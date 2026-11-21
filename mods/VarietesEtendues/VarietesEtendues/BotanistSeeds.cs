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
			ManagementUtilities instance = Il2CppScheduleOne.DevUtilities.Singleton<ManagementUtilities>.Instance;
			if ((UnityEngine.Object)(object)instance == (UnityEngine.Object)null || instance.Seeds == null)
			{
				return;
			}
			int num = 0;
			foreach (Il2CppScheduleOne.Growing.SeedDefinition value in Crops.Seeds.Values)
			{
				if (!Contains(instance.Seeds, ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)value).ID))
				{
					instance.Seeds.Add(value);
					num++;
				}
			}
			if (num > 0)
			{
				MelonLoader.MelonLogger.Msg($"BOTANISTES : {num} graine(s) ajoutée(s) aux options des pots.");
			}
		}
		catch (System.Exception ex)
		{
			MelonLoader.MelonLogger.Warning("BOTANISTES : graines non enregistrées : " + ex.Message);
		}
	}

	internal static void AddOptions(ItemField? field)
	{
		if (field?.Options == null)
		{
			return;
		}
		foreach (Il2CppScheduleOne.Growing.SeedDefinition value in Crops.Seeds.Values)
		{
			bool flag = false;
			foreach (Il2CppScheduleOne.ItemFramework.ItemDefinition option in field.Options)
			{
				if ((UnityEngine.Object)(object)option != (UnityEngine.Object)null && ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)option).ID == ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)value).ID)
				{
					flag = true;
					break;
				}
			}
			if (!flag)
			{
				field.Options.Add((Il2CppScheduleOne.ItemFramework.ItemDefinition)(object)value);
			}
		}
	}

	private static bool Contains(Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Growing.SeedDefinition> seeds, string id)
	{
		foreach (Il2CppScheduleOne.Growing.SeedDefinition seed in seeds)
		{
			if ((UnityEngine.Object)(object)seed != (UnityEngine.Object)null && ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)seed).ID == id)
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
	private static System.Collections.Generic.IEnumerable<System.Reflection.MethodBase> TargetMethods()
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
