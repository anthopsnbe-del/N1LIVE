using System.Collections.Generic;
using UnityEngine;

namespace VarietesEtendues;

internal static class IngredientEffects
{
	internal static void Apply(UnityEngine.Transform actor, NewEffects.Spec spec)
	{
		CharacterEffects.Add(actor, spec.Id);
	}

	internal static void Clear(UnityEngine.Transform actor, string id)
	{
		CharacterEffects.Remove(actor, id);
	}

	internal static void Reconcile(UnityEngine.Transform actor, System.Collections.Generic.IEnumerable<string> ids)
	{
		System.Collections.Generic.HashSet<string> hashSet = new System.Collections.Generic.HashSet<string>(ids);
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
