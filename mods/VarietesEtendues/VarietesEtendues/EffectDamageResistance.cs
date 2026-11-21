using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts.Health;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.PlayerScripts.Health.PlayerHealth), "RpcLogic___TakeDamage_3505310624")]
internal static class EffectDamageResistance
{
	[HarmonyPrefix]
	private static bool Prefix(Il2CppScheduleOne.PlayerScripts.Health.PlayerHealth __instance, ref float __0)
	{
		if ((UnityEngine.Object)(object)__instance.Player == (UnityEngine.Object)null)
		{
			return true;
		}
		EffectTuning effectTuning = CharacterEffects.Tuning(((UnityEngine.Component)__instance.Player).transform);
		if (effectTuning.God)
		{
			return false;
		}
		__0 *= effectTuning.DamageTaken;
		if (__instance.Player.IsLocalPlayer && GameplayEffects.LandingWindow)
		{
			__0 *= effectTuning.Fall;
		}
		return true;
	}
}
