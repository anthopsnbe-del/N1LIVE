# DreamTeam Mail — adresses jetables `@asylum-games.fr`

Application Windows (`.exe`) qui génère des adresses email temporaires en
`@asylum-games.fr` et **les détruit automatiquement au bout d'une durée choisie
(24 h maximum)**, avec leurs messages, pour limiter le phishing, le spam et la
revente d'adresses.

## À lire avant tout : ce qui est technique, ce qui est administratif

Le logiciel est complet, mais **recevoir de vrais courriels ne dépend pas du
code** : il faut que le courrier arrive réellement sur le domaine. Le domaine
par défaut est `asylum-games.fr`, celui que tu possèdes ; il reste à y activer
un catch-all (étapes ci-dessous). L'application fonctionne donc en deux modes :

| Mode | Prérequis | Ce que ça fait |
| --- | --- | --- |
| **Démo** (par défaut) | aucun | Tout fonctionne — génération, compte à rebours, auto-destruction — mais les messages affichés sont **fabriqués localement**. Aucun courrier réel. |
| **IMAP catch-all** (réel) | un domaine à toi + boîte catch-all | L'app relève une vraie boîte et n'affiche que les messages adressés à l'alias en cours. |

**Le domaine est configurable** (bouton « Serveur… », champ *Domaine*, ou
variable d'environnement `DREAMTEAM_DOMAIN`). Le défaut est `asylum-games.fr` ;
pour tout autre domaine, il faut en être propriétaire, sinon aucun courriel n'y
arrivera jamais.

### Mise en route sur `asylum-games.fr`

1. **Catch-all.** Le plus simple et gratuit : Cloudflare → ajoute
   `asylum-games.fr` → **Email → Email Routing** → active-le, ajoute les
   enregistrements MX proposés, puis **Catch-all address → Send to** une boîte
   à toi (Gmail, ou mieux une boîte dédiée). Alternatives : ImprovMX, ou une
   boîte catch-all chez ton hébergeur (OVH, Infomaniak…).
2. **Boîte IMAP dédiée.** Fais atterrir le catch-all dans une boîte séparée de
   ton courrier personnel : l'app y lit tout ce qui entre.
3. **Dans l'app** : bouton **« Serveur… »** →
   - Domaine : `asylum-games.fr`
   - Hôte IMAP / Port : ceux de la boîte de destination (ex. `imap.gmail.com` /
     `993`, avec un **mot de passe d'application**, pas ton mot de passe principal)
   - Utilisateur, Mot de passe, Dossier `INBOX`
   - **Tester**, puis **Enregistrer**.

L'app filtre côté IMAP (`SEARCH TO "alias@asylum-games.fr"`) : seuls les
messages destinés à l'alias sélectionné s'affichent.

> Changer le domaine n'affecte que les **nouvelles** adresses ; celles déjà
> actives gardent le leur jusqu'à leur expiration.

## Fonctions

- Génération d'adresses imprévisibles (module `secrets`), deux styles : `mots`
  (`vif.nuage042@asylum-games.fr`) ou `aleatoire` (12 caractères).
- **Compte à rebours** par adresse, visible dans la liste (format `h:mm:ss`
  au-delà d'une heure).
- **Auto-destruction** : un thread purge chaque seconde ; à l'expiration
  l'adresse *et* ses messages sont effacés de la mémoire et du disque.
- Suppression manuelle immédiate, ou « Tout détruire ».
- Copie automatique de l'adresse dans le presse-papier à la création.
- Limite de 5 adresses actives (garde-fou anti-abus).
- Les adresses expirées ne sont jamais rechargées au démarrage.
- Domaine paramétrable (`asylum-games.fr` par défaut).
- **Durée de vie réglable** dans la barre d'outils : 5 min, 15 min, 30 min, 1 h,
  3 h, 6 h, 12 h, **24 h maximum** (plafond imposé par le code, non contournable
  depuis l'interface).
- **Suppression côté serveur** : à l'expiration, les messages de l'alias sont
  aussi effacés de la boîte catch-all (IMAP `STORE \Deleted` + `EXPUNGE`), pour
  éviter que la boîte ne gonfle indéfiniment. Décochable dans « Serveur… ».
- Interface sombre orange/rouge à chasse fixe, dans l'esprit d'un terminal.
- **Relevé automatique** toutes les 30 s sur l'adresse sélectionnée
  (décochable dans la barre d'outils). Un relevé de fond qui échoue reste
  silencieux ; le bouton « Relever » affiche les erreurs normalement.
- **Adresses réservées** : le générateur ne peut jamais produire une boîte
  réelle du domaine (`clips`, `contact`, `postmaster`, `abuse`…), et une
  saisie manuelle de ces noms est refusée.

## Construire le `.exe`

### Option A — sur ta machine Windows

```bat
git clone <ce dépôt>
cd dreamteam-mail
build.bat
```

Résultat : `dist\DreamTeamMail.exe` (autonome, double-clic, sans console).
Prérequis : Python 3.11+ depuis python.org (tkinter est inclus).

### Option B — sans rien installer, via GitHub Actions

Le workflow `.github/workflows/build-dreamteam-mail.yml` compile l'exécutable
sur un runner Windows à chaque push. Onglet **Actions** → dernier run →
artefact **`DreamTeamMail-exe`** → tu télécharges le `.exe`.

> PyInstaller ne sait pas compiler pour Windows depuis Linux : le `.exe` doit
> être produit sous Windows, par l'une de ces deux options.

## Lancer sans compiler

```bash
python main.py
```

## Tests

```bash
python -m unittest discover -s tests -v
```

37 tests couvrent la durée de vie et son plafond de 24 h, la purge,
l'effacement des messages, la limite d'adresses, la validation du domaine, la
non-réhydratation des adresses expirées, les adresses réservées et la
suppression côté serveur (cible restreinte à l'alias, relevé en lecture seule).

## Où sont stockés les mails

Deux endroits, à ne pas confondre :

1. **Sur le PC** — `%LOCALAPPDATA%\DreamTeamMail\etat.json`, en clair :
   adresses actives et messages relevés. C'est ce que la purge efface à
   l'expiration (adresse *et* messages).
2. **Sur le serveur** — la boîte catch-all qui reçoit tout le domaine. L'app
   relève en lecture seule, puis, à l'expiration, supprime définitivement les
   messages de l'alias concerné si l'option est active (elle l'est par défaut).
   La recherche IMAP porte sur l'en-tête `To` de l'alias jetable : les boîtes
   nominatives du domaine ne sont jamais touchées.

Autres fichiers :

- `config.json` (même dossier) : hôte, port, utilisateur, dossier, domaine,
  option de suppression serveur. **Le mot de passe n'est pas écrit sur le
  disque** sauf si tu coches la case ; sinon utilise `DREAMTEAM_IMAP_PASSWORD`.
- Variables d'environnement : `DREAMTEAM_DOMAIN`, `DREAMTEAM_IMAP_PASSWORD`,
  `DREAMTEAM_MAIL_HOME` (déplacer le dossier de données).

## Usage prévu

Protéger ton adresse principale lors d'inscriptions ponctuelles. Ce n'est pas
un outil d'anonymat : le fournisseur du domaine et la boîte catch-all voient
tout le courrier reçu.
