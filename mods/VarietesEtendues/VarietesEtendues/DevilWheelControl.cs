using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts;

namespace VarietesEtendues;

[HarmonyPatch(typeof(PlayerInventory), "UpdateHotbarSelection")]
internal static class DevilWheelControl
{
	[HarmonyPrefix]
	private static bool Prefix()
	{
		return !GameplayEffects.IsAdjusting;
	}
}
