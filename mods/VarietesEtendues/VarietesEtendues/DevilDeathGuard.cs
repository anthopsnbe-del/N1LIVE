using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts.Health;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(PlayerHealth), "SendDie")]
internal static class DevilDeathGuard
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
