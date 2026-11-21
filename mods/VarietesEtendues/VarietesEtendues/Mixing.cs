using System;
using System.Collections.Generic;
using System.IO;
using HarmonyLib;
using Il2CppScheduleOne.Effects;
using Il2CppSystem.Collections.Generic;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(EffectMixCalculator), "MixProperties")]
internal static class Mixing
{
	/// <summary>
	/// Nombre maximum d'effets par produit. 8 dans le jeu de base ; configurable
	/// ([VarietesEtendues] MaxEffects) pour les mods qui relèvent cette limite.
	/// </summary>
	internal static int MaxEffects => Math.Max(8, Settings.MaxEffects);

	internal static readonly Dictionary<string, Entry> Ingredients = new Dictionary<string, Entry>();

	private static readonly Dictionary<string, Effect> NativeEffects = new Dictionary<string, Effect>(StringComparer.OrdinalIgnoreCase);

	internal static Effect Native(string id)
	{
		if (NativeEffects.TryGetValue(id, out Effect value) && (Object)(object)value != (Object)null)
		{
			return value;
		}
		for (int i = 1; i <= 5; i++)
		{
			foreach (Effect item in Resources.LoadAll<Effect>("Properties/Tier" + i))
			{
				if ((Object)(object)item != (Object)null && (string.Equals(item.ID, id, StringComparison.OrdinalIgnoreCase) || string.Equals(((Object)item).name, id, StringComparison.OrdinalIgnoreCase)))
				{
					NativeEffects[id] = item;
					return item;
				}
			}
		}
		foreach (Effect item2 in Resources.FindObjectsOfTypeAll<Effect>())
		{
			if ((Object)(object)item2 != (Object)null && (string.Equals(item2.ID, id, StringComparison.OrdinalIgnoreCase) || string.Equals(((Object)item2).name, id, StringComparison.OrdinalIgnoreCase)))
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
	private static bool Prefix(ref Effect newProperty, List<Effect> existingProperties, ref List<Effect> __result)
	{
		if ((Object)(object)newProperty == (Object)null || existingProperties == null || !Ingredients.TryGetValue(newProperty.ID, out Entry value))
		{
			return true;
		}
		List<Effect> val = new List<Effect>();
		HashSet<string> hashSet = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
		Enumerator<Effect> enumerator = existingProperties.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Effect current = enumerator.Current;
			if ((Object)(object)current == (Object)null)
			{
				continue;
			}
			Effect val2 = current;
			string[][] rules = value.Rules;
			foreach (string[] array in rules)
			{
				if (string.Equals(current.ID, Native(Mod.Resolve(array[0]).ID).ID, StringComparison.OrdinalIgnoreCase))
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
	private static void PreserveNewEffects(List<Effect> existingProperties, ref List<Effect> __result)
	{
		if (__result == null || existingProperties == null)
		{
			return;
		}
		Enumerator<Effect> enumerator = existingProperties.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Effect current = enumerator.Current;
			if ((Object)(object)current == (Object)null || !NewEffects.IsCustom(current.ID))
			{
				continue;
			}
			bool flag = false;
			Enumerator<Effect> enumerator2 = __result.GetEnumerator();
			while (enumerator2.MoveNext())
			{
				if ((Object)(object)enumerator2.Current != (Object)null && enumerator2.Current.ID == current.ID)
				{
					flag = true;
					break;
				}
			}
			if (flag)
			{
				continue;
			}
			if (__result.Count >= Math.Max(MaxEffects, existingProperties.Count))
			{
				int num = -1;
				for (int num2 = __result.Count - 1; num2 >= 0; num2--)
				{
					if ((Object)(object)__result[num2] == (Object)null || !NewEffects.IsCustom(__result[num2].ID))
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
