using HarmonyLib;
using Il2CppScheduleOne.Core.Items.Framework;
using Il2CppScheduleOne.UI.Items;
using Il2CppTMPro;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(ItemUI), "SetDisplayedQuantity")]
internal static class SeedSingleQuantity
{
	[HarmonyPostfix]
	private static void Postfix(ItemUI __instance, int __0)
	{
		if (__instance.itemInstance != null && !((Object)(object)__instance.QuantityLabel == (Object)null) && Crops.Seeds.ContainsKey(((BaseItemInstance)__instance.itemInstance).ID) && __0 == 1)
		{
			((Component)__instance.QuantityLabel).gameObject.SetActive(true);
			((Behaviour)__instance.QuantityLabel).enabled = true;
			((TMP_Text)__instance.QuantityLabel).text = "1×";
		}
	}
}
