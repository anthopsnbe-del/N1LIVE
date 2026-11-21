using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts.Health;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.PlayerScripts.Health.PlayerHealth), "get_CanTakeDamage")]
internal static class DevilCanBeDamaged
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.PlayerScripts.Health.PlayerHealth __instance, ref bool __result)
	{
		if ((UnityEngine.Object)(object)__instance.Player != (UnityEngine.Object)null && CharacterEffects.Tuning(((UnityEngine.Component)__instance.Player).transform).God)
		{
			__result = false;
		}
	}
}
