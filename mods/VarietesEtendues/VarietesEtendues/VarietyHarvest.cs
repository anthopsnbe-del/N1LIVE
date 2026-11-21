using System.IO;
using System.Linq;
using HarmonyLib;
using Il2CppInterop.Runtime.InteropTypes;
using Il2CppScheduleOne;
using Il2CppScheduleOne.Core.Items.Framework;
using Il2CppScheduleOne.Growing;
using Il2CppScheduleOne.ItemFramework;
using Il2CppScheduleOne.Product;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.Growing.WeedPlant), "GetHarvestedProduct")]
internal static class VarietyHarvest
{
	[HarmonyPrefix]
	private static void Prefix(Il2CppScheduleOne.Growing.WeedPlant __instance, out Il2CppScheduleOne.Growing.SeedDefinition? __state)
	{
		__state = null;
		Il2CppScheduleOne.Growing.SeedDefinition seedDefinition = ((Il2CppScheduleOne.Growing.Plant)__instance).SeedDefinition;
		if (!((UnityEngine.Object)(object)seedDefinition == (UnityEngine.Object)null) && Crops.Seeds.ContainsKey(((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)seedDefinition).ID) && Crops.SourceId != null)
		{
			Il2CppScheduleOne.ItemFramework.ItemDefinition item = Il2CppScheduleOne.Registry.GetItem(Crops.SourceId);
			Il2CppScheduleOne.Growing.SeedDefinition seedDefinition2 = ((item != null) ? ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)item).TryCast<Il2CppScheduleOne.Growing.SeedDefinition>() : null) ?? throw new InvalidDataException("Graine native de référence absente : " + Crops.SourceId);
			__state = seedDefinition;
			((Il2CppScheduleOne.Growing.Plant)__instance).SeedDefinition = seedDefinition2;
		}
	}

	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.Growing.WeedPlant __instance, Il2CppScheduleOne.Growing.SeedDefinition? __state, int __0, ref Il2CppScheduleOne.ItemFramework.ItemInstance __result)
	{
		Il2CppScheduleOne.Growing.SeedDefinition __state2 = __state;
		if ((UnityEngine.Object)(object)__state2 == (UnityEngine.Object)null)
		{
			return;
		}
		((Il2CppScheduleOne.Growing.Plant)__instance).SeedDefinition = __state2;
		Crops.Variety variety = Crops.Varieties.First((Crops.Variety v) => Crops.SeedId(v) == ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)__state2).ID);
		Il2CppScheduleOne.ItemFramework.ItemInstance obj = __result;
		Il2CppScheduleOne.ItemFramework.EQuality? obj2;
		if (obj == null)
		{
			obj2 = null;
		}
		else
		{
			Il2CppScheduleOne.ItemFramework.QualityItemInstance obj3 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)obj).TryCast<Il2CppScheduleOne.ItemFramework.QualityItemInstance>();
			obj2 = ((obj3 != null) ? new Il2CppScheduleOne.ItemFramework.EQuality?(obj3.Quality) : null);
		}
		Il2CppScheduleOne.ItemFramework.EQuality? val = obj2;
		__result = ((Il2CppScheduleOne.ItemFramework.ItemDefinition)((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)Il2CppScheduleOne.Registry.GetItem("vx_" + variety.Key)).Cast<Il2CppScheduleOne.Product.WeedDefinition>()).GetDefaultInstance(__0);
		if (val.HasValue)
		{
			Il2CppScheduleOne.ItemFramework.QualityItemInstance obj4 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)__result).TryCast<Il2CppScheduleOne.ItemFramework.QualityItemInstance>();
			if (obj4 != null)
			{
				obj4.SetQuality(val.Value);
			}
		}
	}

	[HarmonyFinalizer]
	private static void Finalizer(Il2CppScheduleOne.Growing.WeedPlant __instance, Il2CppScheduleOne.Growing.SeedDefinition? __state)
	{
		if ((UnityEngine.Object)(object)__state != (UnityEngine.Object)null)
		{
			((Il2CppScheduleOne.Growing.Plant)__instance).SeedDefinition = __state;
		}
	}
}
