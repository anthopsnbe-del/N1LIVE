using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne.Growing;
using UnityEngine;

namespace VarietesEtendues;

internal static class SeedPresentation
{
	internal static void Apply(UnityEngine.GameObject root, Crops.Variety variety)
	{
		foreach (UnityEngine.Renderer componentsInChild in root.GetComponentsInChildren<UnityEngine.Renderer>(true))
		{
			Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppReferenceArray<UnityEngine.Material> materials = componentsInChild.materials;
			foreach (UnityEngine.Material item in (Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.Material>)(object)materials)
			{
				string[] obj = new string[5]
				{
					((UnityEngine.Object)componentsInChild).name,
					" ",
					((UnityEngine.Object)item).name,
					" ",
					null
				};
				UnityEngine.Texture mainTexture = item.mainTexture;
				obj[4] = ((mainTexture != null) ? ((UnityEngine.Object)mainTexture).name : null);
				string text = string.Concat(obj).ToLowerInvariant();
				if ((text.Contains("label") || text.Contains("etiquette") || text.Contains("ogkush") || text.Contains("og kush") || text.Contains("og_kush")) && !text.Contains("hand") && !text.Contains("skin"))
				{
					item.mainTexture = (UnityEngine.Texture)(object)Models.Texture(variety.Name.ToUpperInvariant() + " VIAL");
					item.color = UnityEngine.Color.white;
				}
			}
			componentsInChild.materials = materials;
			switch (((UnityEngine.Object)componentsInChild).name.ToLowerInvariant())
			{
			default:
			{
				UnityEngine.Transform parent = ((UnityEngine.Component)componentsInChild).transform.parent;
				if (!(((parent != null) ? ((UnityEngine.Object)parent).name : null) == "Seed"))
				{
					continue;
				}
				break;
			}
			case "seed":
			case "seedmesh":
			case "grain":
				break;
			}
			foreach (UnityEngine.Material item2 in (Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.Material>)(object)materials)
			{
				item2.color = variety.Bud;
				if (item2.HasProperty("_BaseColor"))
				{
					item2.SetColor("_BaseColor", variety.Bud);
				}
			}
		}
	}

	internal static void TintPhysicalSeed(Il2CppScheduleOne.Growing.FunctionalSeed seed, Crops.Variety variety)
	{
		object obj;
		if (!((UnityEngine.Object)(object)seed.SeedRigidbody != (UnityEngine.Object)null))
		{
			UnityEngine.Collider seedCollider = seed.SeedCollider;
			obj = ((seedCollider != null) ? ((UnityEngine.Component)seedCollider).transform : null);
		}
		else
		{
			obj = ((UnityEngine.Component)seed.SeedRigidbody).transform;
		}
		UnityEngine.Transform val = (UnityEngine.Transform)obj;
		if ((UnityEngine.Object)(object)val == (UnityEngine.Object)null)
		{
			return;
		}
		foreach (UnityEngine.Renderer componentsInChild in ((UnityEngine.Component)val).GetComponentsInChildren<UnityEngine.Renderer>(true))
		{
			foreach (UnityEngine.Material item in (Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.Material>)(object)componentsInChild.materials)
			{
				item.color = variety.Bud;
				if (item.HasProperty("_BaseColor"))
				{
					item.SetColor("_BaseColor", variety.Bud);
				}
			}
		}
	}
}
