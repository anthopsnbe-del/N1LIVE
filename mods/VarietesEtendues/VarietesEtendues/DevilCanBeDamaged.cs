using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts.Health;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(PlayerHealth), "get_CanTakeDamage")]
internal static class DevilCanBeDamaged
{
	[HarmonyPostfix]
	private static void Postfix(PlayerHealth __instance, ref bool __result)
	{
		if ((Object)(object)__instance.Player != (Object)null && CharacterEffects.Tuning(((Component)__instance.Player).transform).God)
		{
			__result = false;
		}
	}
}
