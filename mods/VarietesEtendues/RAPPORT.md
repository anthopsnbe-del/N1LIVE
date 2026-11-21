# VarietesEtendues.dll : analyse et correctifs

## Ce que fait le mod
Mod **MelonLoader (IL2CPP) pour Schedule I**, basé sur **S1API** :
- **19 ingrédients de mélange** (catalogue JSON embarqué), dont la **Pilule 666** (effet « Diable »), et 18 effets inédits `vx_mixer_*` avec modèles 3D procéduraux.
- **Effets de personnage** sur joueurs et PNJ : couleur de peau, yeux, cornes et fumée pour le Diable, vitesse, saut, gravité, endurance, dégâts, force et effets de vision (post-process).
- **Règles de mélange** : un patch Harmony sur `EffectMixCalculator.MixProperties` remplace la logique native quand un ingrédient du mod est ajouté.
- **3 variétés de weed** (Lemon Haze, Blueberry Dream, Dragon Kush) : graines, plantes teintées, récolte, produit et icônes.
- **PNJ fournisseur « Lucien »** avec boutique IMGUI (Comptoir 666), livraisons et messages traduits.
- Des tests de validation et de régression s'exécutent **à chaque chargement de partie**.

## Bug n° 1 : les effets ne s'arrêtent jamais (Pilule 666 : vitesse infinie)
**Cause** : les effets du mod (vitesse, saut, gravité, apparence, vision, invulnérabilité du Diable) sont calculés à partir de `Player.ConsumedProduct`. Ils restent actifs tant que ce champ est rempli. Or le jeu ne le vide presque jamais (`Player.ClearProduct`), et le mod n'avait aucun minuteur. Pour les PNJ, l'effet ne s'arrêtait que si S1API appelait le « clear ».
De plus, le sprint du Diable était à **×1,8 par défaut**, en plus du sprint du jeu, avec un plafond à ×3.

**Correctif** :
- `ActiveConsumption.cs` : chaque consommation démarre un **minuteur de 120 s** (réglable avec `EffectDuration`). Quand il arrive à 0, la liste d'effets devient vide et **tout s'arrête net** : vitesse (retirée de la pile de vitesse), saut, gravité, peau, fumée, vision et invulnérabilité. Une nouvelle consommation relance les 2 minutes. Les joueurs distants (coop) ont aussi leur minuteur.
- `CharacterEffects.cs` : les PNJ ont le même arrêt net après `EffectDuration`. Un effet expiré n'est pas réappliqué tant que le jeu n'a pas envoyé son « clear ». **Lucien** garde son apparence de Diable en permanence.
- `GameplayEffects.cs` et `EffectRules.cs` : sprint du Diable à **×1,3 par défaut** (réglable avec `DevilSprint`), avec un plafond ramené de ×3 à **×2** (Alt + molette).

## Autres bugs trouvés

| # | Gravité | Bug | Corrigé ? |
|---|---|---|---|
| 1 | Haute | **Botanistes** : les graines du mod sont ajoutées au `Registry`, mais pas à `ManagementUtilities.Seeds`. C'est de cette liste que le jeu tire les options de graines d'un pot et les graines que le botaniste cherche en mode « toutes variétés ». | Oui : `BotanistSeeds.cs` |
| 2 | Haute | **Langue** : FR/EN seulement. La détection se fait via Steam, un mod FR ou AutoTranslator, pas via la langue de Windows. | Oui : FR/EN/DE/ES, détection par la langue Windows |
| 3 | Haute | **Incompatibilité Mode 3X / Frozen** : le préfixe de mélange a la priorité 800 (passe en premier) et renvoie `false`. La méthode d'origine est donc sautée, ce qui court-circuite les transpilers et la logique des autres mods. La limite de **8 effets est codée en dur** à 3 endroits (Mixing ×2, Regression). | Partiellement : priorité basse, limite réglable `MaxEffects`. À tester avec ces mods. |
| 4 | Moyenne | `Regression.Run()` et `ValidateRuntime()` tournent en jeu normal à chaque chargement : des centaines de mélanges, des instanciations de plantes et des logs « RÉGRESSION ÉCHOUÉE » en présence d'autres mods. | Oui : désactivés sauf si `Diagnostics = true` |
| 5 | Moyenne | **Icônes des têtes de weed** : le `WeedDefinition` cloné garde l'icône OG Kush du donneur. La nouvelle icône n'est générée qu'après `OnLoadComplete` (dans `OnUpdate`, avec un réessai toutes les 3 s). Entre-temps, `PopulateProducts` a déjà découvert le produit, et l'app Produits ou les ventes affichent l'ancienne icône sans jamais se rafraîchir. De plus, l'icône est rendue depuis le `WeedVisualsSetter` du *FunctionalProduct* (la tête tenue en main) et non depuis le modèle stocké. | Non : diagnostic seulement (il faut le jeu pour tester) |
| 6 | Moyenne | **Effets du joueur** : ils ne s'appliquent que si le produit consommé **contient** l'ID de l'effet (`PlayerIds` → `EffectIds`), et seulement après la confirmation par le postfix `ConsumeProduct`. Le callback S1API `WithBehavior` est appelé *pendant* `ConsumeProduct`, avant que la session soit enregistrée : `Add()` est rejeté, puis rattrapé par `Reconcile`. Ça marche, mais c'est fragile : si un autre mod modifie `ConsumeProduct`, les effets ne s'affichent pas. | Non |
| 7 | Basse | `PreserveNewEffects` : quand la limite est atteinte, l'effet natif qui est retiré peut être celui que le joueur vient d'ajouter. | Non |
| 8 | Basse | Mixing : il n'y avait pas de vérification des `null` dans `existingProperties` et `__result`, ce qui provoque des NullReferenceException avec d'autres mods. | Oui |
| 9 | Basse | Les noms et descriptions des objets sont fixés au chargement (`OnPreLoad`). Changer de langue demande de redémarrer le jeu. | Non (normal) |
| 10 | Basse | Les noms des graines en anglais s'affichent « Seed: Lemon Haze ». | Traduit DE/ES, format conservé |

## Préférences ajoutées (`UserData/MelonPreferences.cfg`)
```
[VarietesEtendues]
Language = "Auto"     # Auto | fr | en | de | es  (Auto = langue d'affichage Windows)
MaxEffects = 8        # augmenter si un mod relève la limite d'effets (ex. Mode 3X)
Diagnostics = false   # true = tests de validation/régression au chargement
EffectDuration = 120  # durée des effets du mod en secondes (arrêt net)
DevilSprint = 1.3     # sprint du Diable par défaut (1.0 à 2.0)
```

## Fichiers modifiés
- `Localization.cs` : réécrit. 4 langues, `GetUserDefaultUILanguage()` (Windows), puis CultureInfo, puis Unity `systemLanguage`, sinon anglais.
- `Entry.cs` et `catalogue.json` : `GermanName` et `SpanishName` pour les 19 ingrédients.
- `NewEffects.cs` : noms et descriptions DE/ES des 18 effets.
- `Crops.cs`, `DevilShop.cs`, `GameplayEffects.cs`, `LucienCounter.cs`, `Mod.cs`, `SupplierUI.cs` : tous les textes en 4 langues.
- `ActiveConsumption.cs`, `CharacterEffects.cs`, `GameplayEffects.cs`, `EffectRules.cs` : durée des effets et vitesse du Diable.
- `BotanistSeeds.cs` (nouveau) : correctif des botanistes.
- `Settings.cs` (nouveau), `Mixing.cs`, `Regression.cs` : compatibilité et diagnostics.
- `VarietesEtendues.csproj` : `HintPath` vers le dossier du jeu (`-p:GameDir=...`).

## Compilation
C'est du code **décompilé (ILSpy)** puis corrigé. Il n'a **pas pu être compilé ici**, car les assemblies du jeu (`MelonLoader/Il2CppAssemblies`) sont absentes. Sur le PC où le jeu est installé :
```
dotnet build -c Release -p:GameDir="C:\...\Schedule I"
```
Des erreurs de compilation mineures, typiques d'ILSpy (casts `Il2CppArrayBase`, `(Object)obj`), peuvent apparaître. Vérifie aussi le nom exact de la DLL S1API dans `Mods/`, ainsi que les noms `ManagementUtilities.Seeds` et `PotConfiguration.Seed.Options` (Assembly-CSharp).
