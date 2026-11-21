using System.Linq;
using HarmonyLib;
using Il2CppScheduleOne.NPCs;
using Il2CppScheduleOne.Product;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.Product.ProductItemInstance), "ApplyEffectsToNPC")]
internal static class NpcDevilApply
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.Product.ProductItemInstance __instance, Il2CppScheduleOne.NPCs.NPC __0)
	{
		if ((UnityEngine.Object)(object)__0 == (UnityEngine.Object)null)
		{
			return;
		}
		if (DevilReplication.HasEffect(__instance))
		{
			Devil.Apply(((UnityEngine.Component)__0).transform);
		}
		string[] source = DevilReplication.EffectIds(__instance);
		NewEffects.Spec[] all = NewEffects.All;
		foreach (NewEffects.Spec spec in all)
		{
			if (source.Contains(spec.Id))
			{
				IngredientEffects.Apply(((UnityEngine.Component)__0).transform, spec);
			}
		}
	}
}
