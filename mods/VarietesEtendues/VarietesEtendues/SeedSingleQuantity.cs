using HarmonyLib;
using Il2CppScheduleOne.Core.Items.Framework;
using Il2CppScheduleOne.UI.Items;
using Il2CppTMPro;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.UI.Items.ItemUI), "SetDisplayedQuantity")]
internal static class SeedSingleQuantity
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.UI.Items.ItemUI __instance, int __0)
	{
		if (__instance.itemInstance != null && !((UnityEngine.Object)(object)__instance.QuantityLabel == (UnityEngine.Object)null) && Crops.Seeds.ContainsKey(((Il2CppScheduleOne.Core.Items.Framework.BaseItemInstance)__instance.itemInstance).ID) && __0 == 1)
		{
			((UnityEngine.Component)__instance.QuantityLabel).gameObject.SetActive(true);
			((UnityEngine.Behaviour)__instance.QuantityLabel).enabled = true;
			((Il2CppTMPro.TMP_Text)__instance.QuantityLabel).text = "1×";
		}
	}
}
