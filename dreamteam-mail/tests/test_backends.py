import sys
import unittest
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from dreamteam_mail.backends import (  # noqa: E402
    BackendDemo,
    BackendIMAP,
    ConfigIMAP,
)


class FauxIMAP:
    """Enregistre les commandes IMAP recues, sans reseau."""

    def __init__(self, identifiants=b"1 2 3"):
        self.identifiants = identifiants
        self.commandes = []
        self.dossier_ouvert = None

    def select(self, dossier, readonly=False):
        self.dossier_ouvert = (dossier, readonly)
        self.commandes.append(("select", dossier, readonly))
        return "OK", [b"3"]

    def search(self, charset, critere):
        self.commandes.append(("search", critere))
        return "OK", [self.identifiants]

    def store(self, ident, commande, drapeaux):
        self.commandes.append(("store", ident, commande, drapeaux))
        return "OK", [b""]

    def expunge(self):
        self.commandes.append(("expunge",))
        return "OK", [b""]

    def logout(self):
        self.commandes.append(("logout",))


def backend(faux, **kw):
    config = ConfigIMAP(
        hote="mail.exemple.fr", utilisateur="catchall@exemple.fr",
        mot_de_passe="x", domaine="exemple.fr", **kw
    )
    b = BackendIMAP(config)
    b._connexion = lambda: BackendIMAP._Session(faux)
    return b


class TestSuppressionServeur(unittest.TestCase):
    def test_supprime_et_expunge(self):
        faux = FauxIMAP()
        supprimes = backend(faux).supprimer_du_serveur("jetable@exemple.fr")
        self.assertEqual(supprimes, 3)
        self.assertIn(("expunge",), faux.commandes)
        self.assertEqual(
            [c for c in faux.commandes if c[0] == "store"],
            [("store", b"1", "+FLAGS", "\\Deleted"),
             ("store", b"2", "+FLAGS", "\\Deleted"),
             ("store", b"3", "+FLAGS", "\\Deleted")],
        )

    def test_ne_cible_que_l_alias(self):
        faux = FauxIMAP()
        backend(faux).supprimer_du_serveur("jetable@exemple.fr")
        recherches = [c for c in faux.commandes if c[0] == "search"]
        self.assertEqual(recherches, [("search", '(TO "jetable@exemple.fr")')])

    def test_ouverture_en_ecriture(self):
        faux = FauxIMAP()
        backend(faux).supprimer_du_serveur("jetable@exemple.fr")
        self.assertEqual(faux.dossier_ouvert, ("INBOX", False))

    def test_option_desactivee_ne_supprime_rien(self):
        faux = FauxIMAP()
        self.assertEqual(
            backend(faux, supprimer_serveur=False).supprimer_du_serveur("j@exemple.fr"), 0
        )
        self.assertEqual(faux.commandes, [])

    def test_boite_vide(self):
        faux = FauxIMAP(identifiants=b"")
        self.assertEqual(backend(faux).supprimer_du_serveur("j@exemple.fr"), 0)
        self.assertNotIn(("expunge",), faux.commandes)

    def test_backend_demo_ne_supprime_rien(self):
        self.assertEqual(BackendDemo().supprimer_du_serveur("j@exemple.fr"), 0)

    def test_releve_reste_en_lecture_seule(self):
        faux = FauxIMAP(identifiants=b"")
        backend(faux).relever("jetable@exemple.fr")
        self.assertEqual(faux.dossier_ouvert, ("INBOX", True))


class TestConfig(unittest.TestCase):
    def test_valeur_par_defaut_activee(self):
        self.assertTrue(ConfigIMAP().supprimer_serveur)

    def test_aller_retour_config(self):
        import json
        import tempfile

        with tempfile.TemporaryDirectory() as dossier:
            with mock.patch.dict("os.environ", {"DREAMTEAM_MAIL_HOME": dossier}):
                from dreamteam_mail.backends import charger_config, sauver_config

                chemin = sauver_config(
                    ConfigIMAP(hote="h", utilisateur="u", mot_de_passe="p",
                               domaine="exemple.fr", supprimer_serveur=False)
                )
                self.assertNotIn("mot_de_passe", json.loads(chemin.read_text()))
                self.assertFalse(charger_config().supprimer_serveur)


if __name__ == "__main__":
    unittest.main()
