"""Sources de courrier : demo hors-ligne, ou boite catch-all IMAP reelle."""

from __future__ import annotations

import email
import imaplib
import json as _json
import mimetypes
import smtplib
from email.message import EmailMessage
from email.utils import formatdate, make_msgid
import json
import os
import random
import secrets
from dataclasses import dataclass
from email.header import decode_header, make_header
from pathlib import Path
from urllib import error as urlerror
from urllib import parse as urlparse
from urllib import request as urlrequest

from .core import DOMAIN, Message, chemin_config, domaine_configure, valider_domaine


class Backend:
    """Interface commune."""

    nom = "backend"
    reel = False

    def relever(self, email_adresse: str, jeton: str = "") -> list[Message]:
        raise NotImplementedError

    def supprimer_du_serveur(self, email_adresse: str, jeton: str = "") -> int:
        """Efface les messages de l'alias cote serveur. 0 quand il n'y a pas de serveur."""
        return 0

    def supprimer_message(self, email_adresse: str, uid: str, jeton: str = "") -> bool:
        """Efface un seul message cote serveur."""
        return False

    peut_envoyer = False

    def envoyer(self, de: str, a: str, sujet: str, corps: str, jeton: str = "",
                pieces: list | None = None, repond_a: str = "") -> None:
        raise RuntimeError("Cette source ne permet pas d'envoyer de courrier.")


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

    def relever(self, email_adresse: str, jeton: str = "") -> list[Message]:
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
    smtp_port: int = 465
    domaine: str = DOMAIN
    api_url: str = ""  # si renseignee, on passe par l'API et pas par l'IMAP
    supprimer_serveur: bool = True  # vider la boite catch-all a l'expiration

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
        smtp_port=int(donnees.get("smtp_port", 465)),
        ssl=bool(donnees.get("ssl", True)),
        domaine=domaine_configure(),
        supprimer_serveur=bool(donnees.get("supprimer_serveur", True)),
        api_url=str(donnees.get("api_url", "")),
    )
    depuis_env = os.environ.get("DREAMTEAM_IMAP_PASSWORD")
    if depuis_env:
        config.mot_de_passe = depuis_env
    return config


def sauver_config(config: ConfigIMAP, avec_mot_de_passe: bool = True) -> Path:
    """Ecrit la config.

    Le mot de passe est conserve par defaut pour que le reglage tienne « a vie » :
    il est alors en clair dans config.json, lisible par qui accede au compte
    Windows. Passer avec_mot_de_passe=False pour ne rien ecrire et utiliser la
    variable d'environnement DREAMTEAM_IMAP_PASSWORD.
    """
    chemin = chemin_config()
    donnees = {
        "hote": config.hote,
        "port": config.port,
        "utilisateur": config.utilisateur,
        "dossier": config.dossier,
        "ssl": config.ssl,
        "smtp_port": config.smtp_port,
        "domaine": valider_domaine(config.domaine or DOMAIN),
        "supprimer_serveur": config.supprimer_serveur,
        "api_url": config.api_url,
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

    def relever(self, email_adresse: str, jeton: str = "") -> list[Message]:
        messages: list[Message] = []
        with self._connexion() as imap:
            imap.select(self.config.dossier, readonly=True)
            critere = f'(TO "{email_adresse}")'
            statut, donnees = imap.uid("SEARCH", None, critere)
            if statut != "OK" or not donnees or not donnees[0]:
                return messages
            identifiants = donnees[0].split()[-50:]
            for ident in identifiants:
                statut, brut = imap.uid("FETCH", ident, "(RFC822)")
                if statut != "OK" or not brut or not isinstance(brut[0], tuple):
                    continue
                msg = email.message_from_bytes(brut[0][1])
                messages.append(
                    Message(
                        expediteur=_decoder(msg.get("From")),
                        sujet=_decoder(msg.get("Subject")) or "(sans objet)",
                        date=_decoder(msg.get("Date")),
                        corps=_corps_texte(msg),
                        uid=ident.decode() if isinstance(ident, bytes) else str(ident),
                        message_id=(msg.get("Message-ID") or "").strip(),
                    )
                )
        return messages

    def supprimer_du_serveur(self, email_adresse: str, jeton: str = "") -> int:
        """Supprime definitivement les messages adresses a cet alias (IMAP EXPUNGE).

        Ne touche qu'aux messages dont l'en-tete To porte l'alias jetable : les
        boites nominatives du domaine ne sont jamais concernees.
        """
        if not self.config.supprimer_serveur:
            return 0
        with self._connexion() as imap:
            statut, _ = imap.select(self.config.dossier)  # ouverture en ecriture
            if statut != "OK":
                return 0
            statut, donnees = imap.uid("SEARCH", None, f'(TO "{email_adresse}")')
            if statut != "OK" or not donnees or not donnees[0]:
                return 0
            identifiants = donnees[0].split()
            for ident in identifiants:
                imap.uid("STORE", ident, "+FLAGS", "\\Deleted")
            imap.expunge()
            return len(identifiants)

    def supprimer_message(self, email_adresse: str, uid: str, jeton: str = "") -> bool:
        """Supprime un unique message, designe par son UID serveur."""
        if not uid:
            return False
        with self._connexion() as imap:
            statut, _ = imap.select(self.config.dossier)
            if statut != "OK":
                return False
            imap.uid("STORE", uid, "+FLAGS", "\\Deleted")
            imap.expunge()
            return True

    peut_envoyer = True

    def envoyer(self, de: str, a: str, sujet: str, corps: str, jeton: str = "",
                pieces: list | None = None, repond_a: str = "") -> None:
        """Envoie une reponse via le SMTP du domaine, en signant avec l'alias.

        Le serveur peut refuser un expediteur different du compte authentifie :
        dans ce cas l'erreur SMTP est remontee telle quelle a l'utilisateur.
        """
        message = construire_message(de, a, sujet, corps, pieces, repond_a)

        cfg = self.config
        if cfg.smtp_port == 465:
            client = smtplib.SMTP_SSL(cfg.hote, cfg.smtp_port, timeout=25)
        else:
            client = smtplib.SMTP(cfg.hote, cfg.smtp_port, timeout=25)
            client.starttls()
        try:
            client.login(cfg.utilisateur, cfg.mot_de_passe)
            client.send_message(message)
        finally:
            try:
                client.quit()
            except Exception:
                pass

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


class BackendAPI(Backend):
    """Parle a l'API PHP hebergee sur le domaine.

    Le poste client ne connait aucun identifiant de messagerie : il obtient un
    alias et un jeton, qui n'ouvrent que le courrier de cet alias.
    """

    nom = "API DreamTeam (reel)"
    reel = True

    def __init__(self, url: str, delai: int = 20) -> None:
        url = url.strip()
        if not url.startswith(("http://", "https://")):
            raise ValueError("L'adresse de l'API doit commencer par https://")
        self.url = url.rstrip("/")
        self.delai = delai

    # ------------------------------------------------------------------ appels
    def _appeler(self, action: str, **champs: str) -> dict:
        donnees = urlparse.urlencode({"action": action, **champs}).encode()
        requete = urlrequest.Request(
            self.url, data=donnees,
            headers={"Content-Type": "application/x-www-form-urlencoded",
                     "Accept": "application/json",
                     "User-Agent": "DreamTeamMail"},
        )
        try:
            with urlrequest.urlopen(requete, timeout=self.delai) as reponse:
                charge = _json.loads(reponse.read().decode("utf-8", "replace"))
        except urlerror.HTTPError as err:
            detail = ""
            try:
                detail = _json.loads(err.read().decode("utf-8", "replace")).get("erreur", "")
            except Exception:
                pass
            raise RuntimeError(detail or f"Le serveur a repondu {err.code}.") from None
        except urlerror.URLError as err:
            raise RuntimeError(f"Serveur injoignable : {err.reason}") from None
        except ValueError:
            raise RuntimeError("Reponse illisible du serveur.") from None
        if isinstance(charge, dict) and charge.get("erreur"):
            raise RuntimeError(str(charge["erreur"]))
        return charge

    def tester(self) -> str:
        etat = self._appeler("etat")
        maximum = int(etat.get("duree", {}).get("maximum", 0)) // 3600
        return f"API joignable — domaine @{etat.get('domaine', '?')}, duree max {maximum} h."

    def creer_alias(self, duree: int) -> dict:
        """Demande un alias au serveur : c'est lui qui decide du nom et du delai."""
        reponse = self._appeler("creer", duree=str(int(duree)))
        for cle in ("alias", "jeton", "duree"):
            if cle not in reponse:
                raise RuntimeError("Reponse incomplete du serveur.")
        return {
            "local": str(reponse["alias"]),
            "jeton": str(reponse["jeton"]),
            "ttl": int(reponse["duree"]),
            "domaine": str(reponse.get("email", "@")).split("@")[-1],
        }

    def relever(self, email_adresse: str, jeton: str = "") -> list[Message]:
        reponse = self._appeler(
            "relever", alias=email_adresse.split("@")[0], jeton=jeton
        )
        messages = []
        for brut in reponse.get("messages", []):
            messages.append(Message(
                expediteur=str(brut.get("expediteur", "")),
                sujet=str(brut.get("sujet", "")) or "(sans objet)",
                date=str(brut.get("date", "")),
                corps=str(brut.get("corps", "")),
                uid=str(brut.get("uid", "")),
                message_id=str(brut.get("message_id", "")),
            ))
        return messages

    def supprimer_message(self, email_adresse: str, uid: str, jeton: str = "") -> bool:
        if not uid or not jeton:
            return False
        reponse = self._appeler(
            "supprimer_message", alias=email_adresse.split("@")[0], jeton=jeton, uid=uid
        )
        return bool(reponse.get("supprime"))

    peut_envoyer = True

    def envoyer(self, de: str, a: str, sujet: str, corps: str, jeton: str = "",
                pieces: list | None = None, repond_a: str = "") -> None:
        champs = {
            "alias": de.split("@")[0], "jeton": jeton,
            "destinataire": a, "sujet": sujet, "corps": corps, "repond_a": repond_a,
        }
        for i, (nom, contenu) in enumerate(encoder_pieces(pieces)):
            champs[f"piece{i}_nom"] = nom
            champs[f"piece{i}_donnees"] = contenu
        self._appeler("envoyer", **champs)

    def supprimer_du_serveur(self, email_adresse: str, jeton: str = "") -> int:
        if not jeton:
            return 0
        reponse = self._appeler(
            "supprimer", alias=email_adresse.split("@")[0], jeton=jeton
        )
        return int(reponse.get("supprimes", 0))


TAILLE_MAX_PIECES = 10 * 1024 * 1024  # 10 Mo au total : au-dela les serveurs refusent


def verifier_pieces(pieces: list | None) -> list:
    """Controle la taille cumulee des pieces jointes."""
    pieces = [Path(p) for p in (pieces or [])]
    total = 0
    for chemin in pieces:
        if not chemin.is_file():
            raise ValueError(f"Fichier introuvable : {chemin}")
        total += chemin.stat().st_size
    if total > TAILLE_MAX_PIECES:
        raise ValueError(
            f"Pieces jointes trop lourdes ({total / 1e6:.1f} Mo) : "
            f"{TAILLE_MAX_PIECES // 1024 // 1024} Mo au maximum."
        )
    return pieces


def encoder_pieces(pieces: list | None) -> list[tuple[str, str]]:
    """(nom, contenu base64) pour le transport vers l'API."""
    import base64

    return [
        (chemin.name, base64.b64encode(chemin.read_bytes()).decode())
        for chemin in verifier_pieces(pieces)
    ]


def construire_message(de: str, a: str, sujet: str, corps: str,
                       pieces: list | None = None, repond_a: str = "") -> EmailMessage:
    """Message complet : les en-tetes manquants font chuter la reputation.

    Date, Message-ID et le chainage In-Reply-To/References sont attendus par
    les filtres anti-spam ; une reponse sans eux part souvent en indesirables.
    """
    message = EmailMessage()
    message["From"] = de
    message["To"] = a
    message["Subject"] = sujet or "(sans objet)"
    message["Reply-To"] = de
    message["Date"] = formatdate(localtime=True)
    message["Message-ID"] = make_msgid(domain=de.split("@")[-1])
    if repond_a:
        message["In-Reply-To"] = repond_a
        message["References"] = repond_a
    message.set_content(corps)

    for chemin in verifier_pieces(pieces):
        type_mime, _ = mimetypes.guess_type(chemin.name)
        principal, _, sous_type = (type_mime or "application/octet-stream").partition("/")
        message.add_attachment(
            chemin.read_bytes(), maintype=principal,
            subtype=sous_type or "octet-stream", filename=chemin.name,
        )
    return message


def deja_configure(config: ConfigIMAP | None = None) -> bool:
    """Vrai des qu'une source reelle est enregistree (API ou IMAP complet)."""
    config = config or charger_config()
    return bool(config.api_url) or config.est_complete()


def backend_par_defaut() -> Backend:
    """API si elle est configuree, sinon IMAP direct, sinon mode demo."""
    config = charger_config()
    if config.api_url:
        try:
            return BackendAPI(config.api_url)
        except ValueError:
            pass
    if config.est_complete():
        try:
            return BackendIMAP(config)
        except ValueError:
            pass
    return BackendDemo()
