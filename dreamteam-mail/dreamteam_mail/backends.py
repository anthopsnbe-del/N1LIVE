"""Sources de courrier : demo hors-ligne, ou boite catch-all IMAP reelle."""

from __future__ import annotations

import email
import imaplib
import json
import os
import random
import secrets
from dataclasses import dataclass
from email.header import decode_header, make_header
from pathlib import Path

from .core import DOMAIN, Message, chemin_config, domaine_configure, valider_domaine


class Backend:
    """Interface commune."""

    nom = "backend"
    reel = False

    def relever(self, email_adresse: str) -> list[Message]:
        raise NotImplementedError


class BackendDemo(Backend):
    """Aucun serveur : fabrique des messages factices pour tester l'application.

    Ne recoit AUCUN courrier reel. Sert a valider l'IHM et le cycle de vie.
    """

    nom = "Demo (hors-ligne)"
    reel = False

    _MODELES = (
        ("no-reply@exemple-boutique.fr", "Confirmez votre inscription",
         "Bonjour,\n\nVotre code de confirmation est {code}.\nIl expire dans 15 minutes.\n"),
        ("newsletter@exemple-media.fr", "Votre selection de la semaine",
         "Au sommaire cette semaine : trois articles choisis pour vous.\n"),
        ("securite@exemple-service.fr", "Nouvelle connexion detectee",
         "Une connexion a ete detectee. Si ce n'etait pas vous, ignorez ce message.\n"),
    )

    def relever(self, email_adresse: str) -> list[Message]:
        if random.random() < 0.45:
            return []
        expediteur, sujet, corps = random.choice(self._MODELES)
        from datetime import datetime
        return [
            Message(
                expediteur=expediteur,
                sujet=sujet,
                date=datetime.now().strftime("%d/%m/%Y %H:%M:%S"),
                corps=corps.format(code=f"{secrets.randbelow(1000000):06d}")
                + f"\n(Message de demonstration destine a {email_adresse}.)",
            )
        ]


@dataclass
class ConfigIMAP:
    """Parametres de la boite catch-all du domaine."""

    hote: str = ""
    port: int = 993
    utilisateur: str = ""
    mot_de_passe: str = ""
    dossier: str = "INBOX"
    ssl: bool = True
    domaine: str = DOMAIN

    def est_complete(self) -> bool:
        return bool(self.hote and self.utilisateur and self.mot_de_passe)


def charger_config() -> ConfigIMAP:
    """Charge la config ; le mot de passe peut venir de DREAMTEAM_IMAP_PASSWORD."""
    chemin = chemin_config()
    donnees: dict = {}
    if chemin.exists():
        try:
            donnees = json.loads(chemin.read_text(encoding="utf-8"))
        except (OSError, ValueError):
            donnees = {}
    config = ConfigIMAP(
        hote=donnees.get("hote", ""),
        port=int(donnees.get("port", 993)),
        utilisateur=donnees.get("utilisateur", ""),
        mot_de_passe=donnees.get("mot_de_passe", ""),
        dossier=donnees.get("dossier", "INBOX"),
        ssl=bool(donnees.get("ssl", True)),
        domaine=domaine_configure(),
    )
    depuis_env = os.environ.get("DREAMTEAM_IMAP_PASSWORD")
    if depuis_env:
        config.mot_de_passe = depuis_env
    return config


def sauver_config(config: ConfigIMAP, avec_mot_de_passe: bool = False) -> Path:
    """Ecrit la config. Par defaut le mot de passe n'est PAS ecrit sur le disque."""
    chemin = chemin_config()
    donnees = {
        "hote": config.hote,
        "port": config.port,
        "utilisateur": config.utilisateur,
        "dossier": config.dossier,
        "ssl": config.ssl,
        "domaine": valider_domaine(config.domaine or DOMAIN),
    }
    if avec_mot_de_passe:
        donnees["mot_de_passe"] = config.mot_de_passe
    chemin.write_text(json.dumps(donnees, ensure_ascii=False, indent=2), encoding="utf-8")
    try:
        os.chmod(chemin, 0o600)
    except OSError:
        pass
    return chemin


def _decoder(valeur: str | None) -> str:
    if not valeur:
        return ""
    try:
        return str(make_header(decode_header(valeur)))
    except Exception:
        return valeur


def _corps_texte(msg: email.message.Message) -> str:
    if msg.is_multipart():
        for partie in msg.walk():
            if partie.get_content_type() == "text/plain" and "attachment" not in str(
                partie.get("Content-Disposition", "")
            ):
                charge = partie.get_payload(decode=True) or b""
                return charge.decode(partie.get_content_charset() or "utf-8", "replace")
        return "(Aucune partie texte lisible.)"
    charge = msg.get_payload(decode=True) or b""
    return charge.decode(msg.get_content_charset() or "utf-8", "replace")


class BackendIMAP(Backend):
    """Releve une boite catch-all et ne garde que les messages adresses a l'alias.

    Prerequis : tu possedes le domaine et tu as configure une redirection
    catch-all (*@domaine) vers une boite IMAP dont tu donnes les acces.
    """

    nom = "IMAP catch-all (reel)"
    reel = True

    def __init__(self, config: ConfigIMAP) -> None:
        if not config.est_complete():
            raise ValueError("Configuration IMAP incomplete (hote, utilisateur, mot de passe).")
        self.config = config

    def tester(self) -> str:
        with self._connexion() as imap:
            imap.select(self.config.dossier, readonly=True)
            return f"Connexion reussie a {self.config.hote} ({self.config.dossier})."

    def relever(self, email_adresse: str) -> list[Message]:
        messages: list[Message] = []
        with self._connexion() as imap:
            imap.select(self.config.dossier, readonly=True)
            critere = f'(TO "{email_adresse}")'
            statut, donnees = imap.search(None, critere)
            if statut != "OK" or not donnees or not donnees[0]:
                return messages
            identifiants = donnees[0].split()[-50:]
            for ident in identifiants:
                statut, brut = imap.fetch(ident, "(RFC822)")
                if statut != "OK" or not brut or not isinstance(brut[0], tuple):
                    continue
                msg = email.message_from_bytes(brut[0][1])
                messages.append(
                    Message(
                        expediteur=_decoder(msg.get("From")),
                        sujet=_decoder(msg.get("Subject")) or "(sans objet)",
                        date=_decoder(msg.get("Date")),
                        corps=_corps_texte(msg),
                    )
                )
        return messages

    class _Session:
        def __init__(self, imap: imaplib.IMAP4) -> None:
            self.imap = imap

        def __enter__(self) -> imaplib.IMAP4:
            return self.imap

        def __exit__(self, *exc) -> None:
            try:
                self.imap.logout()
            except Exception:
                pass

    def _connexion(self) -> "BackendIMAP._Session":
        cfg = self.config
        if cfg.ssl:
            imap = imaplib.IMAP4_SSL(cfg.hote, cfg.port)
        else:
            imap = imaplib.IMAP4(cfg.hote, cfg.port)
            imap.starttls()
        imap.login(cfg.utilisateur, cfg.mot_de_passe)
        return BackendIMAP._Session(imap)


def backend_par_defaut() -> Backend:
    """IMAP si la configuration est complete, sinon mode demo."""
    config = charger_config()
    if config.est_complete():
        try:
            return BackendIMAP(config)
        except ValueError:
            pass
    return BackendDemo()
