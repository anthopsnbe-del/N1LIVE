using HarmonyLib;
using Il2CppScheduleOne.NPCs;
using Il2CppScheduleOne.Product;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(ProductItemInstance), "ClearEffectsFromNPC")]
internal static class NpcDevilClear
{
	[HarmonyPostfix]
	private static void Postfix(ProductItemInstance __instance, NPC __0)
	{
		if (!((Object)(object)__0 == (Object)null))
		{
			if (DevilReplication.HasEffect(__instance))
			{
				Devil.Clear(((Component)__0).transform);
			}
			string[] array = DevilReplication.EffectIds(__instance);
			foreach (string id in array)
			{
				IngredientEffects.Clear(((Component)__0).transform, id);
			}
		}
	}
}
