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
	internal static bool HasEffect(ProductItemInstance? product)
	{
		object obj;
		if (product == null)
		{
			obj = null;
		}
		else
		{
			ItemDefinition definition = ((ItemInstance)product).Definition;
			obj = ((definition != null) ? ((Il2CppObjectBase)definition).TryCast<PropertyItemDefinition>() : null);
		}
		PropertyItemDefinition val = (PropertyItemDefinition)obj;
		if (((val != null) ? val.Properties : null) == null)
		{
			return false;
		}
		Enumerator<Effect> enumerator = val.Properties.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Effect current = enumerator.Current;
			if ((Object)(object)current != (Object)null && current.ID == "vx_diable")
			{
				return true;
			}
		}
		return false;
	}

	internal static string[] EffectIds(ProductItemInstance? product)
	{
		object obj;
		if (product == null)
		{
			obj = null;
		}
		else
		{
			ItemDefinition definition = ((ItemInstance)product).Definition;
			if (definition == null)
			{
				obj = null;
			}
			else
			{
				PropertyItemDefinition obj2 = ((Il2CppObjectBase)definition).TryCast<PropertyItemDefinition>();
				obj = ((obj2 != null) ? obj2.Properties : null);
			}
		}
		List<Effect> val = (List<Effect>)obj;
		if (val == null)
		{
			return Array.Empty<string>();
		}
		List<string> list = new List<string>();
		Enumerator<Effect> enumerator = val.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Effect current = enumerator.Current;
			if ((Object)(object)current != (Object)null && NewEffects.IsCustom(current.ID))
			{
				list.Add(current.ID);
			}
		}
		return list.ToArray();
	}

	internal static void ReconcilePlayers()
	{
		if (Player.PlayerList == null)
		{
			return;
		}
		Enumerator<Player> enumerator = Player.PlayerList.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Player current = enumerator.Current;
			if (!((Object)(object)current == (Object)null))
			{
				string[] array = ActiveConsumption.PlayerIds(current);
				if (Array.IndexOf(array, "vx_diable") >= 0)
				{
					Devil.Apply(((Component)current).transform);
				}
				else
				{
					Devil.Clear(((Component)current).transform);
				}
				IngredientEffects.Reconcile(((Component)current).transform, array);
			}
		}
	}
}
