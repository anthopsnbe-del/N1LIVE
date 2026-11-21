using HarmonyLib;
using Il2CppScheduleOne.UI.Phone.Delivery;

namespace VarietesEtendues;

[HarmonyPatch(typeof(DeliveryShop), "Close")]
internal static class DevilDeliveryClose
{
	[HarmonyPostfix]
	private static void Postfix(DeliveryShop __instance)
	{
		DevilShop.Delivery(__instance, open: false);
	}
}
