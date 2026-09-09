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
    RELEVE_AUTO_SECONDES,
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
        self.geometry("1240x700")
        self.minsize(1000, 580)

        self.backend: Backend = backend_par_defaut()
        self.file_evenements: queue.Queue = queue.Queue()
        self._releve_en_cours: set[str] = set()

        self._construire()
        self._rafraichir_liste()

        self.purge = PurgeAutomatique(self.gestionnaire, intervalle=1.0, au_tick=self._sur_purge)
        self.purge.demarrer()
        self.after(500, self._vider_file)
        self.after(RELEVE_AUTO_SECONDES * 1000, self._releve_automatique)
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

        self.var_auto = tk.BooleanVar(value=True)
        ttk.Checkbutton(
            barre, text=f"Releve auto ({RELEVE_AUTO_SECONDES} s)", variable=self.var_auto,
        ).pack(side=tk.RIGHT, padx=(12, 12))

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
        self.liste.column("messages", width=60, anchor=tk.CENTER)
        self.liste.pack(fill=tk.BOTH, expand=True, side=tk.LEFT)
        defilement = ttk.Scrollbar(gauche, orient=tk.VERTICAL, command=self.liste.yview)
        defilement.pack(fill=tk.Y, side=tk.RIGHT)
        self.liste.configure(yscrollcommand=defilement.set)
        self.liste.bind("<<TreeviewSelect>>", lambda _e: self._afficher_messages())
        corps.add(gauche, weight=1)

        droite = ttk.Frame(corps)
        self.var_boite = tk.StringVar(value="Boite de reception")
        ttk.Label(droite, textvariable=self.var_boite, style="Titre.TLabel",
                  padding=(0, 0, 0, 6)).pack(anchor=tk.W)

        lecture = ttk.Panedwindow(droite, orient=tk.VERTICAL)
        lecture.pack(fill=tk.BOTH, expand=True)

        entete = ttk.Frame(lecture)
        colonnes_msg = ("de", "objet", "date")
        self.messages = ttk.Treeview(
            entete, columns=colonnes_msg, show="headings", selectmode="browse"
        )
        self.messages.heading("de", text="De")
        self.messages.heading("objet", text="Objet")
        self.messages.heading("date", text="Date")
        self.messages.column("de", width=170, stretch=False)
        self.messages.column("objet", width=420)
        self.messages.column("date", width=130, stretch=False, anchor=tk.E)
        self.messages.tag_configure("nonlu", font=theme.police(gras=True), foreground=theme.ORANGE)
        self.messages.tag_configure("lu", foreground=theme.ROUGE)
        self.messages.pack(fill=tk.BOTH, expand=True, side=tk.LEFT)
        defilement_msg = ttk.Scrollbar(entete, orient=tk.VERTICAL, command=self.messages.yview)
        defilement_msg.pack(fill=tk.Y, side=tk.RIGHT)
        self.messages.configure(yscrollcommand=defilement_msg.set)
        self.messages.bind("<<TreeviewSelect>>", lambda _e: self._ouvrir_message())
        lecture.add(entete, weight=2)

        bas = ttk.Frame(lecture)
        self.zone = tk.Text(bas, wrap=tk.WORD, state=tk.DISABLED, height=12)
        theme.habiller_texte(self.zone)
        self.zone.pack(fill=tk.BOTH, expand=True)
        lecture.add(bas, weight=3)

        self._signature_boite: tuple | None = None
        corps.add(droite, weight=3)

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
        self._lancer_releve(email)

    def _lancer_releve(self, email: str, silencieux: bool = False) -> None:
        if email in self._releve_en_cours:
            return  # un relevé precedent est encore en vol
        self._releve_en_cours.add(email)
        threading.Thread(
            target=self._relever_en_fond, args=(email, silencieux), daemon=True
        ).start()

    def _relever_en_fond(self, email: str, silencieux: bool = False) -> None:
        try:
            messages = self.backend.relever(email)
        except Exception as err:  # reseau, auth, etc.
            if not silencieux:  # un relevé de fond echoue en silence
                self.file_evenements.put(("erreur", f"Relevé impossible : {err}"))
            self.file_evenements.put(("fin_releve", email))
            return
        self.file_evenements.put(("messages", (email, messages, silencieux)))
        self.file_evenements.put(("fin_releve", email))

    def _releve_automatique(self) -> None:
        """Relève l'adresse selectionnee a intervalle regulier, si l'option est active."""
        try:
            if self.var_auto.get():
                email = self._selection()
                if email and self.gestionnaire.obtenir(email) is not None:
                    self._lancer_releve(email, silencieux=True)
        finally:
            self.after(RELEVE_AUTO_SECONDES * 1000, self._releve_automatique)

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
            non_lus = sum(1 for m in adresse.messages if not m.lu)
            compteur = f"{non_lus}/{len(adresse.messages)}" if non_lus else str(len(adresse.messages))
            valeurs = (adresse.email, adresse.compte_a_rebours(), compteur)
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
        """Met a jour la liste des messages de l'adresse selectionnee."""
        email = self._selection()
        adresse = self.gestionnaire.obtenir(email) if email else None
        if adresse is None:
            self.var_boite.set("Boite de reception")
            if self._signature_boite is not None:
                self.messages.delete(*self.messages.get_children())
                self._ecrire_corps(("Aucune adresse selectionnee.", "discret"))
                self._signature_boite = None
            return

        non_lus = sum(1 for m in adresse.messages if not m.lu)
        titre_boite = f"{adresse.email} — expire dans {adresse.compte_a_rebours()}"
        if non_lus:
            titre_boite += f" — {non_lus} non lu(s)"
        self.var_boite.set(titre_boite)

        signature = (adresse.email, tuple((m.date, m.sujet, m.lu) for m in adresse.messages))
        if signature == self._signature_boite:
            return  # rien de neuf : on ne reconstruit pas la liste sous la souris
        self._signature_boite = signature

        choix = self.messages.selection()
        precedent = choix[0] if choix else None
        self.messages.delete(*self.messages.get_children())
        for indice in range(len(adresse.messages) - 1, -1, -1):  # plus recent en haut
            msg = adresse.messages[indice]
            objet = msg.sujet or "(sans objet)"
            apercu = msg.apercu()
            self.messages.insert(
                "", tk.END, iid=str(indice),
                values=(msg.expediteur_court(), f"{objet}  —  {apercu}", msg.date),
                tags=("lu" if msg.lu else "nonlu",),
            )
        if precedent and self.messages.exists(precedent):
            self.messages.selection_set(precedent)
        elif not adresse.messages:
            self._ecrire_corps(
                (f"{adresse.email}\n", "titre"),
                (f"Expire dans {adresse.compte_a_rebours()}.\n\n", "accent"),
                ("Boite vide. Le releve automatique tourne toutes les 30 s.\n", "discret"),
            )

    def _ouvrir_message(self) -> None:
        """Affiche le message selectionne et le marque comme lu."""
        email = self._selection()
        adresse = self.gestionnaire.obtenir(email) if email else None
        choix = self.messages.selection()
        if adresse is None or not choix:
            return
        try:
            msg = adresse.messages[int(choix[0])]
        except (ValueError, IndexError):
            return
        self._ecrire_corps(
            (f"{msg.sujet or '(sans objet)'}\n", "titre"),
            (f"De   : {msg.expediteur}\n", "accent"),
            (f"Date : {msg.date}\n", "accent"),
            ("─" * 60 + "\n\n", "separateur"),
            (f"{msg.corps}\n", None),
        )
        if not msg.lu:
            msg.lu = True
            self.messages.item(choix[0], tags=("lu",))
            self.gestionnaire.sauver()
            self._afficher_messages()

    def _ecrire_corps(self, *morceaux: tuple) -> None:
        """Remplit le volet de lecture : suite de (texte, style)."""
        self.zone.configure(state=tk.NORMAL)
        self.zone.delete("1.0", tk.END)
        for texte, style in morceaux:
            self.zone.insert(tk.END, texte, style) if style else self.zone.insert(tk.END, texte)
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
                    email, messages, silencieux = charge
                    ajoutes = self.gestionnaire.ajouter_messages(email, messages)
                    if ajoutes:
                        self._statut(f"{ajoutes} nouveau(x) message(s) pour {email}.")
                    elif not silencieux:
                        self._statut(f"Aucun nouveau message pour {email}.")
                elif genre == "fin_releve":
                    self._releve_en_cours.discard(charge)
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
