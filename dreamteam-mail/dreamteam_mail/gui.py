"""Interface graphique Tkinter de DreamTeam Mail."""

from __future__ import annotations

import queue
import threading
import tkinter as tk
from tkinter import messagebox, ttk

from .backends import (
    Backend,
    BackendDemo,
    BackendIMAP,
    ConfigIMAP,
    backend_par_defaut,
    charger_config,
    sauver_config,
)
from . import theme
from .core import (
    DUREES,
    MAX_ACTIVE,
    TTL_SECONDS,
    GestionnaireAdresses,
    PurgeAutomatique,
    domaine_configure,
    valider_domaine,
)


def titre(domaine: str) -> str:
    return f"DreamTeam Mail — adresses jetables @{domaine}"


class Application(tk.Tk):
    def __init__(self, gestionnaire: GestionnaireAdresses | None = None) -> None:
        super().__init__()
        theme.appliquer(self)
        self.gestionnaire = gestionnaire or GestionnaireAdresses()
        self.title(titre(self.gestionnaire.domaine))
        self.geometry("1040x620")
        self.minsize(880, 540)

        self.backend: Backend = backend_par_defaut()
        self.file_evenements: queue.Queue = queue.Queue()

        self._construire()
        self._rafraichir_liste()

        self.purge = PurgeAutomatique(self.gestionnaire, intervalle=1.0, au_tick=self._sur_purge)
        self.purge.demarrer()
        self.after(500, self._vider_file)
        self.protocol("WM_DELETE_WINDOW", self._fermer)

    # ------------------------------------------------------------ construction
    def _construire(self) -> None:
        barre = ttk.Frame(self, padding=(10, 8))
        barre.pack(fill=tk.X)

        ttk.Button(barre, text="Nouvelle adresse", command=self.creer_adresse).pack(side=tk.LEFT)
        ttk.Button(barre, text="Copier", command=self.copier_adresse).pack(side=tk.LEFT, padx=(6, 0))
        ttk.Button(barre, text="Relever", command=self.relever).pack(side=tk.LEFT, padx=(6, 0))
        ttk.Button(barre, text="Supprimer", command=self.supprimer_adresse).pack(side=tk.LEFT, padx=(6, 0))
        ttk.Button(barre, text="Tout detruire", command=self.tout_supprimer).pack(side=tk.LEFT, padx=(6, 0))
        ttk.Button(barre, text="Serveur…", command=self.configurer_imap).pack(side=tk.RIGHT)

        self.var_style = tk.StringVar(value="mots")
        ttk.Label(barre, text="Style :").pack(side=tk.RIGHT, padx=(12, 4))
        ttk.Combobox(
            barre, textvariable=self.var_style, values=("mots", "aleatoire"),
            width=10, state="readonly",
        ).pack(side=tk.RIGHT)

        self.durees = dict(DUREES)
        defaut = next(lib for lib, sec in DUREES if sec == TTL_SECONDS)
        self.var_duree = tk.StringVar(value=defaut)
        ttk.Label(barre, text="Duree :").pack(side=tk.RIGHT, padx=(12, 4))
        ttk.Combobox(
            barre, textvariable=self.var_duree, values=[lib for lib, _ in DUREES],
            width=12, state="readonly",
        ).pack(side=tk.RIGHT)

        corps = ttk.Panedwindow(self, orient=tk.HORIZONTAL)
        corps.pack(fill=tk.BOTH, expand=True, padx=10, pady=(0, 8))

        gauche = ttk.Frame(corps)
        colonnes = ("email", "restant", "messages")
        self.liste = ttk.Treeview(gauche, columns=colonnes, show="headings", selectmode="browse")
        self.liste.heading("email", text="Adresse")
        self.liste.heading("restant", text="Expire dans")
        self.liste.heading("messages", text="Msg")
        self.liste.column("email", width=300)
        self.liste.column("restant", width=100, anchor=tk.CENTER)
        self.liste.column("messages", width=50, anchor=tk.CENTER)
        self.liste.pack(fill=tk.BOTH, expand=True, side=tk.LEFT)
        defilement = ttk.Scrollbar(gauche, orient=tk.VERTICAL, command=self.liste.yview)
        defilement.pack(fill=tk.Y, side=tk.RIGHT)
        self.liste.configure(yscrollcommand=defilement.set)
        self.liste.bind("<<TreeviewSelect>>", lambda _e: self._afficher_messages())
        corps.add(gauche, weight=1)

        droite = ttk.Frame(corps)
        ttk.Label(droite, text="Boite de reception", style="Titre.TLabel",
                  padding=(0, 0, 0, 6)).pack(anchor=tk.W)
        self.zone = tk.Text(droite, wrap=tk.WORD, state=tk.DISABLED, height=20)
        theme.habiller_texte(self.zone)
        self.zone.pack(fill=tk.BOTH, expand=True)
        corps.add(droite, weight=2)

        self.var_statut = tk.StringVar()
        ttk.Label(self, textvariable=self.var_statut, style="Statut.TLabel",
                  anchor=tk.W).pack(fill=tk.X, side=tk.BOTTOM)
        self._statut(
            f"Domaine : @{self.gestionnaire.domaine} — duree reglable jusqu'a 24 h — "
            f"{MAX_ACTIVE} adresses max — source : {self.backend.nom}"
        )

    # ------------------------------------------------------------------ actions
    def creer_adresse(self) -> None:
        libelle = self.var_duree.get()
        try:
            adresse = self.gestionnaire.creer(
                style=self.var_style.get(), ttl=self.durees.get(libelle, TTL_SECONDS)
            )
        except (RuntimeError, ValueError) as err:
            messagebox.showwarning("Creation impossible", str(err), parent=self)
            return
        self._rafraichir_liste(selection=adresse.email)
        self._copier_presse_papier(adresse.email)
        self._statut(f"{adresse.email} creee et copiee — auto-destruction dans {libelle}.")

    def copier_adresse(self) -> None:
        email = self._selection()
        if email:
            self._copier_presse_papier(email)
            self._statut(f"{email} copiee dans le presse-papier.")

    def supprimer_adresse(self) -> None:
        email = self._selection()
        if not email:
            return
        if self.gestionnaire.supprimer(email):
            self._rafraichir_liste()
            self._statut(f"{email} detruite immediatement.")
            self._effacer_du_serveur([email])

    def tout_supprimer(self) -> None:
        if not messagebox.askyesno("Tout detruire", "Detruire toutes les adresses actives ?", parent=self):
            return
        emails = [a.email for a in self.gestionnaire.actives()]
        n = self.gestionnaire.tout_supprimer()
        self._rafraichir_liste()
        self._statut(f"{n} adresse(s) detruite(s).")
        self._effacer_du_serveur(emails)

    def relever(self) -> None:
        email = self._selection()
        if not email:
            self._statut("Selectionne d'abord une adresse.")
            return
        self._statut(f"Relevé en cours pour {email}…")
        threading.Thread(target=self._relever_en_fond, args=(email,), daemon=True).start()

    def _relever_en_fond(self, email: str) -> None:
        try:
            messages = self.backend.relever(email)
        except Exception as err:  # reseau, auth, etc.
            self.file_evenements.put(("erreur", f"Relevé impossible : {err}"))
            return
        self.file_evenements.put(("messages", (email, messages)))

    def appliquer_domaine(self, domaine: str) -> None:
        """Change le domaine des futures adresses ; les adresses en cours restent valides."""
        self.gestionnaire.domaine = valider_domaine(domaine)
        self.title(titre(self.gestionnaire.domaine))

    def _effacer_du_serveur(self, emails: list[str]) -> None:
        """Vide la boite catch-all des messages de ces alias, sans bloquer l'IHM."""
        if not emails or not getattr(self.backend, "reel", False):
            return
        backend = self.backend

        def travail() -> None:
            total = 0
            for email in emails:
                try:
                    total += backend.supprimer_du_serveur(email)
                except Exception as err:
                    self.file_evenements.put(
                        ("erreur", f"Effacement serveur impossible pour {email} : {err}")
                    )
                    return
            if total:
                self.file_evenements.put(
                    ("info", f"{total} message(s) supprime(s) definitivement du serveur.")
                )

        threading.Thread(target=travail, daemon=True).start()

    def configurer_imap(self) -> None:
        DialogueIMAP(self)

    # ----------------------------------------------------------------- affichage
    def _selection(self) -> str | None:
        choix = self.liste.selection()
        return choix[0] if choix else None

    def _rafraichir_liste(self, selection: str | None = None) -> None:
        selection = selection or self._selection()
        existants = set(self.liste.get_children())
        vivants = set()
        for adresse in self.gestionnaire.actives():
            vivants.add(adresse.email)
            valeurs = (adresse.email, adresse.compte_a_rebours(), len(adresse.messages))
            if adresse.email in existants:
                self.liste.item(adresse.email, values=valeurs)
            else:
                self.liste.insert("", tk.END, iid=adresse.email, values=valeurs)
        for disparu in existants - vivants:
            self.liste.delete(disparu)
        if selection and selection in vivants:
            self.liste.selection_set(selection)
        self._afficher_messages()

    def _afficher_messages(self) -> None:
        email = self._selection()
        adresse = self.gestionnaire.obtenir(email) if email else None
        self.zone.configure(state=tk.NORMAL)
        self.zone.delete("1.0", tk.END)
        if adresse is None:
            self.zone.insert(tk.END, "Aucune adresse selectionnee.\n", "discret")
        elif not adresse.messages:
            self.zone.insert(tk.END, f"{adresse.email}\n", "titre")
            self.zone.insert(tk.END, f"Expire dans {adresse.compte_a_rebours()}.\n\n", "accent")
            self.zone.insert(tk.END, "Boite vide. Clique sur « Relever ».\n", "discret")
        else:
            self.zone.insert(tk.END, f"{adresse.email}\n", "titre")
            self.zone.insert(tk.END, f"Expire dans {adresse.compte_a_rebours()}\n", "accent")
            for msg in reversed(adresse.messages):
                self.zone.insert(tk.END, "\n" + "─" * 60 + "\n", "separateur")
                self.zone.insert(tk.END, "De     : ", "accent")
                self.zone.insert(tk.END, f"{msg.expediteur}\n")
                self.zone.insert(tk.END, "Date   : ", "accent")
                self.zone.insert(tk.END, f"{msg.date}\n")
                self.zone.insert(tk.END, "Objet  : ", "accent")
                self.zone.insert(tk.END, f"{msg.sujet}\n\n", "titre")
                self.zone.insert(tk.END, f"{msg.corps}\n")
        self.zone.configure(state=tk.DISABLED)

    def _copier_presse_papier(self, texte: str) -> None:
        self.clipboard_clear()
        self.clipboard_append(texte)

    def _statut(self, texte: str) -> None:
        self.var_statut.set(texte)

    # ------------------------------------------------------------- evenements
    def _sur_purge(self, purgees: list[str]) -> None:
        self.file_evenements.put(("tick", purgees))

    def _vider_file(self) -> None:
        purge_signalee: list[str] = []
        try:
            while True:
                genre, charge = self.file_evenements.get_nowait()
                if genre == "tick":
                    purge_signalee.extend(charge)
                elif genre == "messages":
                    email, messages = charge
                    ajoutes = self.gestionnaire.ajouter_messages(email, messages)
                    self._statut(
                        f"{ajoutes} nouveau(x) message(s) pour {email}."
                        if ajoutes else f"Aucun nouveau message pour {email}."
                    )
                elif genre in ("erreur", "info"):
                    self._statut(charge)
        except queue.Empty:
            pass
        if purge_signalee:
            self._statut(
                f"Auto-destruction : {', '.join(purge_signalee)} — adresse et messages effaces."
            )
            self._effacer_du_serveur(purge_signalee)
        self._rafraichir_liste()
        self.after(1000, self._vider_file)

    def _fermer(self) -> None:
        self.purge.arreter()
        self.destroy()


class DialogueIMAP(tk.Toplevel):
    """Configuration de la boite catch-all reelle."""

    def __init__(self, parent: Application) -> None:
        super().__init__(parent)
        self.parent = parent
        self.title("Serveur de reception")
        self.configure(background=theme.FOND)
        self.resizable(False, False)
        self.transient(parent)
        self.grab_set()

        config = charger_config()
        self.vars = {
            "hote": tk.StringVar(value=config.hote),
            "port": tk.StringVar(value=str(config.port)),
            "utilisateur": tk.StringVar(value=config.utilisateur),
            "mot_de_passe": tk.StringVar(value=config.mot_de_passe),
            "dossier": tk.StringVar(value=config.dossier),
            "domaine": tk.StringVar(value=parent.gestionnaire.domaine),
        }
        self.var_ssl = tk.BooleanVar(value=config.ssl)
        self.var_enregistrer_mdp = tk.BooleanVar(value=False)
        self.var_supprimer_serveur = tk.BooleanVar(value=config.supprimer_serveur)

        cadre = ttk.Frame(self, padding=12)
        cadre.pack(fill=tk.BOTH, expand=True)
        ttk.Label(
            cadre,
            text=(
                "Indique le domaine que tu possedes et redirige *@<domaine> (catch-all)\n"
                "vers la boite IMAP ci-dessous. Sans cela, l'application reste en\n"
                "mode demonstration (aucun courrier reel)."
            ),
            justify=tk.LEFT,
        ).grid(row=0, column=0, columnspan=2, sticky=tk.W, pady=(0, 10))

        libelles = [
            ("Domaine", "domaine"), ("Hote IMAP", "hote"), ("Port", "port"),
            ("Utilisateur", "utilisateur"), ("Mot de passe", "mot_de_passe"),
            ("Dossier", "dossier"),
        ]
        for i, (libelle, cle) in enumerate(libelles, start=1):
            ttk.Label(cadre, text=libelle + " :").grid(row=i, column=0, sticky=tk.W, pady=3)
            ttk.Entry(
                cadre, textvariable=self.vars[cle], width=34,
                show="•" if cle == "mot_de_passe" else "",
            ).grid(row=i, column=1, sticky=tk.W, pady=3)

        ttk.Checkbutton(cadre, text="SSL/TLS (port 993)", variable=self.var_ssl).grid(
            row=7, column=1, sticky=tk.W, pady=(6, 0))
        ttk.Checkbutton(
            cadre, text="Enregistrer le mot de passe sur ce poste",
            variable=self.var_enregistrer_mdp,
        ).grid(row=8, column=1, sticky=tk.W)
        ttk.Checkbutton(
            cadre, text="Supprimer aussi les mails du serveur a l'expiration",
            variable=self.var_supprimer_serveur,
        ).grid(row=9, column=1, sticky=tk.W)

        boutons = ttk.Frame(cadre)
        boutons.grid(row=10, column=0, columnspan=2, sticky=tk.E, pady=(12, 0))
        ttk.Button(boutons, text="Tester", command=self.tester).pack(side=tk.LEFT)
        ttk.Button(boutons, text="Mode demo", command=self.mode_demo).pack(side=tk.LEFT, padx=6)
        ttk.Button(boutons, text="Enregistrer", command=self.enregistrer).pack(side=tk.LEFT)

    def _config(self) -> ConfigIMAP:
        try:
            port = int(self.vars["port"].get())
        except ValueError:
            port = 993
        return ConfigIMAP(
            hote=self.vars["hote"].get().strip(),
            port=port,
            utilisateur=self.vars["utilisateur"].get().strip(),
            mot_de_passe=self.vars["mot_de_passe"].get(),
            dossier=self.vars["dossier"].get().strip() or "INBOX",
            ssl=self.var_ssl.get(),
            domaine=self.vars["domaine"].get().strip().lower(),
            supprimer_serveur=self.var_supprimer_serveur.get(),
        )

    def tester(self) -> None:
        try:
            message = BackendIMAP(self._config()).tester()
        except Exception as err:
            messagebox.showerror("Echec", str(err), parent=self)
            return
        messagebox.showinfo("Succes", message, parent=self)

    def mode_demo(self) -> None:
        try:
            self.parent.appliquer_domaine(self.vars["domaine"].get().strip().lower())
        except ValueError:
            pass
        self.parent.backend = BackendDemo()
        self.parent._statut("Source : mode demonstration (aucun courrier reel).")
        self.destroy()

    def enregistrer(self) -> None:
        config = self._config()
        try:
            domaine = valider_domaine(config.domaine)
            config.domaine = domaine
            backend = BackendIMAP(config)
        except ValueError as err:
            messagebox.showwarning("Configuration invalide", str(err), parent=self)
            return
        sauver_config(config, avec_mot_de_passe=self.var_enregistrer_mdp.get())
        self.parent.backend = backend
        self.parent.appliquer_domaine(domaine)
        self.parent._statut(f"Source : {backend.nom} — {config.hote} — domaine @{domaine}")
        self.destroy()


def lancer() -> int:
    Application().mainloop()
    return 0
