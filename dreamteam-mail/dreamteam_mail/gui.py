"""Interface graphique d'Email Destructor : onglets Emails et Messages."""

from __future__ import annotations

import queue
import threading
import tkinter as tk
from tkinter import messagebox, ttk

from . import theme
from .backends import (
    Backend,
    BackendAPI,
    BackendDemo,
    BackendIMAP,
    ConfigIMAP,
    backend_par_defaut,
    charger_config,
    deja_configure,
    sauver_config,
)
from .core import (
    DUREES,
    MAX_ACTIVE,
    RELEVE_AUTO_SECONDES,
    TTL_PERMANENT,
    TTL_SECONDS,
    GestionnaireAdresses,
    PurgeAutomatique,
    date_courte,
    domaine_configure,
    valider_domaine,
)

CREDIT = "Email Destructor crée par mNzy | DreamTeam 2026"


def titre(domaine: str) -> str:
    return f"Email Destructor — adresses jetables @{domaine}"


class Application(tk.Tk):
    def __init__(self, gestionnaire: GestionnaireAdresses | None = None) -> None:
        super().__init__()
        theme.appliquer(self)
        self.gestionnaire = gestionnaire or GestionnaireAdresses()
        self.title(titre(self.gestionnaire.domaine))
        self.geometry("1240x720")
        self.minsize(1000, 600)

        self.backend: Backend = backend_par_defaut()
        self.file_evenements: queue.Queue = queue.Queue()
        self._releve_en_cours: set[str] = set()
        self._signature_boite: tuple | None = None

        self._construire()
        self._rafraichir_liste()

        self.purge = PurgeAutomatique(self.gestionnaire, intervalle=1.0, au_tick=self._sur_purge)
        self.purge.demarrer()
        self.after(500, self._vider_file)
        self.after(RELEVE_AUTO_SECONDES * 1000, self._releve_automatique)
        # Porte de service : rouvre la configuration meme quand le bouton est cache.
        self.bind_all("<Control-Shift-S>", lambda _e: self.configurer_serveur())
        self.protocol("WM_DELETE_WINDOW", self._fermer)

    # ------------------------------------------------------------ construction
    def _construire(self) -> None:
        self._construire_barre()

        self.onglets = ttk.Notebook(self)
        self.onglets.pack(fill=tk.BOTH, expand=True, padx=(0, 10), pady=(10, 8))
        self.onglets.add(self._onglet_emails(), text="  Emails  ")
        self.onglets.add(self._onglet_messages(), text="  Messages  ")
        self.onglets.bind("<<NotebookTabChanged>>", lambda _e: self._afficher_messages())

        self._construire_pied()

    def _construire_barre(self) -> None:
        barre = ttk.Frame(self, padding=(10, 10), style="Barre.TFrame")
        barre.pack(fill=tk.Y, side=tk.LEFT)

        ttk.Label(barre, text="EMAIL", style="Marque.TLabel").pack(anchor=tk.W)
        ttk.Label(barre, text="DESTRUCTOR", style="Marque.TLabel").pack(anchor=tk.W, pady=(0, 14))

        ttk.Button(barre, text="Emails", width=18,
                   command=lambda: self.onglets.select(0)).pack(fill=tk.X, pady=3)
        ttk.Button(barre, text="Messages", width=18,
                   command=lambda: self.onglets.select(1)).pack(fill=tk.X, pady=3)

        ttk.Separator(barre, orient=tk.HORIZONTAL).pack(fill=tk.X, pady=12)

        ttk.Label(barre, text="Duree de vie", style="Barre.TLabel").pack(anchor=tk.W, pady=(0, 4))
        self.durees = dict(DUREES)
        defaut = next(lib for lib, sec in DUREES if sec == TTL_SECONDS)
        self.var_duree = tk.StringVar(value=defaut)
        ttk.Combobox(
            barre, textvariable=self.var_duree, values=[lib for lib, _ in DUREES],
            width=16, state="readonly",
        ).pack(fill=tk.X)

        ttk.Button(barre, text="Nouvelle adresse", width=18,
                   command=self.creer_adresse).pack(fill=tk.X, pady=(10, 3))
        ttk.Button(barre, text="Restaurer…", width=18,
                   command=self.restaurer_adresse).pack(fill=tk.X, pady=3)

        ttk.Label(
            barre, text=f"Releve automatique\ntoutes les {RELEVE_AUTO_SECONDES} s",
            style="Discret.TLabel", justify=tk.LEFT,
        ).pack(anchor=tk.W, pady=(14, 0))

        ttk.Frame(barre, style="Barre.TFrame").pack(fill=tk.BOTH, expand=True)
        # Le bouton disparait des qu'une source reelle est enregistree.
        self.bouton_serveur = ttk.Button(
            barre, text="Serveur…", width=18, command=self.configurer_serveur
        )
        if not deja_configure():
            self.bouton_serveur.pack(fill=tk.X)

    def _onglet_emails(self) -> ttk.Frame:
        cadre = ttk.Frame(self.onglets, padding=10)

        actions = ttk.Frame(cadre)
        actions.pack(fill=tk.X, pady=(0, 8))
        ttk.Button(actions, text="Copier", command=self.copier_adresse).pack(side=tk.LEFT)
        ttk.Button(actions, text="Voir le mot de passe",
                   command=self.montrer_mot_de_passe).pack(side=tk.LEFT, padx=6)
        ttk.Button(actions, text="Supprimer", command=self.supprimer_adresse).pack(side=tk.LEFT)
        ttk.Button(actions, text="Tout detruire",
                   command=self.tout_supprimer).pack(side=tk.LEFT, padx=6)

        colonnes = ("email", "restant", "messages")
        self.liste = ttk.Treeview(cadre, columns=colonnes, show="headings", selectmode="browse")
        self.liste.heading("email", text="Adresse")
        self.liste.heading("restant", text="Expire dans")
        self.liste.heading("messages", text="Msg")
        self.liste.column("email", width=420, minwidth=260)
        self.liste.column("restant", width=140, minwidth=110, anchor=tk.CENTER)
        self.liste.column("messages", width=80, minwidth=60, anchor=tk.CENTER)
        self.liste.tag_configure("a_vie", foreground=theme.ORANGE_VIF)
        self.liste.pack(fill=tk.BOTH, expand=True, side=tk.LEFT)
        defilement = ttk.Scrollbar(cadre, orient=tk.VERTICAL, command=self.liste.yview)
        defilement.pack(fill=tk.Y, side=tk.RIGHT)
        self.liste.configure(yscrollcommand=defilement.set)
        self.liste.bind("<<TreeviewSelect>>", lambda _e: self._afficher_messages())
        self.liste.bind("<Double-1>", lambda _e: self.onglets.select(1))
        return cadre

    def _onglet_messages(self) -> ttk.Frame:
        cadre = ttk.Frame(self.onglets, padding=10)

        actions = ttk.Frame(cadre)
        actions.pack(fill=tk.X, pady=(0, 8))
        ttk.Button(actions, text="Relever", command=self.relever).pack(side=tk.LEFT)
        self.bouton_repondre = ttk.Button(actions, text="Repondre", command=self.repondre)
        self.bouton_repondre.pack(side=tk.LEFT, padx=6)
        self.var_boite = tk.StringVar(value="Aucune adresse selectionnee")
        ttk.Label(actions, textvariable=self.var_boite,
                  style="Titre.TLabel").pack(side=tk.LEFT, padx=(12, 0))

        lecture = ttk.Panedwindow(cadre, orient=tk.VERTICAL)
        lecture.pack(fill=tk.BOTH, expand=True)

        haut = ttk.Frame(lecture)
        self.messages = ttk.Treeview(
            haut, columns=("de", "objet", "date"), show="headings", selectmode="browse"
        )
        for cle, texte in (("de", "De"), ("objet", "Objet"), ("date", "Date")):
            self.messages.heading(cle, text=texte)
        self.messages.column("de", width=230, minwidth=140, stretch=False)
        self.messages.column("objet", width=380, minwidth=200)
        self.messages.column("date", width=170, minwidth=150, stretch=False, anchor=tk.E)
        self.messages.tag_configure("nonlu", font=theme.police(gras=True), foreground=theme.ORANGE)
        self.messages.tag_configure("lu", foreground=theme.ROUGE)
        self.messages.pack(fill=tk.BOTH, expand=True, side=tk.LEFT)
        barre_msg = ttk.Scrollbar(haut, orient=tk.VERTICAL, command=self.messages.yview)
        barre_msg.pack(fill=tk.Y, side=tk.RIGHT)
        self.messages.configure(yscrollcommand=barre_msg.set)
        self.messages.bind("<<TreeviewSelect>>", lambda _e: self._ouvrir_message())
        lecture.add(haut, weight=2)

        bas = ttk.Frame(lecture)
        self.zone = tk.Text(bas, wrap=tk.WORD, state=tk.DISABLED, height=14)
        theme.habiller_texte(self.zone)
        self.zone.pack(fill=tk.BOTH, expand=True, side=tk.LEFT)
        barre_zone = ttk.Scrollbar(bas, orient=tk.VERTICAL, command=self.zone.yview)
        barre_zone.pack(fill=tk.Y, side=tk.RIGHT)
        self.zone.configure(yscrollcommand=barre_zone.set)
        lecture.add(bas, weight=3)
        return cadre

    def _construire_pied(self) -> None:
        pied = ttk.Frame(self, style="Statut.TFrame")
        pied.pack(fill=tk.X, side=tk.BOTTOM)
        self.var_statut = tk.StringVar()
        ttk.Label(pied, textvariable=self.var_statut, style="Statut.TLabel",
                  anchor=tk.W).pack(fill=tk.X, side=tk.LEFT, expand=True)
        ttk.Label(pied, text=CREDIT, style="Credit.TLabel", anchor=tk.E).pack(side=tk.RIGHT)
        self._statut(
            f"Domaine : @{self.gestionnaire.domaine} — {MAX_ACTIVE} adresses max — "
            f"source : {self.backend.nom}"
        )

    # ------------------------------------------------------------------ adresses
    def creer_adresse(self) -> None:
        libelle = self.var_duree.get()
        duree = self.durees.get(libelle, TTL_SECONDS)
        if hasattr(self.backend, "creer_alias"):
            self._statut("Demande d'une adresse au serveur…")
            backend = self.backend
            threading.Thread(
                target=lambda: self._alias_distant(backend, duree, libelle), daemon=True
            ).start()
            return
        try:
            adresse = self.gestionnaire.creer(ttl=duree)
        except (RuntimeError, ValueError) as err:
            messagebox.showwarning("Creation impossible", str(err), parent=self)
            return
        self._installer_adresse(adresse, libelle)

    def _alias_distant(self, backend, duree: int, libelle: str) -> None:
        try:
            infos = backend.creer_alias(duree)
        except Exception as err:
            self.file_evenements.put(("erreur", f"Creation refusee : {err}"))
            return
        self.file_evenements.put(("alias", (infos, libelle)))

    def _installer_adresse(self, adresse, libelle: str) -> None:
        self._rafraichir_liste(selection=adresse.email)
        self._copier_presse_papier(adresse.email)
        self._statut(f"{adresse.email} creee et copiee — duree : {libelle}.")
        if adresse.permanente:
            DialogueMotDePasse(self, adresse, nouvelle=True)

    def copier_adresse(self) -> None:
        email = self._selection()
        if email:
            self._copier_presse_papier(email)
            self._statut(f"{email} copiee dans le presse-papier.")

    def montrer_mot_de_passe(self) -> None:
        adresse = self.gestionnaire.obtenir(self._selection() or "")
        if adresse is None:
            self._statut("Selectionne d'abord une adresse.")
        elif not adresse.permanente:
            self._statut("Seules les adresses a vie ont un mot de passe.")
        else:
            DialogueMotDePasse(self, adresse)

    def restaurer_adresse(self) -> None:
        DialogueRestauration(self)

    def supprimer_adresse(self) -> None:
        email = self._selection()
        if not email:
            return
        adresse = self.gestionnaire.obtenir(email)
        if adresse is None:
            return
        if adresse.permanente and not messagebox.askyesno(
            "Supprimer une adresse a vie",
            f"{email} est conservee a vie. La detruire est definitif.\n\nContinuer ?",
            parent=self,
        ):
            return
        if self.gestionnaire.supprimer(email):
            self._rafraichir_liste()
            self._statut(f"{email} detruite immediatement.")
            self._effacer_du_serveur([adresse])

    def tout_supprimer(self) -> None:
        if not messagebox.askyesno(
            "Tout detruire", "Detruire toutes les adresses, y compris celles a vie ?", parent=self
        ):
            return
        adresses = list(self.gestionnaire.actives())
        n = self.gestionnaire.tout_supprimer()
        self._rafraichir_liste()
        self._statut(f"{n} adresse(s) detruite(s).")
        self._effacer_du_serveur(adresses)

    # ------------------------------------------------------------------ courrier
    def relever(self) -> None:
        email = self._selection()
        if not email:
            self._statut("Selectionne d'abord une adresse dans l'onglet Emails.")
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
        adresse = self.gestionnaire.obtenir(email)
        try:
            messages = self.backend.relever(email, getattr(adresse, "jeton", ""))
        except Exception as err:
            if not silencieux:  # un relevé de fond echoue en silence
                self.file_evenements.put(("erreur", f"Relevé impossible : {err}"))
            self.file_evenements.put(("fin_releve", email))
            return
        self.file_evenements.put(("messages", (email, messages, silencieux)))
        self.file_evenements.put(("fin_releve", email))

    def _releve_automatique(self) -> None:
        try:
            email = self._selection()
            if email and self.gestionnaire.obtenir(email) is not None:
                self._lancer_releve(email, silencieux=True)
        finally:
            self.after(RELEVE_AUTO_SECONDES * 1000, self._releve_automatique)

    def repondre(self) -> None:
        adresse = self.gestionnaire.obtenir(self._selection() or "")
        message = self._message_choisi(adresse)
        if adresse is None or message is None:
            self._statut("Selectionne un message pour y repondre.")
            return
        if not getattr(self.backend, "peut_envoyer", False):
            messagebox.showinfo(
                "Envoi indisponible",
                "La source actuelle ne permet pas d'envoyer de courrier.\n"
                "Configure le serveur (API ou IMAP) pour repondre.",
                parent=self,
            )
            return
        DialogueReponse(self, adresse, message)

    def envoyer_reponse(self, adresse, destinataire: str, sujet: str, corps: str) -> None:
        """Envoi en tache de fond : le reseau ne doit pas figer la fenetre."""
        backend = self.backend
        self._statut(f"Envoi vers {destinataire}…")

        def travail() -> None:
            try:
                backend.envoyer(adresse.email, destinataire, sujet, corps, adresse.jeton)
            except Exception as err:
                self.file_evenements.put(("erreur", f"Envoi refuse : {err}"))
                return
            self.file_evenements.put(("info", f"Reponse envoyee a {destinataire}."))

        threading.Thread(target=travail, daemon=True).start()

    def _effacer_du_serveur(self, adresses: list) -> None:
        cibles = [(a.email, a.jeton) for a in adresses]
        if not cibles or not getattr(self.backend, "reel", False):
            return
        backend = self.backend

        def travail() -> None:
            total = 0
            for email, jeton in cibles:
                try:
                    total += backend.supprimer_du_serveur(email, jeton)
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

    # ----------------------------------------------------------------- affichage
    def _selection(self) -> str | None:
        choix = self.liste.selection()
        return choix[0] if choix else None

    def _message_choisi(self, adresse):
        choix = self.messages.selection()
        if adresse is None or not choix:
            return None
        try:
            return adresse.messages[int(choix[0])]
        except (ValueError, IndexError):
            return None

    def _rafraichir_liste(self, selection: str | None = None) -> None:
        selection = selection or self._selection()
        existants = set(self.liste.get_children())
        vivants = set()
        for adresse in self.gestionnaire.actives():
            vivants.add(adresse.email)
            non_lus = sum(1 for m in adresse.messages if not m.lu)
            compteur = f"{non_lus}/{len(adresse.messages)}" if non_lus else str(len(adresse.messages))
            valeurs = (adresse.email, adresse.compte_a_rebours(), compteur)
            marque = ("a_vie",) if adresse.permanente else ()
            if adresse.email in existants:
                self.liste.item(adresse.email, values=valeurs, tags=marque)
            else:
                self.liste.insert("", tk.END, iid=adresse.email, values=valeurs, tags=marque)
        for disparu in existants - vivants:
            self.liste.delete(disparu)
        if selection and selection in vivants:
            self.liste.selection_set(selection)
        self._afficher_messages()

    def _afficher_messages(self) -> None:
        email = self._selection()
        adresse = self.gestionnaire.obtenir(email) if email else None
        if adresse is None:
            self.var_boite.set("Aucune adresse selectionnee")
            if self._signature_boite is not None:
                self.messages.delete(*self.messages.get_children())
                self._ecrire_corps(("Choisis une adresse dans l'onglet Emails.\n", "discret"))
                self._signature_boite = None
            return

        non_lus = sum(1 for m in adresse.messages if not m.lu)
        etiquette = f"{adresse.email} — {adresse.compte_a_rebours()}"
        if non_lus:
            etiquette += f" — {non_lus} non lu(s)"
        if not getattr(self.backend, "reel", False):
            etiquette += "   [DEMONSTRATION — aucun courrier reel]"
        self.var_boite.set(etiquette)

        signature = (adresse.email, tuple((m.date, m.sujet, m.lu) for m in adresse.messages))
        if signature == self._signature_boite:
            return  # rien de neuf : on ne reconstruit pas la liste sous la souris
        self._signature_boite = signature

        choix = self.messages.selection()
        precedent = choix[0] if choix else None
        self.messages.delete(*self.messages.get_children())
        for indice in range(len(adresse.messages) - 1, -1, -1):  # plus recent en haut
            msg = adresse.messages[indice]
            self.messages.insert(
                "", tk.END, iid=str(indice),
                values=(msg.expediteur_court(),
                        f"{msg.sujet or '(sans objet)'}  —  {msg.apercu()}",
                        date_courte(msg.date)),
                tags=("lu" if msg.lu else "nonlu",),
            )
        if precedent and self.messages.exists(precedent):
            self.messages.selection_set(precedent)
        elif not adresse.messages:
            self._ecrire_corps(
                (f"{adresse.email}\n", "titre"),
                (f"{adresse.compte_a_rebours()}\n\n", "accent"),
                ("Boite vide. Le releve automatique tourne toutes les 30 s.\n", "discret"),
            )

    def _ouvrir_message(self) -> None:
        adresse = self.gestionnaire.obtenir(self._selection() or "")
        msg = self._message_choisi(adresse)
        if msg is None:
            return
        self._ecrire_corps(
            (f"{msg.sujet or '(sans objet)'}\n\n", "titre"),
            ("De     ", "accent"), (f": {msg.expediteur}\n", None),
            ("Date   ", "accent"), (f": {msg.date}\n", None),
            ("Pour   ", "accent"), (f": {adresse.email}\n", None),
            ("\n" + "─" * 58 + "\n\n", "separateur"),
            (msg.corps_aere() + "\n", None),
        )
        if not msg.lu:
            msg.lu = True
            self.messages.item(self.messages.selection()[0], tags=("lu",))
            self.gestionnaire.sauver()
            self._afficher_messages()

    def _ecrire_corps(self, *morceaux: tuple) -> None:
        self.zone.configure(state=tk.NORMAL)
        self.zone.delete("1.0", tk.END)
        for texte, style in morceaux:
            if style:
                self.zone.insert(tk.END, texte, style)
            else:
                self.zone.insert(tk.END, texte)
        self.zone.configure(state=tk.DISABLED)

    def _copier_presse_papier(self, texte: str) -> None:
        self.clipboard_clear()
        self.clipboard_append(texte)

    def _statut(self, texte: str) -> None:
        self.var_statut.set(texte)

    # ------------------------------------------------------------- configuration
    def configurer_serveur(self) -> None:
        DialogueServeur(self)

    def appliquer_domaine(self, domaine: str) -> None:
        """Change le domaine des futures adresses ; les adresses en cours restent valides."""
        self.gestionnaire.domaine = valider_domaine(domaine)
        self.title(titre(self.gestionnaire.domaine))

    def masquer_bouton_serveur(self) -> None:
        """Une fois la source enregistree, la configuration sort de la vue.

        Ctrl+Shift+S la rouvre : sans cela une erreur de saisie enfermerait
        l'utilisateur hors de son propre reglage.
        """
        self.bouton_serveur.pack_forget()

    # ------------------------------------------------------------- evenements
    def _sur_purge(self, purgees: list) -> None:
        self.file_evenements.put(("tick", purgees))

    def _vider_file(self) -> None:
        purge_signalee: list = []
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
                elif genre == "alias":
                    infos, libelle = charge
                    try:
                        adresse = self.gestionnaire.creer(
                            local=infos["local"], ttl=infos["ttl"], jeton=infos["jeton"]
                        )
                    except (RuntimeError, ValueError) as err:
                        self._statut(f"Adresse refusee localement : {err}")
                    else:
                        self._installer_adresse(adresse, libelle)
                elif genre == "fin_releve":
                    self._releve_en_cours.discard(charge)
                elif genre in ("erreur", "info"):
                    self._statut(charge)
        except queue.Empty:
            pass
        if purge_signalee:
            noms = ", ".join(a.email for a in purge_signalee)
            self._statut(f"Auto-destruction : {noms} — adresse et messages effaces.")
            self._effacer_du_serveur(purge_signalee)
        self._rafraichir_liste()
        self.after(1000, self._vider_file)

    def _fermer(self) -> None:
        self.purge.arreter()
        self.destroy()


class DialogueMotDePasse(tk.Toplevel):
    """Affiche le mot de passe d'une adresse a vie."""

    def __init__(self, parent: Application, adresse, nouvelle: bool = False) -> None:
        super().__init__(parent)
        self.title("Adresse a vie")
        self.configure(background=theme.FOND)
        self.resizable(False, False)
        self.transient(parent)
        self.grab_set()

        cadre = ttk.Frame(self, padding=16)
        cadre.pack(fill=tk.BOTH, expand=True)
        ttk.Label(cadre, text=adresse.email, style="Titre.TLabel").pack(anchor=tk.W)
        ttk.Label(
            cadre, justify=tk.LEFT, text=(
                "Cette adresse n'expire jamais.\n"
                "Note son mot de passe : il sert a la restaurer sur un autre poste,\n"
                "ou apres une reinstallation."
                + ("\n\nIl est aussi enregistre dans l'application sur ce poste." if nouvelle else "")
            ),
        ).pack(anchor=tk.W, pady=(8, 10))

        champ = ttk.Entry(cadre, width=32, font=theme.police(12, gras=True))
        champ.insert(0, adresse.mot_de_passe)
        champ.configure(state="readonly")
        champ.pack(fill=tk.X)

        boutons = ttk.Frame(cadre)
        boutons.pack(fill=tk.X, pady=(12, 0))
        ttk.Button(boutons, text="Copier le mot de passe",
                   command=lambda: self._copier(parent, adresse)).pack(side=tk.LEFT)
        ttk.Button(boutons, text="Fermer", command=self.destroy).pack(side=tk.RIGHT)

    def _copier(self, parent: Application, adresse) -> None:
        parent._copier_presse_papier(adresse.mot_de_passe)
        parent._statut(f"Mot de passe de {adresse.email} copie.")


class DialogueRestauration(tk.Toplevel):
    """Reprend une adresse a vie a partir de son nom et de son mot de passe."""

    def __init__(self, parent: Application) -> None:
        super().__init__(parent)
        self.parent = parent
        self.title("Restaurer une adresse a vie")
        self.configure(background=theme.FOND)
        self.resizable(False, False)
        self.transient(parent)
        self.grab_set()

        cadre = ttk.Frame(self, padding=16)
        cadre.pack(fill=tk.BOTH, expand=True)
        ttk.Label(cadre, justify=tk.LEFT, text=(
            "Saisis l'adresse et le mot de passe notes lors de sa creation.\n"
            "Le courrier deja recu sera relu depuis le serveur."
        )).grid(row=0, column=0, columnspan=2, sticky=tk.W, pady=(0, 10))

        self.var_email = tk.StringVar()
        self.var_mdp = tk.StringVar()
        for i, (libelle, variable) in enumerate(
            (("Adresse", self.var_email), ("Mot de passe", self.var_mdp)), start=1
        ):
            ttk.Label(cadre, text=libelle + " :").grid(row=i, column=0, sticky=tk.W, pady=4)
            ttk.Entry(cadre, textvariable=variable, width=34).grid(row=i, column=1, pady=4)

        boutons = ttk.Frame(cadre)
        boutons.grid(row=3, column=0, columnspan=2, sticky=tk.E, pady=(12, 0))
        ttk.Button(boutons, text="Annuler", command=self.destroy).pack(side=tk.RIGHT)
        ttk.Button(boutons, text="Restaurer", command=self.restaurer).pack(side=tk.RIGHT, padx=6)

    def restaurer(self) -> None:
        email = self.var_email.get().strip().lower()
        mot_de_passe = self.var_mdp.get().strip()
        if "@" not in email or not mot_de_passe:
            messagebox.showwarning(
                "Champs incomplets", "Indique l'adresse complete et son mot de passe.", parent=self
            )
            return
        local, _, domaine = email.partition("@")
        try:
            adresse = self.parent.gestionnaire.creer(
                local=local, ttl=TTL_PERMANENT, jeton=mot_de_passe, mot_de_passe=mot_de_passe
            )
        except (RuntimeError, ValueError) as err:
            messagebox.showwarning("Restauration impossible", str(err), parent=self)
            return
        if domaine and domaine != adresse.domaine:
            self.parent._statut(
                f"Attention : {email} n'est pas sur @{adresse.domaine}, adresse recreee en "
                f"{adresse.email}."
            )
        self.parent._rafraichir_liste(selection=adresse.email)
        self.parent._lancer_releve(adresse.email)
        self.destroy()


class DialogueReponse(tk.Toplevel):
    """Redaction d'une reponse depuis l'adresse jetable."""

    def __init__(self, parent: Application, adresse, message) -> None:
        super().__init__(parent)
        self.parent = parent
        self.adresse = adresse
        self.title(f"Repondre — {adresse.email}")
        self.configure(background=theme.FOND)
        self.geometry("760x560")
        self.transient(parent)

        cadre = ttk.Frame(self, padding=14)
        cadre.pack(fill=tk.BOTH, expand=True)

        sujet = message.sujet or "(sans objet)"
        self.var_a = tk.StringVar(value=message.adresse_expediteur())
        self.var_sujet = tk.StringVar(
            value=sujet if sujet.lower().startswith("re:") else f"Re: {sujet}"
        )
        for i, (libelle, variable) in enumerate(
            (("De", tk.StringVar(value=adresse.email)),
             ("A", self.var_a), ("Objet", self.var_sujet))
        ):
            ttk.Label(cadre, text=libelle + " :").grid(row=i, column=0, sticky=tk.W, pady=3)
            champ = ttk.Entry(cadre, textvariable=variable, width=70)
            if libelle == "De":
                champ.configure(state="readonly")
            champ.grid(row=i, column=1, sticky=tk.EW, pady=3)
        cadre.grid_columnconfigure(1, weight=1)

        self.corps = tk.Text(cadre, wrap=tk.WORD, height=18)
        theme.habiller_texte(self.corps)
        self.corps.grid(row=3, column=0, columnspan=2, sticky=tk.NSEW, pady=(10, 0))
        cadre.grid_rowconfigure(3, weight=1)
        citation = "\n".join("> " + ligne for ligne in message.corps_aere().splitlines())
        self.corps.insert("1.0", f"\n\n--- Le {message.date}, {message.expediteur_court()} :\n{citation}\n")
        self.corps.mark_set(tk.INSERT, "1.0")

        boutons = ttk.Frame(cadre)
        boutons.grid(row=4, column=0, columnspan=2, sticky=tk.E, pady=(12, 0))
        ttk.Button(boutons, text="Annuler", command=self.destroy).pack(side=tk.RIGHT)
        ttk.Button(boutons, text="Envoyer", command=self.envoyer).pack(side=tk.RIGHT, padx=6)

    def envoyer(self) -> None:
        destinataire = self.var_a.get().strip()
        corps = self.corps.get("1.0", tk.END).strip()
        if "@" not in destinataire or not corps:
            messagebox.showwarning(
                "Message incomplet", "Il faut un destinataire et un texte.", parent=self
            )
            return
        self.parent.envoyer_reponse(self.adresse, destinataire, self.var_sujet.get().strip(), corps)
        self.destroy()


class DialogueServeur(tk.Toplevel):
    """Configuration de la source de courrier : API, ou IMAP direct."""

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
            "api_url": tk.StringVar(value=config.api_url),
            "domaine": tk.StringVar(value=parent.gestionnaire.domaine),
            "hote": tk.StringVar(value=config.hote),
            "port": tk.StringVar(value=str(config.port)),
            "smtp_port": tk.StringVar(value=str(config.smtp_port)),
            "utilisateur": tk.StringVar(value=config.utilisateur),
            "mot_de_passe": tk.StringVar(value=config.mot_de_passe),
            "dossier": tk.StringVar(value=config.dossier),
        }
        self.var_ssl = tk.BooleanVar(value=config.ssl)
        self.var_supprimer_serveur = tk.BooleanVar(value=config.supprimer_serveur)

        cadre = ttk.Frame(self, padding=14)
        cadre.pack(fill=tk.BOTH, expand=True)
        ttk.Label(cadre, justify=tk.LEFT, text=(
            "Deux modes possibles.\n"
            "• API : renseigne seulement son adresse. Aucun identifiant sur ce poste.\n"
            "• IMAP direct : usage personnel ; la boite catch-all entiere devient\n"
            "  accessible depuis cette machine, mot de passe enregistre en clair.\n\n"
            "Une fois enregistre, ce bouton disparait. Ctrl+Shift+S le rouvre."
        )).grid(row=0, column=0, columnspan=2, sticky=tk.W, pady=(0, 12))

        libelles = [
            ("API", "api_url"), ("Domaine", "domaine"), ("Hote IMAP", "hote"),
            ("Port IMAP", "port"), ("Port SMTP", "smtp_port"), ("Utilisateur", "utilisateur"),
            ("Mot de passe", "mot_de_passe"), ("Dossier", "dossier"),
        ]
        for i, (libelle, cle) in enumerate(libelles, start=1):
            ttk.Label(cadre, text=libelle + " :").grid(row=i, column=0, sticky=tk.W, pady=3)
            ttk.Entry(
                cadre, textvariable=self.vars[cle], width=40,
                show="•" if cle == "mot_de_passe" else "",
            ).grid(row=i, column=1, sticky=tk.EW, pady=3)
        cadre.grid_columnconfigure(1, weight=1)

        ttk.Checkbutton(cadre, text="SSL/TLS (IMAP 993, SMTP 465)", variable=self.var_ssl).grid(
            row=9, column=1, sticky=tk.W, pady=(8, 0))
        ttk.Checkbutton(
            cadre, text="Supprimer aussi les mails du serveur a l'expiration",
            variable=self.var_supprimer_serveur,
        ).grid(row=10, column=1, sticky=tk.W)

        boutons = ttk.Frame(cadre)
        boutons.grid(row=11, column=0, columnspan=2, sticky=tk.E, pady=(14, 0))
        ttk.Button(boutons, text="Tester", command=self.tester).pack(side=tk.LEFT)
        ttk.Button(boutons, text="Mode demo", command=self.mode_demo).pack(side=tk.LEFT, padx=6)
        ttk.Button(boutons, text="Enregistrer", command=self.enregistrer).pack(side=tk.LEFT)

    def _config(self) -> ConfigIMAP:
        def entier(cle: str, defaut: int) -> int:
            try:
                return int(self.vars[cle].get())
            except ValueError:
                return defaut

        return ConfigIMAP(
            hote=self.vars["hote"].get().strip(),
            port=entier("port", 993),
            smtp_port=entier("smtp_port", 465),
            utilisateur=self.vars["utilisateur"].get().strip(),
            mot_de_passe=self.vars["mot_de_passe"].get(),
            dossier=self.vars["dossier"].get().strip() or "INBOX",
            ssl=self.var_ssl.get(),
            domaine=self.vars["domaine"].get().strip().lower(),
            supprimer_serveur=self.var_supprimer_serveur.get(),
            api_url=self.vars["api_url"].get().strip(),
        )

    def _backend(self, config: ConfigIMAP) -> Backend:
        return BackendAPI(config.api_url) if config.api_url else BackendIMAP(config)

    def tester(self) -> None:
        try:
            message = self._backend(self._config()).tester()
        except Exception as err:
            messagebox.showerror("Echec", str(err), parent=self)
            return
        messagebox.showinfo("Succes", message, parent=self)

    def mode_demo(self) -> None:
        self.parent.backend = BackendDemo()
        self.parent._statut("Source : mode demonstration (aucun courrier reel).")
        self.destroy()

    def enregistrer(self) -> None:
        config = self._config()
        try:
            domaine = valider_domaine(config.domaine)
            config.domaine = domaine
            backend = self._backend(config)
        except ValueError as err:
            messagebox.showwarning("Configuration invalide", str(err), parent=self)
            return
        sauver_config(config)
        self.parent.backend = backend
        self.parent.appliquer_domaine(domaine)
        origine = config.api_url or config.hote
        self.parent._statut(f"Source : {backend.nom} — {origine} — domaine @{domaine}")
        self.parent.masquer_bouton_serveur()
        self.destroy()


def lancer() -> int:
    Application().mainloop()
    return 0
