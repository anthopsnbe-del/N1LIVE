using HarmonyLib;
using Il2CppScheduleOne.UI.Phone.Delivery;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.UI.Phone.Delivery.DeliveryShop), "Close")]
internal static class DevilDeliveryClose
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.UI.Phone.Delivery.DeliveryShop __instance)
	{
		DevilShop.Delivery(__instance, open: false);
	}
}
