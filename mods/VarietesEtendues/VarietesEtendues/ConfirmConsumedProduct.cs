using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts;
using Il2CppScheduleOne.Product;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.PlayerScripts.Player), "ConsumeProduct")]
internal static class ConfirmConsumedProduct
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.PlayerScripts.Player __instance, Il2CppScheduleOne.Product.ProductItemInstance __0)
	{
		ActiveConsumption.Consumed(__instance, __0);
	}
}
