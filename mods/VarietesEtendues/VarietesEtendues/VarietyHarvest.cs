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

[HarmonyPatch(typeof(WeedPlant), "GetHarvestedProduct")]
internal static class VarietyHarvest
{
	[HarmonyPrefix]
	private static void Prefix(WeedPlant __instance, out SeedDefinition? __state)
	{
		__state = null;
		SeedDefinition seedDefinition = ((Plant)__instance).SeedDefinition;
		if (!((Object)(object)seedDefinition == (Object)null) && Crops.Seeds.ContainsKey(((BaseItemDefinition)seedDefinition).ID) && Crops.SourceId != null)
		{
			ItemDefinition item = Registry.GetItem(Crops.SourceId);
			SeedDefinition seedDefinition2 = ((item != null) ? ((Il2CppObjectBase)item).TryCast<SeedDefinition>() : null) ?? throw new InvalidDataException("Graine native de référence absente : " + Crops.SourceId);
			__state = seedDefinition;
			((Plant)__instance).SeedDefinition = seedDefinition2;
		}
	}

	[HarmonyPostfix]
	private static void Postfix(WeedPlant __instance, SeedDefinition? __state, int __0, ref ItemInstance __result)
	{
		//IL_0066: Unknown result type (might be due to invalid IL or missing references)
		//IL_00aa: Unknown result type (might be due to invalid IL or missing references)
		SeedDefinition __state2 = __state;
		if ((Object)(object)__state2 == (Object)null)
		{
			return;
		}
		((Plant)__instance).SeedDefinition = __state2;
		Crops.Variety variety = Crops.Varieties.First((Crops.Variety v) => Crops.SeedId(v) == ((BaseItemDefinition)__state2).ID);
		ItemInstance obj = __result;
		EQuality? obj2;
		if (obj == null)
		{
			obj2 = null;
		}
		else
		{
			QualityItemInstance obj3 = ((Il2CppObjectBase)obj).TryCast<QualityItemInstance>();
			obj2 = ((obj3 != null) ? new EQuality?(obj3.Quality) : null);
		}
		EQuality? val = obj2;
		__result = ((ItemDefinition)((Il2CppObjectBase)Registry.GetItem("vx_" + variety.Key)).Cast<WeedDefinition>()).GetDefaultInstance(__0);
		if (val.HasValue)
		{
			QualityItemInstance obj4 = ((Il2CppObjectBase)__result).TryCast<QualityItemInstance>();
			if (obj4 != null)
			{
				obj4.SetQuality(val.Value);
			}
		}
	}

	[HarmonyFinalizer]
	private static void Finalizer(WeedPlant __instance, SeedDefinition? __state)
	{
		if ((Object)(object)__state != (Object)null)
		{
			((Plant)__instance).SeedDefinition = __state;
		}
	}
}
