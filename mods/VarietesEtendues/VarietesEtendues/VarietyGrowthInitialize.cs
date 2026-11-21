using HarmonyLib;
using Il2CppScheduleOne.Growing;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Plant), "Initialize")]
internal static class VarietyGrowthInitialize
{
	[HarmonyPostfix]
	private static void Postfix(Plant __instance)
	{
		Crops.RefreshPlant(__instance);
	}
}
