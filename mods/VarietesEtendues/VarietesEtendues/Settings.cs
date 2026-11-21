using MelonLoader;
using MelonLoader.Preferences;

namespace VarietesEtendues;

/// <summary>Préférences dans UserData/MelonPreferences.cfg, catégorie [VarietesEtendues].</summary>
internal static class Settings
{
	private static MelonPreferences_Entry<int>? maxEffects;

	private static MelonPreferences_Entry<bool>? diagnostics;

	private static MelonPreferences_Entry<float>? effectDuration;

	private static MelonPreferences_Entry<float>? devilSprint;

	internal static int MaxEffects => maxEffects?.Value ?? 8;

	internal static bool Diagnostics => diagnostics?.Value ?? false;

	/// <summary>Durée (secondes) des effets du mod après consommation. 120 = 2 minutes.</summary>
	internal static float EffectDuration => System.Math.Max(5f, effectDuration?.Value ?? 120f);

	/// <summary>Multiplicateur de sprint par défaut sous effet Diable.</summary>
	internal static float DevilSprint => System.Math.Clamp(devilSprint?.Value ?? 1.3f, 1f, 2f);

	internal static void Initialize()
	{
		MelonPreferences_Category category = MelonPreferences.CreateCategory("VarietesEtendues");
		maxEffects = category.CreateEntry<int>("MaxEffects", 8, "Effets max par produit (8 = jeu de base ; augmenter si un autre mod relève la limite, ex. Mode 3X)", (string)null, false, false, (ValueValidator)null, (string)null);
		effectDuration = category.CreateEntry<float>("EffectDuration", 120f, "Durée des effets du mod en secondes (120 = 2 min). L'effet s'arrête net à la fin.", (string)null, false, false, (ValueValidator)null, (string)null);
		devilSprint = category.CreateEntry<float>("DevilSprint", 1.3f, "Vitesse de sprint du Diable par défaut (1.0 à 2.0)", (string)null, false, false, (ValueValidator)null, (string)null);
		diagnostics = category.CreateEntry<bool>("Diagnostics", false, "Lance les tests de validation/régression à chaque chargement (développeur)", (string)null, false, false, (ValueValidator)null, (string)null);
	}
}
