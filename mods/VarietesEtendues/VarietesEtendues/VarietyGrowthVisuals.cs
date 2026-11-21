using HarmonyLib;
using Il2CppScheduleOne.Growing;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.Growing.Plant), "UpdateVisuals")]
internal static class VarietyGrowthVisuals
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.Growing.Plant __instance)
	{
		Crops.RefreshPlant(__instance);
	}
}
