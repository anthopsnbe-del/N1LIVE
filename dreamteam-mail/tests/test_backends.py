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

    def uid(self, commande, *arguments):
        """Les commandes UID sont enregistrees comme leurs equivalents simples."""
        nom = commande.lower()
        if nom == "search":
            return self.search(*arguments)
        if nom == "fetch":
            return self.fetch(*arguments)
        if nom == "store":
            return self.store(*arguments)
        raise AssertionError(f"commande UID inattendue : {commande}")

    def fetch(self, ident, quoi):
        self.commandes.append(("fetch", ident, quoi))
        return "OK", [None]

    def store(self, ident, commande, drapeaux):
        ident = ident.decode() if isinstance(ident, bytes) else str(ident)
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
            [("store", "1", "+FLAGS", "\\Deleted"),
             ("store", "2", "+FLAGS", "\\Deleted"),
             ("store", "3", "+FLAGS", "\\Deleted")],
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

                config = ConfigIMAP(hote="h", utilisateur="u", mot_de_passe="p",
                                    domaine="exemple.fr", supprimer_serveur=False)
                # Par defaut le reglage tient « a vie » : le mot de passe est ecrit.
                chemin = sauver_config(config)
                self.assertEqual(json.loads(chemin.read_text())["mot_de_passe"], "p")
                self.assertFalse(charger_config().supprimer_serveur)
                # Et il reste possible de ne rien ecrire du tout.
                chemin = sauver_config(config, avec_mot_de_passe=False)
                self.assertNotIn("mot_de_passe", json.loads(chemin.read_text()))


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


class TestEnvoi(unittest.TestCase):
    def test_demo_refuse_l_envoi(self):
        from dreamteam_mail.backends import BackendDemo

        backend = BackendDemo()
        self.assertFalse(backend.peut_envoyer)
        with self.assertRaises(RuntimeError):
            backend.envoyer("a@b.fr", "c@d.fr", "sujet", "corps")

    def test_api_transmet_alias_jeton_et_message(self):
        from dreamteam_mail.backends import BackendAPI

        backend = BackendAPI("https://asylum-games.fr/api")
        appels = []
        backend._appeler = lambda action, **champs: appels.append((action, champs)) or {}
        backend.envoyer("vif.nuage042@asylum-games.fr", "cible@exemple.fr",
                        "Re: test", "Bonjour", "d" * 32)

        self.assertEqual(appels, [("envoyer", {
            "alias": "vif.nuage042", "jeton": "d" * 32,
            "destinataire": "cible@exemple.fr", "sujet": "Re: test", "corps": "Bonjour",
            "repond_a": "",
        })])
        self.assertTrue(backend.peut_envoyer)

    def test_api_transmet_les_pieces_jointes(self):
        import base64
        import tempfile

        from dreamteam_mail.backends import BackendAPI

        fichier = Path(tempfile.mkdtemp()) / "facture.pdf"
        fichier.write_bytes(b"%PDF-1.4 contenu")
        backend = BackendAPI("https://asylum-games.fr/api")
        appels = []
        backend._appeler = lambda action, **champs: appels.append(champs) or {}
        backend.envoyer("vif.nuage042@asylum-games.fr", "c@d.fr", "s", "corps",
                        "f" * 32, [fichier], "<abc@jobat.be>")
        champs = appels[0]
        self.assertEqual(champs["piece0_nom"], "facture.pdf")
        self.assertEqual(base64.b64decode(champs["piece0_donnees"]), b"%PDF-1.4 contenu")
        self.assertEqual(champs["repond_a"], "<abc@jobat.be>")


class TestDejaConfigure(unittest.TestCase):
    def test_reconnait_les_sources_reelles(self):
        from dreamteam_mail.backends import ConfigIMAP, deja_configure

        self.assertFalse(deja_configure(ConfigIMAP()))
        self.assertFalse(deja_configure(ConfigIMAP(hote="h")))  # incomplet
        self.assertTrue(deja_configure(ConfigIMAP(api_url="https://x.fr/api")))
        self.assertTrue(deja_configure(ConfigIMAP(hote="h", utilisateur="u", mot_de_passe="p")))


class TestMessageComplet(unittest.TestCase):
    def construire(self, **kw):
        from dreamteam_mail.backends import construire_message

        return construire_message("vif.nuage042@asylum-games.fr", "cible@exemple.fr",
                                  "Re: test", "Bonjour", **kw)

    def test_entetes_attendus_par_les_filtres(self):
        message = self.construire()
        for entete in ("From", "To", "Subject", "Reply-To", "Date", "Message-ID"):
            self.assertTrue(message[entete], entete)
        self.assertTrue(message["Message-ID"].endswith("@asylum-games.fr>"))

    def test_chainage_de_la_reponse(self):
        message = self.construire(repond_a="<abc@jobat.be>")
        self.assertEqual(message["In-Reply-To"], "<abc@jobat.be>")
        self.assertEqual(message["References"], "<abc@jobat.be>")

    def test_sans_chainage_pas_d_entete_vide(self):
        self.assertIsNone(self.construire()["In-Reply-To"])

    def test_sujet_vide_remplace(self):
        from dreamteam_mail.backends import construire_message

        message = construire_message("a@b.fr", "c@d.fr", "", "corps")
        self.assertEqual(message["Subject"], "(sans objet)")


class TestPiecesJointes(unittest.TestCase):
    def fichiers(self):
        import tempfile

        dossier = Path(tempfile.mkdtemp())
        (dossier / "facture.pdf").write_bytes(b"%PDF-1.4 contenu")
        (dossier / "photo.png").write_bytes(b"\x89PNG contenu")
        return dossier

    def test_types_mime_deduits(self):
        from dreamteam_mail.backends import construire_message

        dossier = self.fichiers()
        message = construire_message(
            "a@b.fr", "c@d.fr", "s", "corps",
            pieces=[dossier / "facture.pdf", dossier / "photo.png"],
        )
        types = {p.get_filename(): p.get_content_type() for p in message.iter_attachments()}
        self.assertEqual(types, {"facture.pdf": "application/pdf", "photo.png": "image/png"})

    def test_fichier_absent_refuse(self):
        from dreamteam_mail.backends import verifier_pieces

        with self.assertRaises(ValueError):
            verifier_pieces([Path("/introuvable/x.pdf")])

    def test_limite_de_taille(self):
        import tempfile

        from dreamteam_mail.backends import TAILLE_MAX_PIECES, verifier_pieces

        gros = Path(tempfile.mkdtemp()) / "gros.bin"
        gros.write_bytes(b"0" * (TAILLE_MAX_PIECES + 1))
        with self.assertRaises(ValueError) as contexte:
            verifier_pieces([gros])
        self.assertIn("10 Mo", str(contexte.exception))

    def test_encodage_pour_l_api(self):
        import base64

        from dreamteam_mail.backends import encoder_pieces

        dossier = self.fichiers()
        encodees = encoder_pieces([dossier / "facture.pdf"])
        self.assertEqual(encodees[0][0], "facture.pdf")
        self.assertEqual(base64.b64decode(encodees[0][1]), b"%PDF-1.4 contenu")

    def test_aucune_piece(self):
        from dreamteam_mail.backends import encoder_pieces, verifier_pieces

        self.assertEqual(encoder_pieces(None), [])
        self.assertEqual(verifier_pieces(None), [])


class TestSuppressionUnitaire(unittest.TestCase):
    def test_imap_supprime_un_uid(self):
        faux = FauxIMAP()
        b = backend(faux)
        self.assertTrue(b.supprimer_message("vif.nuage042@asylum-games.fr", "42"))
        self.assertIn(("store", "42", "+FLAGS", "\\Deleted"), faux.commandes)
        self.assertIn(("expunge",), faux.commandes)
        self.assertEqual(faux.dossier_ouvert, ("INBOX", False))

    def test_imap_sans_uid_ne_fait_rien(self):
        faux = FauxIMAP()
        self.assertFalse(backend(faux).supprimer_message("x@asylum-games.fr", ""))
        self.assertEqual(faux.commandes, [])

    def test_api_transmet_l_uid(self):
        from dreamteam_mail.backends import BackendAPI

        b = BackendAPI("https://asylum-games.fr/api")
        appels = []
        b._appeler = lambda action, **champs: (appels.append((action, champs)), {"supprime": True})[1]
        self.assertTrue(b.supprimer_message("vif.nuage042@asylum-games.fr", "9", "e" * 32))
        self.assertEqual(appels, [("supprimer_message",
                                   {"alias": "vif.nuage042", "jeton": "e" * 32, "uid": "9"})])

    def test_demo_ne_supprime_rien(self):
        self.assertFalse(BackendDemo().supprimer_message("x@y.fr", "1"))
