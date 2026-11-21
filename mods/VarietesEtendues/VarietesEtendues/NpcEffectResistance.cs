using HarmonyLib;
using Il2CppScheduleOne.NPCs;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(NPCHealth), "TakeDamage")]
internal static class NpcEffectResistance
{
	[HarmonyPrefix]
	private static bool Prefix(NPCHealth __instance, ref float __0)
	{
		if ((Object)(object)__instance._npc == (Object)null)
		{
			return true;
		}
		EffectTuning effectTuning = CharacterEffects.Tuning(((Component)__instance._npc).transform);
		if (effectTuning.God)
		{
			return false;
		}
		__0 *= effectTuning.DamageTaken;
		return true;
	}
}
