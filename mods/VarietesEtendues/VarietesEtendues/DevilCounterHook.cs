using HarmonyLib;
using Il2CppScheduleOne.UI.Shop;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.UI.Shop.ShopInterface), "SetIsOpen")]
internal static class DevilCounterHook
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.UI.Shop.ShopInterface __instance, bool __0)
	{
		DevilShop.Counter(__instance, __0);
	}
}
