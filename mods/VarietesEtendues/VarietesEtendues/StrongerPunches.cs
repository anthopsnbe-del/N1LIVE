using HarmonyLib;
using Il2CppScheduleOne.Combat;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(PunchController), "ExecuteHit")]
internal static class StrongerPunches
{
	private sealed record Values(float Min, float Max, float MinForce, float MaxForce);

	[HarmonyPrefix]
	private static void Prefix(PunchController __instance, out Values? __state)
	{
		__state = null;
		if (!((Object)(object)__instance.player == (Object)null) && __instance.player.IsLocalPlayer)
		{
			float strength = CharacterEffects.Tuning(((Component)__instance.player).transform).Strength;
			if (!Mathf.Approximately(strength, 1f))
			{
				__state = new Values(__instance.MinPunchDamage, __instance.MaxPunchDamage, __instance.MinPunchForce, __instance.MaxPunchForce);
				__instance.MinPunchDamage *= strength;
				__instance.MaxPunchDamage *= strength;
				__instance.MinPunchForce *= Mathf.Sqrt(strength);
				__instance.MaxPunchForce *= Mathf.Sqrt(strength);
			}
		}
	}

	[HarmonyFinalizer]
	private static void Finalizer(PunchController __instance, Values? __state)
	{
		if (!(__state == null))
		{
			__instance.MinPunchDamage = __state.Min;
			__instance.MaxPunchDamage = __state.Max;
			__instance.MinPunchForce = __state.MinForce;
			__instance.MaxPunchForce = __state.MaxForce;
		}
	}
}
