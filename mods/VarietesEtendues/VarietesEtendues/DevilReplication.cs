using System;
using System.Collections.Generic;
using Il2CppInterop.Runtime.InteropTypes;
using Il2CppScheduleOne.Effects;
using Il2CppScheduleOne.ItemFramework;
using Il2CppScheduleOne.PlayerScripts;
using Il2CppScheduleOne.Product;
using Il2CppSystem.Collections.Generic;
using UnityEngine;

namespace VarietesEtendues;

internal static class DevilReplication
{
	internal static bool HasEffect(Il2CppScheduleOne.Product.ProductItemInstance? product)
	{
		object obj;
		if (product == null)
		{
			obj = null;
		}
		else
		{
			Il2CppScheduleOne.ItemFramework.ItemDefinition definition = ((Il2CppScheduleOne.ItemFramework.ItemInstance)product).Definition;
			obj = ((definition != null) ? ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)definition).TryCast<Il2CppScheduleOne.Product.PropertyItemDefinition>() : null);
		}
		Il2CppScheduleOne.Product.PropertyItemDefinition val = (Il2CppScheduleOne.Product.PropertyItemDefinition)obj;
		if (((val != null) ? val.Properties : null) == null)
		{
			return false;
		}
		var enumerator = val.Properties.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Il2CppScheduleOne.Effects.Effect current = enumerator.Current;
			if ((UnityEngine.Object)(object)current != (UnityEngine.Object)null && current.ID == "vx_diable")
			{
				return true;
			}
		}
		return false;
	}

	internal static string[] EffectIds(Il2CppScheduleOne.Product.ProductItemInstance? product)
	{
		object obj;
		if (product == null)
		{
			obj = null;
		}
		else
		{
			Il2CppScheduleOne.ItemFramework.ItemDefinition definition = ((Il2CppScheduleOne.ItemFramework.ItemInstance)product).Definition;
			if (definition == null)
			{
				obj = null;
			}
			else
			{
				Il2CppScheduleOne.Product.PropertyItemDefinition obj2 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)definition).TryCast<Il2CppScheduleOne.Product.PropertyItemDefinition>();
				obj = ((obj2 != null) ? obj2.Properties : null);
			}
		}
		Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> val = (Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect>)obj;
		if (val == null)
		{
			return System.Array.Empty<string>();
		}
		System.Collections.Generic.List<string> list = new System.Collections.Generic.List<string>();
		var enumerator = val.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Il2CppScheduleOne.Effects.Effect current = enumerator.Current;
			if ((UnityEngine.Object)(object)current != (UnityEngine.Object)null && NewEffects.IsCustom(current.ID))
			{
				list.Add(current.ID);
			}
		}
		return list.ToArray();
	}

	internal static void ReconcilePlayers()
	{
		if (Il2CppScheduleOne.PlayerScripts.Player.PlayerList == null)
		{
			return;
		}
		var enumerator = Il2CppScheduleOne.PlayerScripts.Player.PlayerList.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Il2CppScheduleOne.PlayerScripts.Player current = enumerator.Current;
			if (!((UnityEngine.Object)(object)current == (UnityEngine.Object)null))
			{
				string[] array = ActiveConsumption.PlayerIds(current);
				if (System.Array.IndexOf(array, "vx_diable") >= 0)
				{
					Devil.Apply(((UnityEngine.Component)current).transform);
				}
				else
				{
					Devil.Clear(((UnityEngine.Component)current).transform);
				}
				IngredientEffects.Reconcile(((UnityEngine.Component)current).transform, array);
			}
		}
	}
}
