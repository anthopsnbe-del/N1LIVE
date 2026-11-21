using HarmonyLib;
using Il2CppScheduleOne.UI.Shop;

namespace VarietesEtendues;

[HarmonyPatch(typeof(ShopInterface), "SetIsOpen")]
internal static class DevilCounterHook
{
	[HarmonyPostfix]
	private static void Postfix(ShopInterface __instance, bool __0)
	{
		DevilShop.Counter(__instance, __0);
	}
}
