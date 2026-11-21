using HarmonyLib;
using Il2CppScheduleOne.Growing;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Plant), "UpdateVisuals")]
internal static class VarietyGrowthVisuals
{
	[HarmonyPostfix]
	private static void Postfix(Plant __instance)
	{
		Crops.RefreshPlant(__instance);
	}
}
