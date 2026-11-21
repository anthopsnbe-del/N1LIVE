using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts;
using Il2CppScheduleOne.Product;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Player), "ConsumeProduct")]
internal static class ConfirmConsumedProduct
{
	[HarmonyPostfix]
	private static void Postfix(Player __instance, ProductItemInstance __0)
	{
		ActiveConsumption.Consumed(__instance, __0);
	}
}
