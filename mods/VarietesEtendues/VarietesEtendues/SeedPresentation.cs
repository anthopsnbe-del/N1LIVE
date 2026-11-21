using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne.Growing;
using UnityEngine;

namespace VarietesEtendues;

internal static class SeedPresentation
{
	internal static void Apply(GameObject root, Crops.Variety variety)
	{
		//IL_0106: Unknown result type (might be due to invalid IL or missing references)
		//IL_019d: Unknown result type (might be due to invalid IL or missing references)
		//IL_01bd: Unknown result type (might be due to invalid IL or missing references)
		foreach (Renderer componentsInChild in root.GetComponentsInChildren<Renderer>(true))
		{
			Il2CppReferenceArray<Material> materials = componentsInChild.materials;
			foreach (Material item in (Il2CppArrayBase<Material>)(object)materials)
			{
				string[] obj = new string[5]
				{
					((Object)componentsInChild).name,
					" ",
					((Object)item).name,
					" ",
					null
				};
				Texture mainTexture = item.mainTexture;
				obj[4] = ((mainTexture != null) ? ((Object)mainTexture).name : null);
				string text = string.Concat(obj).ToLowerInvariant();
				if ((text.Contains("label") || text.Contains("etiquette") || text.Contains("ogkush") || text.Contains("og kush") || text.Contains("og_kush")) && !text.Contains("hand") && !text.Contains("skin"))
				{
					item.mainTexture = (Texture)(object)Models.Texture(variety.Name.ToUpperInvariant() + " VIAL");
					item.color = Color.white;
				}
			}
			componentsInChild.materials = materials;
			switch (((Object)componentsInChild).name.ToLowerInvariant())
			{
			default:
			{
				Transform parent = ((Component)componentsInChild).transform.parent;
				if (!(((parent != null) ? ((Object)parent).name : null) == "Seed"))
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
			foreach (Material item2 in (Il2CppArrayBase<Material>)(object)materials)
			{
				item2.color = variety.Bud;
				if (item2.HasProperty("_BaseColor"))
				{
					item2.SetColor("_BaseColor", variety.Bud);
				}
			}
		}
	}

	internal static void TintPhysicalSeed(FunctionalSeed seed, Crops.Variety variety)
	{
		//IL_0063: Unknown result type (might be due to invalid IL or missing references)
		//IL_0081: Unknown result type (might be due to invalid IL or missing references)
		object obj;
		if (!((Object)(object)seed.SeedRigidbody != (Object)null))
		{
			Collider seedCollider = seed.SeedCollider;
			obj = ((seedCollider != null) ? ((Component)seedCollider).transform : null);
		}
		else
		{
			obj = ((Component)seed.SeedRigidbody).transform;
		}
		Transform val = (Transform)obj;
		if ((Object)(object)val == (Object)null)
		{
			return;
		}
		foreach (Renderer componentsInChild in ((Component)val).GetComponentsInChildren<Renderer>(true))
		{
			foreach (Material item in (Il2CppArrayBase<Material>)(object)componentsInChild.materials)
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
