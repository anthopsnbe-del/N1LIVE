using HarmonyLib;
using Il2CppScheduleOne.UI.Phone.Delivery;

namespace VarietesEtendues;

[HarmonyPatch(typeof(DeliveryShop), "Open")]
internal static class DevilDeliveryOpen
{
	[HarmonyPostfix]
	private static void Postfix(DeliveryShop __instance)
	{
		DevilShop.Delivery(__instance, open: true);
	}
}
