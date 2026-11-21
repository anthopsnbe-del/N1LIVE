using System.Linq;
using HarmonyLib;
using Il2CppScheduleOne.NPCs;
using Il2CppScheduleOne.Product;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(ProductItemInstance), "ApplyEffectsToNPC")]
internal static class NpcDevilApply
{
	[HarmonyPostfix]
	private static void Postfix(ProductItemInstance __instance, NPC __0)
	{
		if ((Object)(object)__0 == (Object)null)
		{
			return;
		}
		if (DevilReplication.HasEffect(__instance))
		{
			Devil.Apply(((Component)__0).transform);
		}
		string[] source = DevilReplication.EffectIds(__instance);
		NewEffects.Spec[] all = NewEffects.All;
		foreach (NewEffects.Spec spec in all)
		{
			if (source.Contains(spec.Id))
			{
				IngredientEffects.Apply(((Component)__0).transform, spec);
			}
		}
	}
}
