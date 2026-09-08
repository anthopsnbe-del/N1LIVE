"""Coeur metier : generation, stockage et expiration des adresses jetables."""

from __future__ import annotations

import json
import os
import re
import secrets
import string
import threading
import time
from dataclasses import dataclass, field, asdict
from pathlib import Path
from typing import Callable, Iterable

DOMAIN = "dreamteam.fr"
TTL_SECONDS = 3600  # 1 heure
MAX_ACTIVE = 5      # garde-fou anti-abus

_ADJECTIFS = (
    "vif", "calme", "clair", "doux", "franc", "leger", "malin", "net",
    "rapide", "sage", "vaste", "zele", "brave", "fier", "juste",
)
_NOMS = (
    "aigle", "banc", "cedre", "delta", "ecume", "faucon", "givre", "havre",
    "ilot", "jade", "kayak", "lynx", "menthe", "nuage", "orage", "pivoine",
)

_LOCAL_RE = re.compile(r"^[a-z0-9](?:[a-z0-9._-]{0,30}[a-z0-9])?$")


def _now() -> float:
    return time.time()


def generer_local_part(style: str = "mots") -> str:
    """Retourne la partie locale d'une adresse, imprevisible (module secrets)."""
    if style == "aleatoire":
        alphabet = string.ascii_lowercase + string.digits
        return "".join(secrets.choice(alphabet) for _ in range(12))
    return "{}.{}{:03d}".format(
        secrets.choice(_ADJECTIFS), secrets.choice(_NOMS), secrets.randbelow(1000)
    )


def valider_local_part(local: str) -> str:
    local = local.strip().lower()
    if not _LOCAL_RE.match(local):
        raise ValueError(
            "Partie locale invalide : lettres/chiffres/._- uniquement, 1 a 32 caracteres."
        )
    return local


@dataclass
class Message:
    expediteur: str = ""
    sujet: str = ""
    date: str = ""
    corps: str = ""


@dataclass
class Adresse:
    """Une adresse jetable et sa duree de vie."""

    local: str
    domaine: str = DOMAIN
    cree_a: float = field(default_factory=_now)
    ttl: int = TTL_SECONDS
    messages: list = field(default_factory=list)

    @property
    def email(self) -> str:
        return f"{self.local}@{self.domaine}"

    @property
    def expire_a(self) -> float:
        return self.cree_a + self.ttl

    def secondes_restantes(self, maintenant: float | None = None) -> int:
        return max(0, int(round(self.expire_a - (maintenant or _now()))))

    def est_expiree(self, maintenant: float | None = None) -> bool:
        return (maintenant or _now()) >= self.expire_a

    def compte_a_rebours(self, maintenant: float | None = None) -> str:
        restant = self.secondes_restantes(maintenant)
        return f"{restant // 60:02d}:{restant % 60:02d}"

    def to_dict(self) -> dict:
        d = asdict(self)
        d["messages"] = [asdict(m) if not isinstance(m, dict) else m for m in self.messages]
        return d

    @classmethod
    def from_dict(cls, d: dict) -> "Adresse":
        msgs = [Message(**m) for m in d.get("messages", [])]
        return cls(
            local=d["local"],
            domaine=d.get("domaine", DOMAIN),
            cree_a=float(d.get("cree_a", _now())),
            ttl=int(d.get("ttl", TTL_SECONDS)),
            messages=msgs,
        )


def dossier_donnees() -> Path:
    """Dossier de travail (Windows : %LOCALAPPDATA%\\DreamTeamMail)."""
    base = os.environ.get("DREAMTEAM_MAIL_HOME")
    if base:
        racine = Path(base)
    elif os.name == "nt":
        racine = Path(os.environ.get("LOCALAPPDATA", Path.home())) / "DreamTeamMail"
    else:
        racine = Path.home() / ".local" / "share" / "dreamteam-mail"
    racine.mkdir(parents=True, exist_ok=True)
    return racine


def chemin_etat() -> Path:
    return dossier_donnees() / "etat.json"


def chemin_config() -> Path:
    return dossier_donnees() / "config.json"


_DOMAINE_RE = re.compile(r"^(?=.{1,253}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?"
                         r"(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$")


def valider_domaine(domaine: str) -> str:
    """Verifie qu'un nom de domaine est syntaxiquement utilisable."""
    domaine = domaine.strip().lower().rstrip(".")
    if not _DOMAINE_RE.match(domaine):
        raise ValueError(f"Nom de domaine invalide : {domaine!r}")
    return domaine


def domaine_configure(defaut: str = DOMAIN) -> str:
    """Domaine choisi par l'utilisateur (config.json), sinon DREAMTEAM_DOMAIN, sinon defaut."""
    depuis_env = os.environ.get("DREAMTEAM_DOMAIN")
    if depuis_env:
        try:
            return valider_domaine(depuis_env)
        except ValueError:
            pass
    chemin = chemin_config()
    if chemin.exists():
        try:
            brut = json.loads(chemin.read_text(encoding="utf-8")).get("domaine", "")
        except (OSError, ValueError):
            brut = ""
        if brut:
            try:
                return valider_domaine(brut)
            except ValueError:
                pass
    return defaut


class GestionnaireAdresses:
    """Cree, purge et persiste les adresses jetables. Thread-safe."""

    def __init__(
        self,
        ttl: int = TTL_SECONDS,
        max_actives: int = MAX_ACTIVE,
        domaine: str | None = None,
        fichier: Path | None = None,
        persister: bool = True,
    ) -> None:
        self.ttl = ttl
        self.max_actives = max_actives
        self.domaine = valider_domaine(domaine) if domaine else domaine_configure()
        self.fichier = fichier or chemin_etat()
        self.persister = persister
        self._adresses: dict[str, Adresse] = {}
        self._verrou = threading.RLock()
        self._charger()

    # ---------------------------------------------------------------- lecture
    def actives(self) -> list[Adresse]:
        with self._verrou:
            self.purger()
            return sorted(self._adresses.values(), key=lambda a: a.cree_a)

    def obtenir(self, email: str) -> Adresse | None:
        with self._verrou:
            return self._adresses.get(email.lower())

    # ---------------------------------------------------------------- ecriture
    def creer(self, local: str | None = None, style: str = "mots") -> Adresse:
        with self._verrou:
            self.purger()
            if len(self._adresses) >= self.max_actives:
                raise RuntimeError(
                    f"Limite atteinte : {self.max_actives} adresses actives au maximum. "
                    "Supprime-en une ou attends l'expiration."
                )
            for _ in range(50):
                part = valider_local_part(local) if local else generer_local_part(style)
                adresse = Adresse(local=part, domaine=self.domaine, ttl=self.ttl)
                if adresse.email not in self._adresses:
                    self._adresses[adresse.email] = adresse
                    self._sauver()
                    return adresse
                if local:
                    raise ValueError(f"{adresse.email} existe deja.")
            raise RuntimeError("Impossible de generer une adresse unique.")

    def supprimer(self, email: str) -> bool:
        with self._verrou:
            retire = self._adresses.pop(email.lower(), None) is not None
            if retire:
                self._sauver()
            return retire

    def tout_supprimer(self) -> int:
        with self._verrou:
            n = len(self._adresses)
            self._adresses.clear()
            self._sauver()
            return n

    def ajouter_messages(self, email: str, messages: Iterable[Message]) -> int:
        with self._verrou:
            adresse = self._adresses.get(email.lower())
            if adresse is None:
                return 0
            connus = {(m.date, m.sujet, m.expediteur) for m in adresse.messages}
            ajoutes = 0
            for m in messages:
                cle = (m.date, m.sujet, m.expediteur)
                if cle not in connus:
                    adresse.messages.append(m)
                    connus.add(cle)
                    ajoutes += 1
            if ajoutes:
                self._sauver()
            return ajoutes

    def purger(self, maintenant: float | None = None) -> list[str]:
        """Detruit les adresses expirees (et leurs messages). Retourne les emails purges."""
        maintenant = maintenant or _now()
        with self._verrou:
            expirees = [e for e, a in self._adresses.items() if a.est_expiree(maintenant)]
            for email in expirees:
                adresse = self._adresses.pop(email)
                adresse.messages.clear()
            if expirees:
                self._sauver()
            return expirees

    # ------------------------------------------------------------ persistance
    def _charger(self) -> None:
        if not self.persister or not self.fichier.exists():
            return
        try:
            data = json.loads(self.fichier.read_text(encoding="utf-8"))
        except (OSError, ValueError):
            return
        with self._verrou:
            for d in data.get("adresses", []):
                try:
                    adresse = Adresse.from_dict(d)
                except (KeyError, TypeError, ValueError):
                    continue
                if not adresse.est_expiree():
                    self._adresses[adresse.email] = adresse
            self.purger()

    def _sauver(self) -> None:
        if not self.persister:
            return
        charge = {"adresses": [a.to_dict() for a in self._adresses.values()]}
        temporaire = self.fichier.with_suffix(".tmp")
        try:
            temporaire.write_text(
                json.dumps(charge, ensure_ascii=False, indent=2), encoding="utf-8"
            )
            os.replace(temporaire, self.fichier)
        except OSError:
            pass


class PurgeAutomatique:
    """Thread de fond : purge les adresses expirees et notifie l'IHM."""

    def __init__(
        self,
        gestionnaire: GestionnaireAdresses,
        intervalle: float = 1.0,
        au_tick: Callable[[list[str]], None] | None = None,
    ) -> None:
        self.gestionnaire = gestionnaire
        self.intervalle = intervalle
        self.au_tick = au_tick
        self._stop = threading.Event()
        self._thread: threading.Thread | None = None

    def demarrer(self) -> None:
        if self._thread and self._thread.is_alive():
            return
        self._stop.clear()
        self._thread = threading.Thread(target=self._boucle, daemon=True)
        self._thread.start()

    def arreter(self) -> None:
        self._stop.set()
        if self._thread:
            self._thread.join(timeout=2)

    def _boucle(self) -> None:
        while not self._stop.wait(self.intervalle):
            purgees = self.gestionnaire.purger()
            if self.au_tick:
                try:
                    self.au_tick(purgees)
                except Exception:
                    pass
