import sys
import unittest
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from dreamteam_mail.core import (  # noqa: E402
    ADRESSES_RESERVEES,
    DOMAIN,
    Adresse,
    GestionnaireAdresses,
    Message,
    DUREES,
    TTL_MAX_SECONDS,
    TTL_PERMANENT,
    generer_mot_de_passe,
    date_courte,
    domaine_configure,
    generer_local_part,
    valider_ttl,
    valider_domaine,
    valider_local_part,
)


class TestGeneration(unittest.TestCase):
    def test_domaine_et_format(self):
        for style in ("mots", "aleatoire"):
            local = generer_local_part(style)
            self.assertEqual(valider_local_part(local), local)
            self.assertTrue(Adresse(local=local).email.endswith(f"@{DOMAIN}"))

    def test_unicite_raisonnable(self):
        parts = {generer_local_part("aleatoire") for _ in range(500)}
        self.assertEqual(len(parts), 500)

    def test_local_part_invalide(self):
        for mauvais in ("", "a b", "-abc", "abc-", "é", "x" * 40):
            with self.assertRaises(ValueError):
                valider_local_part(mauvais)


class TestCycleDeVie(unittest.TestCase):
    def gestionnaire(self, **kw):
        kw.setdefault("persister", False)
        return GestionnaireAdresses(**kw)

    def test_ttl_par_defaut_24h(self):
        adresse = self.gestionnaire().creer()
        self.assertEqual(adresse.ttl, 86400)
        self.assertAlmostEqual(adresse.expire_a - adresse.cree_a, 86400, delta=1)
        self.assertFalse(adresse.permanente)

    def test_expiration_et_purge(self):
        g = self.gestionnaire()
        adresse = g.creer()
        futur = adresse.cree_a + 86401
        self.assertTrue(adresse.est_expiree(futur))
        self.assertEqual([a.email for a in g.purger(futur)], [adresse.email])
        # L'adresse rendue garde son jeton : le serveur peut encore etre purge.
        self.assertEqual(g.purger(futur), [])
        self.assertEqual(g.actives(), [])
        self.assertIsNone(g.obtenir(adresse.email))

    def test_pas_expiree_avant_l_heure(self):
        g = self.gestionnaire()
        adresse = g.creer()
        self.assertFalse(adresse.est_expiree(adresse.cree_a + 86399))
        self.assertEqual(g.purger(adresse.cree_a + 86399), [])

    def test_messages_effaces_a_la_purge(self):
        g = self.gestionnaire()
        adresse = g.creer()
        g.ajouter_messages(adresse.email, [Message(sujet="secret", corps="code 1234")])
        self.assertEqual(len(g.obtenir(adresse.email).messages), 1)
        g.purger(adresse.cree_a + 86401)
        self.assertEqual(adresse.messages, [])

    def test_deduplication_des_messages(self):
        g = self.gestionnaire()
        adresse = g.creer()
        msg = Message(expediteur="a@b.fr", sujet="x", date="d", corps="c")
        self.assertEqual(g.ajouter_messages(adresse.email, [msg]), 1)
        self.assertEqual(g.ajouter_messages(adresse.email, [msg]), 0)

    def test_suppression_immediate(self):
        g = self.gestionnaire()
        adresse = g.creer()
        self.assertTrue(g.supprimer(adresse.email))
        self.assertFalse(g.supprimer(adresse.email))

    def test_limite_adresses_actives(self):
        g = self.gestionnaire(max_actives=2)
        g.creer()
        g.creer()
        with self.assertRaises(RuntimeError):
            g.creer()

    def test_compte_a_rebours(self):
        adresse = Adresse(local="test", ttl=3600)
        self.assertEqual(adresse.compte_a_rebours(adresse.cree_a + 3600 - 65), "01:05")


class TestAdressesReservees(unittest.TestCase):
    def test_boites_reelles_du_domaine_protegees(self):
        for reservee in ("clips", "contact", "postmaster", "abuse", "mail_php"):
            self.assertIn(reservee, ADRESSES_RESERVEES)

    def test_creation_explicite_refusee(self):
        g = GestionnaireAdresses(persister=False)
        for reservee in ("clips", "CONTACT", " postmaster "):
            with self.assertRaises(ValueError, msg=reservee):
                g.creer(local=reservee)

    def test_generation_evite_les_reservees(self):
        # Un generateur qui ne renverrait qu'une adresse reservee doit echouer
        # plutot que de la creer.
        g = GestionnaireAdresses(persister=False)
        with mock.patch("dreamteam_mail.core.generer_local_part", return_value="contact"):
            with self.assertRaises(RuntimeError):
                g.creer()

    def test_generation_repli_sur_une_adresse_libre(self):
        g = GestionnaireAdresses(persister=False)
        tirages = iter(["clips", "contact", "vif.nuage042"])
        with mock.patch("dreamteam_mail.core.generer_local_part",
                        side_effect=lambda *a, **k: next(tirages)):
            self.assertEqual(g.creer().local, "vif.nuage042")

    def test_liste_personnalisable(self):
        g = GestionnaireAdresses(persister=False, reservees=["interdit"])
        with self.assertRaises(ValueError):
            g.creer(local="interdit")
        self.assertTrue(g.creer(local="clips").email.startswith("clips@"))


class TestAdresseAVie(unittest.TestCase):
    def gestionnaire(self):
        return GestionnaireAdresses(persister=False)

    def test_jamais_expiree(self):
        adresse = self.gestionnaire().creer(ttl=TTL_PERMANENT)
        self.assertTrue(adresse.permanente)
        self.assertFalse(adresse.est_expiree(adresse.cree_a + 86400 * 365 * 50))
        self.assertEqual(adresse.compte_a_rebours(), "a vie")
        self.assertEqual(adresse.secondes_restantes(), -1)

    def test_survit_a_la_purge(self):
        g = self.gestionnaire()
        a_vie = g.creer(ttl=TTL_PERMANENT)
        jetable = g.creer(ttl=86400)
        purgees = g.purger(jetable.cree_a + 86401)
        self.assertEqual([a.email for a in purgees], [jetable.email])
        self.assertIsNotNone(g.obtenir(a_vie.email))

    def test_mot_de_passe_genere_pour_les_adresses_a_vie(self):
        g = self.gestionnaire()
        a_vie = g.creer(ttl=TTL_PERMANENT)
        self.assertRegex(a_vie.mot_de_passe, r"^[A-Za-z2-9]{5}(-[A-Za-z2-9]{5}){3}$")
        self.assertEqual(g.creer(ttl=86400).mot_de_passe, "")

    def test_mots_de_passe_tous_differents(self):
        mots = {generer_mot_de_passe() for _ in range(200)}
        self.assertEqual(len(mots), 200)

    def test_mot_de_passe_fourni_conserve(self):
        adresse = self.gestionnaire().creer(ttl=TTL_PERMANENT, mot_de_passe="AAAAA-BBBBB")
        self.assertEqual(adresse.mot_de_passe, "AAAAA-BBBBB")

    def test_persistance_du_regime_a_vie(self):
        import tempfile

        with tempfile.TemporaryDirectory() as dossier:
            fichier = Path(dossier) / "etat.json"
            g1 = GestionnaireAdresses(fichier=fichier)
            adresse = g1.creer(ttl=TTL_PERMANENT)
            g2 = GestionnaireAdresses(fichier=fichier)
            reprise = g2.obtenir(adresse.email)
            self.assertIsNotNone(reprise)
            self.assertTrue(reprise.permanente)
            self.assertEqual(reprise.mot_de_passe, adresse.mot_de_passe)

    def test_deux_regimes_proposes(self):
        self.assertEqual(DUREES, (("24 heures", 86400), ("A vie", TTL_PERMANENT)))


class TestDateCourte(unittest.TestCase):
    def test_formats_reconnus(self):
        self.assertEqual(date_courte("09/09/2026 10:22:59"), "09/09 10:22")
        self.assertEqual(date_courte("09/09/2026 10:22"), "09/09 10:22")
        self.assertEqual(date_courte("Tue, 09 Sep 2026 07:46:00 +0200"), "09/09 07:46")

    def test_date_illisible_tronquee_sans_erreur(self):
        self.assertEqual(date_courte(""), "")
        self.assertEqual(date_courte("n'importe quoi du tout"), "n'importe quoi d")


class TestDuree(unittest.TestCase):
    def test_plafond_24h(self):
        self.assertEqual(TTL_MAX_SECONDS, 24 * 3600)
        self.assertEqual(valider_ttl(TTL_MAX_SECONDS), TTL_MAX_SECONDS)
        with self.assertRaises(ValueError):
            valider_ttl(TTL_MAX_SECONDS + 1)

    def test_plancher_et_valeurs_invalides(self):
        with self.assertRaises(ValueError):
            valider_ttl(60)
        with self.assertRaises(ValueError):
            valider_ttl("abc")

    def test_durees_proposees_toutes_valides(self):
        self.assertTrue(DUREES)
        for libelle, secondes in DUREES:
            self.assertEqual(valider_ttl(secondes), secondes, libelle)
            self.assertLessEqual(secondes, TTL_MAX_SECONDS)

    def test_zero_signifie_a_vie(self):
        self.assertEqual(valider_ttl(TTL_PERMANENT), TTL_PERMANENT)

    def test_creation_avec_duree_choisie(self):
        g = GestionnaireAdresses(persister=False)
        adresse = g.creer(ttl=6 * 3600)
        self.assertEqual(adresse.ttl, 6 * 3600)
        self.assertFalse(adresse.est_expiree(adresse.cree_a + 6 * 3600 - 1))
        self.assertTrue(adresse.est_expiree(adresse.cree_a + 6 * 3600))

    def test_creation_refuse_au_dela_de_24h(self):
        g = GestionnaireAdresses(persister=False)
        with self.assertRaises(ValueError):
            g.creer(ttl=48 * 3600)

    def test_compte_a_rebours_avec_heures(self):
        adresse = Adresse(local="test", ttl=TTL_MAX_SECONDS)
        self.assertEqual(adresse.compte_a_rebours(adresse.cree_a), "24:00:00")
        self.assertEqual(
            adresse.compte_a_rebours(adresse.cree_a + TTL_MAX_SECONDS - 3725), "1:02:05"
        )


class TestMessages(unittest.TestCase):
    def test_message_non_lu_par_defaut(self):
        self.assertFalse(Message().lu)

    def test_apercu_sur_une_ligne_tronque(self):
        msg = Message(corps="Bonjour\n\n   Anthony,   voici\tune offre.")
        self.assertEqual(msg.apercu(), "Bonjour Anthony, voici une offre.")
        self.assertEqual(len(Message(corps="a" * 500).apercu(taille=20)), 20)

    def test_expediteur_court(self):
        self.assertEqual(Message(expediteur="Jobat <no@jobat.be>").expediteur_court(), "Jobat")
        self.assertEqual(Message(expediteur="<no@jobat.be>").expediteur_court(), "no@jobat.be")
        self.assertEqual(Message(expediteur="no@jobat.be").expediteur_court(), "no@jobat.be")
        self.assertEqual(Message().expediteur_court(), "(expediteur inconnu)")

    def test_etat_lu_persiste(self):
        import tempfile

        with tempfile.TemporaryDirectory() as dossier:
            fichier = Path(dossier) / "etat.json"
            g1 = GestionnaireAdresses(fichier=fichier)
            adresse = g1.creer()
            g1.ajouter_messages(adresse.email, [Message(sujet="s", date="d")])
            g1.obtenir(adresse.email).messages[0].lu = True
            g1.sauver()

            g2 = GestionnaireAdresses(fichier=fichier)
            self.assertTrue(g2.obtenir(adresse.email).messages[0].lu)

    def test_ancien_etat_sans_champ_lu(self):
        adresse = Adresse.from_dict(
            {"local": "x", "messages": [{"sujet": "s", "corps": "c", "champ_inconnu": 1}]}
        )
        self.assertFalse(adresse.messages[0].lu)
        self.assertEqual(adresse.messages[0].sujet, "s")


class TestPersistance(unittest.TestCase):
    def test_expirees_non_rechargees(self):
        import tempfile

        with tempfile.TemporaryDirectory() as dossier:
            fichier = Path(dossier) / "etat.json"
            g1 = GestionnaireAdresses(fichier=fichier)
            vivante = g1.creer()
            perimee = g1.creer()
            g1.obtenir(perimee.email).cree_a -= 86400 * 2
            g1._sauver()

            g2 = GestionnaireAdresses(fichier=fichier)
            emails = [a.email for a in g2.actives()]
            self.assertIn(vivante.email, emails)
            self.assertNotIn(perimee.email, emails)


class TestDomaine(unittest.TestCase):
    def test_domaine_par_defaut(self):
        self.assertEqual(DOMAIN, "asylum-games.fr")
        self.assertEqual(valider_domaine(DOMAIN), DOMAIN)

    def test_normalisation(self):
        self.assertEqual(valider_domaine("  Asylum-Games.FR. "), "asylum-games.fr")

    def test_domaines_invalides(self):
        for mauvais in ("", "local", "a..b.fr", "-a.fr", "a-.fr", "a.fr-", "é.fr"):
            with self.assertRaises(ValueError, msg=mauvais):
                valider_domaine(mauvais)

    def test_domaine_personnalise_dans_les_adresses(self):
        g = GestionnaireAdresses(persister=False, domaine="autre-domaine.fr")
        self.assertTrue(g.creer().email.endswith("@autre-domaine.fr"))

    def test_domaine_lu_depuis_la_config(self):
        import json
        import os
        import tempfile

        with tempfile.TemporaryDirectory() as dossier:
            ancien = os.environ.get("DREAMTEAM_MAIL_HOME")
            os.environ["DREAMTEAM_MAIL_HOME"] = dossier
            try:
                (Path(dossier) / "config.json").write_text(
                    json.dumps({"domaine": "autre-domaine.fr"}), encoding="utf-8"
                )
                self.assertEqual(domaine_configure(), "autre-domaine.fr")
                g = GestionnaireAdresses(persister=False)
                self.assertEqual(g.domaine, "autre-domaine.fr")
                (Path(dossier) / "config.json").write_text(
                    json.dumps({"domaine": "pas valide"}), encoding="utf-8"
                )
                self.assertEqual(domaine_configure(), DOMAIN)
            finally:
                if ancien is None:
                    os.environ.pop("DREAMTEAM_MAIL_HOME", None)
                else:
                    os.environ["DREAMTEAM_MAIL_HOME"] = ancien


if __name__ == "__main__":
    unittest.main()
