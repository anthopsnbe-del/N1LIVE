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


class TestBackendAPI(unittest.TestCase):
    """Le client API, avec un faux transport : aucun appel reseau."""

    def backend(self, reponse):
        from dreamteam_mail.backends import BackendAPI

        b = BackendAPI("https://asylum-games.fr/api/")
        self.appels = []

        def faux(action, **champs):
            self.appels.append((action, champs))
            return reponse

        b._appeler = faux
        return b

    def test_url_normalisee_et_schema_impose(self):
        from dreamteam_mail.backends import BackendAPI

        self.assertEqual(BackendAPI("https://x.fr/api/").url, "https://x.fr/api")
        for mauvais in ("x.fr/api", "ftp://x.fr", ""):
            with self.assertRaises(ValueError):
                BackendAPI(mauvais)

    def test_creer_alias(self):
        b = self.backend({"alias": "vif.nuage042", "jeton": "a" * 32,
                          "duree": 900, "email": "vif.nuage042@asylum-games.fr"})
        infos = b.creer_alias(900)
        self.assertEqual(infos, {"local": "vif.nuage042", "jeton": "a" * 32,
                                 "ttl": 900, "domaine": "asylum-games.fr"})
        self.assertEqual(self.appels, [("creer", {"duree": "900"})])

    def test_creer_alias_reponse_incomplete(self):
        with self.assertRaises(RuntimeError):
            self.backend({"alias": "x"}).creer_alias(900)

    def test_relever_envoie_alias_et_jeton(self):
        b = self.backend({"messages": [
            {"expediteur": "a@b.fr", "sujet": "", "date": "d", "corps": "c"}
        ]})
        messages = b.relever("vif.nuage042@asylum-games.fr", "b" * 32)
        self.assertEqual(self.appels,
                         [("relever", {"alias": "vif.nuage042", "jeton": "b" * 32})])
        self.assertEqual(messages[0].sujet, "(sans objet)")
        self.assertFalse(messages[0].lu)

    def test_supprimer_sans_jeton_ne_fait_rien(self):
        b = self.backend({"supprimes": 3})
        self.assertEqual(b.supprimer_du_serveur("x@asylum-games.fr", ""), 0)
        self.assertEqual(self.appels, [])

    def test_supprimer_avec_jeton(self):
        b = self.backend({"supprimes": 3})
        self.assertEqual(b.supprimer_du_serveur("vif.nuage042@asylum-games.fr", "c" * 32), 3)
        self.assertEqual(self.appels,
                         [("supprimer", {"alias": "vif.nuage042", "jeton": "c" * 32})])

    def test_backend_par_defaut_prefere_l_api(self):
        import tempfile

        from dreamteam_mail.backends import BackendAPI, ConfigIMAP, backend_par_defaut, sauver_config

        with tempfile.TemporaryDirectory() as dossier:
            with mock.patch.dict("os.environ", {"DREAMTEAM_MAIL_HOME": dossier}):
                sauver_config(ConfigIMAP(
                    hote="mail.asylum-games.fr", utilisateur="catchall@asylum-games.fr",
                    mot_de_passe="p", domaine="asylum-games.fr",
                    api_url="https://asylum-games.fr/api/",
                ))
                self.assertIsInstance(backend_par_defaut(), BackendAPI)
