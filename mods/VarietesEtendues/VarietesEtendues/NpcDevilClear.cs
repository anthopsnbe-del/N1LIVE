using HarmonyLib;
using Il2CppScheduleOne.NPCs;
using Il2CppScheduleOne.Product;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.Product.ProductItemInstance), "ClearEffectsFromNPC")]
internal static class NpcDevilClear
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.Product.ProductItemInstance __instance, Il2CppScheduleOne.NPCs.NPC __0)
	{
		if (!((UnityEngine.Object)(object)__0 == (UnityEngine.Object)null))
		{
			if (DevilReplication.HasEffect(__instance))
			{
				Devil.Clear(((UnityEngine.Component)__0).transform);
			}
			string[] array = DevilReplication.EffectIds(__instance);
			foreach (string id in array)
			{
				IngredientEffects.Clear(((UnityEngine.Component)__0).transform, id);
			}
		}
	}
}
