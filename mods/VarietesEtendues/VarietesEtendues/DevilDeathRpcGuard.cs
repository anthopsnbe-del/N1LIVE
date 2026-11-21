using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts.Health;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(PlayerHealth), "Die")]
internal static class DevilDeathRpcGuard
{
	[HarmonyPrefix]
	private static bool Prefix(PlayerHealth __instance)
	{
		if (!((Object)(object)__instance.Player == (Object)null))
		{
			return !CharacterEffects.Tuning(((Component)__instance.Player).transform).God;
		}
		return true;
	}
}
