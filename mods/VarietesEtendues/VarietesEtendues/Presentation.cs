using System;
using System.Collections.Generic;
using System.Linq;
using Il2CppInterop.Runtime.InteropTypes;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne;
using Il2CppScheduleOne.AvatarFramework.Equipping;
using Il2CppScheduleOne.Equipping;
using Il2CppScheduleOne.ItemFramework;
using S1API.Items;
using UnityEngine;

namespace VarietesEtendues;

internal static class Presentation
{
	private static UnityEngine.GameObject? cache;

	internal static void Cache(UnityEngine.GameObject prefab)
	{
		if ((UnityEngine.Object)(object)cache == (UnityEngine.Object)null)
		{
			cache = new GameObject("VX_Prefabs");
			cache.SetActive(false);
			UnityEngine.Object.DontDestroyOnLoad((UnityEngine.Object)(object)cache);
		}
		prefab.transform.SetParent(cache.transform, false);
		prefab.SetActive(true);
	}

	internal static void Layer(UnityEngine.Transform root, int layer)
	{
		((UnityEngine.Component)root).gameObject.layer = layer;
		for (int i = 0; i < root.childCount; i++)
		{
			Layer(root.GetChild(i), layer);
		}
	}

	internal static Il2CppScheduleOne.ItemFramework.StorableItemDefinition Template(Entry entry)
	{
		string text;
		switch (entry.Key)
		{
		case "syrup":
			text = "flumedicine";
			break;
		case "focalix":
		case "fizz":
			text = "addy";
			break;
		case "cyclo":
		case "paranox":
			text = "iodine";
			break;
		case "absinthe":
			text = "mouthwash";
			break;
		case "nitro":
		case "lithium":
			text = "battery";
			break;
		case "patee":
			text = "cuke";
			break;
		case "appetix":
		case "pill666":
		case "poprocks":
			text = "paracetamol";
			break;
		case "melon":
		case "prune":
			text = "donut";
			break;
		case "glow":
			text = "chili";
			break;
		default:
			text = "banana";
			break;
		}
		Il2CppScheduleOne.ItemFramework.ItemDefinition item = Il2CppScheduleOne.Registry.GetItem(text);
		return ((item != null) ? ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)item).TryCast<Il2CppScheduleOne.ItemFramework.StorableItemDefinition>() : null) ?? ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)Il2CppScheduleOne.Registry.GetItem("banana")).Cast<Il2CppScheduleOne.ItemFramework.StorableItemDefinition>();
	}

	internal static UnityEngine.GameObject ReplaceMeshes(UnityEngine.GameObject scaffold, Entry entry)
	{
		Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.MeshRenderer> componentsInChildren = scaffold.GetComponentsInChildren<UnityEngine.MeshRenderer>(true);
		UnityEngine.Bounds? val = null;
		UnityEngine.Bounds val2;
		foreach (UnityEngine.MeshRenderer item in componentsInChildren)
		{
			if (!((UnityEngine.Renderer)item).enabled || !((UnityEngine.Component)item).gameObject.activeSelf)
			{
				continue;
			}
			val2 = ((UnityEngine.Renderer)item).bounds;
			UnityEngine.Vector3 size = val2.size;
			if (size.sqrMagnitude > 0f)
			{
				if (val.HasValue)
				{
					UnityEngine.Bounds value = val.Value;
					value.Encapsulate(((UnityEngine.Renderer)item).bounds);
					val = value;
				}
				else
				{
					val = ((UnityEngine.Renderer)item).bounds;
				}
			}
		}
		UnityEngine.GameObject val3 = Models.Make(entry.Key, entry.Name);
		val3.transform.SetParent(scaffold.transform, false);
		if (val.HasValue)
		{
			UnityEngine.Bounds? val4 = null;
			foreach (UnityEngine.Renderer componentsInChild in val3.GetComponentsInChildren<UnityEngine.Renderer>(true))
			{
				if (val4.HasValue)
				{
					UnityEngine.Bounds value2 = val4.Value;
					value2.Encapsulate(componentsInChild.bounds);
					val4 = value2;
				}
				else
				{
					val4 = componentsInChild.bounds;
				}
			}
			if (val4.HasValue)
			{
				UnityEngine.Transform transform = val3.transform;
				UnityEngine.Vector3 position = transform.position;
				val2 = val.Value;
				UnityEngine.Vector3 center = val2.center;
				val2 = val4.Value;
				transform.position = position + (center - val2.center);
			}
		}
		UnityEngine.MeshRenderer val5 = ((System.Collections.Generic.IEnumerable<UnityEngine.MeshRenderer>)componentsInChildren).FirstOrDefault((System.Func<UnityEngine.MeshRenderer, bool>)((UnityEngine.MeshRenderer r) => ((UnityEngine.Renderer)r).enabled && ((UnityEngine.Component)r).gameObject.activeSelf));
		int layer = (((UnityEngine.Object)(object)val5 != (UnityEngine.Object)null) ? ((UnityEngine.Component)val5).gameObject.layer : scaffold.layer);
		Layer(val3.transform, layer);
		foreach (UnityEngine.MeshRenderer item2 in componentsInChildren)
		{
			((UnityEngine.Renderer)item2).enabled = false;
		}
		return val3;
	}

	internal static void ConfigureHeld(Il2CppScheduleOne.ItemFramework.StorableItemDefinition source, Il2CppScheduleOne.ItemFramework.StorableItemDefinition target, Entry entry)
	{
		if ((UnityEngine.Object)(object)((Il2CppScheduleOne.ItemFramework.ItemDefinition)source).Equippable == (UnityEngine.Object)null)
		{
			throw new InvalidOperationException("Modèle tenu de référence absent");
		}
		Il2CppScheduleOne.Equipping.Equippable val = UnityEngine.Object.Instantiate<Il2CppScheduleOne.Equipping.Equippable>(((Il2CppScheduleOne.ItemFramework.ItemDefinition)source).Equippable);
		((UnityEngine.Object)val).name = "VX_EnMain_" + entry.Key;
		Il2CppScheduleOne.Equipping.Equippable_Viewmodel val2 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val).TryCast<Il2CppScheduleOne.Equipping.Equippable_Viewmodel>();
		if ((UnityEngine.Object)(object)((val2 != null) ? val2.AvatarEquippable : null) != (UnityEngine.Object)null)
		{
			Il2CppScheduleOne.AvatarFramework.Equipping.AvatarEquippable val3 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.AvatarFramework.Equipping.AvatarEquippable>(val2.AvatarEquippable);
			((UnityEngine.Object)val3).name = "VX_Avatar_" + entry.Key;
			ReplaceMeshes(((UnityEngine.Component)val3).gameObject, entry);
			string text2 = (val3.AssetPath = "Equippables/VarietesEtendues/" + entry.Key);
			string text3 = text2;
			if (!S1API.Items.AvatarEquippableRegistry.RegisterAvatarEquippable(text3, ((UnityEngine.Component)val3).gameObject))
			{
				throw new InvalidOperationException("Présentation réseau non enregistrée : " + text3);
			}
			Cache(((UnityEngine.Component)val3).gameObject);
			val2.AvatarEquippable = val3;
			if ((UnityEngine.Object)(object)((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val).TryCast<Il2CppScheduleOne.Equipping.Equippable_AvatarViewmodel>() == (UnityEngine.Object)null)
			{
				ReplaceMeshes(((UnityEngine.Component)val).gameObject, entry);
			}
		}
		else
		{
			ReplaceMeshes(((UnityEngine.Component)val).gameObject, entry);
		}
		Cache(((UnityEngine.Component)val).gameObject);
		((Il2CppScheduleOne.ItemFramework.ItemDefinition)target).Equippable = val;
	}
}
