using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts.Health;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.PlayerScripts.Health.PlayerHealth), "SendDie")]
internal static class DevilDeathGuard
{
	[HarmonyPrefix]
	private static bool Prefix(Il2CppScheduleOne.PlayerScripts.Health.PlayerHealth __instance)
	{
		if (!((UnityEngine.Object)(object)__instance.Player == (UnityEngine.Object)null))
		{
			return !CharacterEffects.Tuning(((UnityEngine.Component)__instance.Player).transform).God;
		}
		return true;
	}
}
