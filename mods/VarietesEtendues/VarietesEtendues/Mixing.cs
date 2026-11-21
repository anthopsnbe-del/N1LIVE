using System;
using System.Collections.Generic;
using System.IO;
using HarmonyLib;
using Il2CppScheduleOne.Effects;
using Il2CppSystem.Collections.Generic;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.Effects.EffectMixCalculator), "MixProperties")]
internal static class Mixing
{
	/// <summary>
	/// Nombre maximum d'effets par produit. 8 dans le jeu de base ; configurable
	/// ([VarietesEtendues] MaxEffects) pour les mods qui relèvent cette limite.
	/// </summary>
	internal static int MaxEffects => Math.Max(8, Settings.MaxEffects);

	internal static readonly System.Collections.Generic.Dictionary<string, Entry> Ingredients = new System.Collections.Generic.Dictionary<string, Entry>();

	private static readonly System.Collections.Generic.Dictionary<string, Il2CppScheduleOne.Effects.Effect> NativeEffects = new System.Collections.Generic.Dictionary<string, Il2CppScheduleOne.Effects.Effect>(System.StringComparer.OrdinalIgnoreCase);

	internal static Il2CppScheduleOne.Effects.Effect Native(string id)
	{
		if (NativeEffects.TryGetValue(id, out Il2CppScheduleOne.Effects.Effect value) && (UnityEngine.Object)(object)value != (UnityEngine.Object)null)
		{
			return value;
		}
		for (int i = 1; i <= 5; i++)
		{
			foreach (Il2CppScheduleOne.Effects.Effect item in UnityEngine.Resources.LoadAll<Il2CppScheduleOne.Effects.Effect>("Properties/Tier" + i))
			{
				if ((UnityEngine.Object)(object)item != (UnityEngine.Object)null && (string.Equals(item.ID, id, System.StringComparison.OrdinalIgnoreCase) || string.Equals(((UnityEngine.Object)item).name, id, System.StringComparison.OrdinalIgnoreCase)))
				{
					NativeEffects[id] = item;
					return item;
				}
			}
		}
		foreach (Il2CppScheduleOne.Effects.Effect item2 in UnityEngine.Resources.FindObjectsOfTypeAll<Il2CppScheduleOne.Effects.Effect>())
		{
			if ((UnityEngine.Object)(object)item2 != (UnityEngine.Object)null && (string.Equals(item2.ID, id, System.StringComparison.OrdinalIgnoreCase) || string.Equals(((UnityEngine.Object)item2).name, id, System.StringComparison.OrdinalIgnoreCase)))
			{
				NativeEffects[id] = item2;
				return item2;
			}
		}
		throw new InvalidDataException("Effet natif introuvable : " + id);
	}

	// Priorité basse (au lieu de 800/First) : les préfixes des autres mods de mélange
	// (ex. Mode 3X, Frozen) s'exécutent avant le nôtre au lieu d'être court-circuités.
	[HarmonyPrefix]
	[HarmonyPriority(200)]
	private static bool Prefix(ref Il2CppScheduleOne.Effects.Effect newProperty, Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> existingProperties, ref Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> __result)
	{
		if ((UnityEngine.Object)(object)newProperty == (UnityEngine.Object)null || existingProperties == null || !Ingredients.TryGetValue(newProperty.ID, out Entry value))
		{
			return true;
		}
		Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> val = new Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect>();
		System.Collections.Generic.HashSet<string> hashSet = new System.Collections.Generic.HashSet<string>(System.StringComparer.OrdinalIgnoreCase);
		var enumerator = existingProperties.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Il2CppScheduleOne.Effects.Effect current = enumerator.Current;
			if ((UnityEngine.Object)(object)current == (UnityEngine.Object)null)
			{
				continue;
			}
			Il2CppScheduleOne.Effects.Effect val2 = current;
			string[][] rules = value.Rules;
			foreach (string[] array in rules)
			{
				if (string.Equals(current.ID, Native(Mod.Resolve(array[0]).ID).ID, System.StringComparison.OrdinalIgnoreCase))
				{
					val2 = Native(Mod.Resolve(array[1]).ID);
					break;
				}
			}
			if (hashSet.Add(val2.ID) && val.Count < MaxEffects)
			{
				val.Add(val2);
			}
		}
		if (hashSet.Add(newProperty.ID) && val.Count < MaxEffects)
		{
			val.Add(newProperty);
		}
		__result = val;
		return false;
	}

	[HarmonyPostfix]
	private static void PreserveNewEffects(Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> existingProperties, ref Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> __result)
	{
		if (__result == null || existingProperties == null)
		{
			return;
		}
		var enumerator = existingProperties.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Il2CppScheduleOne.Effects.Effect current = enumerator.Current;
			if ((UnityEngine.Object)(object)current == (UnityEngine.Object)null || !NewEffects.IsCustom(current.ID))
			{
				continue;
			}
			bool flag = false;
			var enumerator2 = __result.GetEnumerator();
			while (enumerator2.MoveNext())
			{
				if ((UnityEngine.Object)(object)enumerator2.Current != (UnityEngine.Object)null && enumerator2.Current.ID == current.ID)
				{
					flag = true;
					break;
				}
			}
			if (flag)
			{
				continue;
			}
			if (__result.Count >= System.Math.Max(MaxEffects, existingProperties.Count))
			{
				int num = -1;
				for (int num2 = __result.Count - 1; num2 >= 0; num2--)
				{
					if ((UnityEngine.Object)(object)__result[num2] == (UnityEngine.Object)null || !NewEffects.IsCustom(__result[num2].ID))
					{
						num = num2;
						break;
					}
				}
				if (num < 0)
				{
					continue;
				}
				__result.RemoveAt(num);
			}
			__result.Add(current);
		}
	}
}
