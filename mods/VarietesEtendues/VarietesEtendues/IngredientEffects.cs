using System.Collections.Generic;
using UnityEngine;

namespace VarietesEtendues;

internal static class IngredientEffects
{
	internal static void Apply(Transform actor, NewEffects.Spec spec)
	{
		CharacterEffects.Add(actor, spec.Id);
	}

	internal static void Clear(Transform actor, string id)
	{
		CharacterEffects.Remove(actor, id);
	}

	internal static void Reconcile(Transform actor, IEnumerable<string> ids)
	{
		HashSet<string> hashSet = new HashSet<string>(ids);
		if (CharacterEffects.Has(actor, "vx_diable"))
		{
			hashSet.Add("vx_diable");
		}
		CharacterEffects.Reconcile(actor, hashSet);
	}

	internal static void Tick()
	{
	}

	internal static void ClearAll()
	{
		CharacterEffects.ClearAll();
	}
}
