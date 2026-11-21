using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.PlayerScripts.PlayerInventory), "UpdateHotbarSelection")]
internal static class DevilWheelControl
{
	[HarmonyPrefix]
	private static bool Prefix()
	{
		return !GameplayEffects.IsAdjusting;
	}
}
