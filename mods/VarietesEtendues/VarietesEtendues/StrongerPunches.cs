using HarmonyLib;
using Il2CppScheduleOne.Combat;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.Combat.PunchController), "ExecuteHit")]
internal static class StrongerPunches
{
	private sealed record Values(float Min, float Max, float MinForce, float MaxForce);

	[HarmonyPrefix]
	private static void Prefix(Il2CppScheduleOne.Combat.PunchController __instance, out Values? __state)
	{
		__state = null;
		if (!((UnityEngine.Object)(object)__instance.player == (UnityEngine.Object)null) && __instance.player.IsLocalPlayer)
		{
			float strength = CharacterEffects.Tuning(((UnityEngine.Component)__instance.player).transform).Strength;
			if (!UnityEngine.Mathf.Approximately(strength, 1f))
			{
				__state = new Values(__instance.MinPunchDamage, __instance.MaxPunchDamage, __instance.MinPunchForce, __instance.MaxPunchForce);
				__instance.MinPunchDamage *= strength;
				__instance.MaxPunchDamage *= strength;
				__instance.MinPunchForce *= UnityEngine.Mathf.Sqrt(strength);
				__instance.MaxPunchForce *= UnityEngine.Mathf.Sqrt(strength);
			}
		}
	}

	[HarmonyFinalizer]
	private static void Finalizer(Il2CppScheduleOne.Combat.PunchController __instance, Values? __state)
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
