using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts.Health;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(PlayerHealth), "RpcLogic___TakeDamage_3505310624")]
internal static class EffectDamageResistance
{
	[HarmonyPrefix]
	private static bool Prefix(PlayerHealth __instance, ref float __0)
	{
		if ((Object)(object)__instance.Player == (Object)null)
		{
			return true;
		}
		EffectTuning effectTuning = CharacterEffects.Tuning(((Component)__instance.Player).transform);
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
