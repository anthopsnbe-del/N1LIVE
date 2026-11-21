using HarmonyLib;
using Il2CppScheduleOne.NPCs;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.NPCs.NPCHealth), "TakeDamage")]
internal static class NpcEffectResistance
{
	[HarmonyPrefix]
	private static bool Prefix(Il2CppScheduleOne.NPCs.NPCHealth __instance, ref float __0)
	{
		if ((UnityEngine.Object)(object)__instance._npc == (UnityEngine.Object)null)
		{
			return true;
		}
		EffectTuning effectTuning = CharacterEffects.Tuning(((UnityEngine.Component)__instance._npc).transform);
		if (effectTuning.God)
		{
			return false;
		}
		__0 *= effectTuning.DamageTaken;
		return true;
	}
}
