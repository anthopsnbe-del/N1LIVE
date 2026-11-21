using System;
using System.Collections.Generic;
using System.Reflection;
using HarmonyLib;
using Il2CppScheduleOne.DevUtilities;
using Il2CppScheduleOne.Growing;
using Il2CppScheduleOne.ItemFramework;
using Il2CppScheduleOne.Management;
using MelonLoader;
using UnityEngine;

namespace VarietesEtendues;

/// <summary>
/// Correctif botanistes.
/// Le jeu construit la liste des graines proposées dans la configuration d'un pot
/// (PotConfiguration.Seed.Options) ET la liste utilisée par le botaniste en mode
/// « toutes variétés » à partir de ManagementUtilities.Seeds. Les graines du mod
/// étaient seulement ajoutées au Registry : elles n'apparaissaient donc ni dans le
/// menu du pot, ni dans la recherche automatique du botaniste.
/// </summary>
internal static class BotanistSeeds
{
	internal static void Register()
	{
		try
		{
			ManagementUtilities instance = Singleton<ManagementUtilities>.Instance;
			if ((Object)(object)instance == (Object)null || instance.Seeds == null)
			{
				return;
			}
			int num = 0;
			foreach (SeedDefinition value in Crops.Seeds.Values)
			{
				if (!Contains(instance.Seeds, ((BaseItemDefinition)value).ID))
				{
					instance.Seeds.Add(value);
					num++;
				}
			}
			if (num > 0)
			{
				MelonLogger.Msg($"BOTANISTES : {num} graine(s) ajoutée(s) aux options des pots.");
			}
		}
		catch (Exception ex)
		{
			MelonLogger.Warning("BOTANISTES : graines non enregistrées : " + ex.Message);
		}
	}

	internal static void AddOptions(ItemField? field)
	{
		if (field?.Options == null)
		{
			return;
		}
		foreach (SeedDefinition value in Crops.Seeds.Values)
		{
			bool flag = false;
			foreach (ItemDefinition option in field.Options)
			{
				if ((Object)(object)option != (Object)null && ((BaseItemDefinition)option).ID == ((BaseItemDefinition)value).ID)
				{
					flag = true;
					break;
				}
			}
			if (!flag)
			{
				field.Options.Add((ItemDefinition)(object)value);
			}
		}
	}

	private static bool Contains(Il2CppSystem.Collections.Generic.List<SeedDefinition> seeds, string id)
	{
		foreach (SeedDefinition seed in seeds)
		{
			if ((Object)(object)seed != (Object)null && ((BaseItemDefinition)seed).ID == id)
			{
				return true;
			}
		}
		return false;
	}
}

/// <summary>Ajoute les graines du mod aux pots créés (ou rechargés) avant OnLoadComplete.</summary>
[HarmonyPatch]
internal static class PotConfigurationSeedOptions
{
	private static IEnumerable<MethodBase> TargetMethods()
	{
		return AccessTools.GetDeclaredConstructors(typeof(PotConfiguration), (bool?)null);
	}

	[HarmonyPostfix]
	private static void Postfix(PotConfiguration __instance)
	{
		BotanistSeeds.Register();
		BotanistSeeds.AddOptions(__instance?.Seed);
	}
}
