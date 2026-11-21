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
	private static GameObject? cache;

	internal static void Cache(GameObject prefab)
	{
		//IL_0012: Unknown result type (might be due to invalid IL or missing references)
		//IL_001c: Expected O, but got Unknown
		if ((Object)(object)cache == (Object)null)
		{
			cache = new GameObject("VX_Prefabs");
			cache.SetActive(false);
			Object.DontDestroyOnLoad((Object)(object)cache);
		}
		prefab.transform.SetParent(cache.transform, false);
		prefab.SetActive(true);
	}

	internal static void Layer(Transform root, int layer)
	{
		((Component)root).gameObject.layer = layer;
		for (int i = 0; i < root.childCount; i++)
		{
			Layer(root.GetChild(i), layer);
		}
	}

	internal static StorableItemDefinition Template(Entry entry)
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
		ItemDefinition item = Registry.GetItem(text);
		return ((item != null) ? ((Il2CppObjectBase)item).TryCast<StorableItemDefinition>() : null) ?? ((Il2CppObjectBase)Registry.GetItem("banana")).Cast<StorableItemDefinition>();
	}

	internal static GameObject ReplaceMeshes(GameObject scaffold, Entry entry)
	{
		//IL_003c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0041: Unknown result type (might be due to invalid IL or missing references)
		//IL_0045: Unknown result type (might be due to invalid IL or missing references)
		//IL_004a: Unknown result type (might be due to invalid IL or missing references)
		//IL_0086: Unknown result type (might be due to invalid IL or missing references)
		//IL_0065: Unknown result type (might be due to invalid IL or missing references)
		//IL_006a: Unknown result type (might be due to invalid IL or missing references)
		//IL_0070: Unknown result type (might be due to invalid IL or missing references)
		//IL_007a: Unknown result type (might be due to invalid IL or missing references)
		//IL_0126: Unknown result type (might be due to invalid IL or missing references)
		//IL_0104: Unknown result type (might be due to invalid IL or missing references)
		//IL_0109: Unknown result type (might be due to invalid IL or missing references)
		//IL_010f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0119: Unknown result type (might be due to invalid IL or missing references)
		//IL_0159: Unknown result type (might be due to invalid IL or missing references)
		//IL_0160: Unknown result type (might be due to invalid IL or missing references)
		//IL_0165: Unknown result type (might be due to invalid IL or missing references)
		//IL_0169: Unknown result type (might be due to invalid IL or missing references)
		//IL_0170: Unknown result type (might be due to invalid IL or missing references)
		//IL_0175: Unknown result type (might be due to invalid IL or missing references)
		//IL_0179: Unknown result type (might be due to invalid IL or missing references)
		//IL_017e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0183: Unknown result type (might be due to invalid IL or missing references)
		Il2CppArrayBase<MeshRenderer> componentsInChildren = scaffold.GetComponentsInChildren<MeshRenderer>(true);
		Bounds? val = null;
		Bounds val2;
		foreach (MeshRenderer item in componentsInChildren)
		{
			if (!((Renderer)item).enabled || !((Component)item).gameObject.activeSelf)
			{
				continue;
			}
			val2 = ((Renderer)item).bounds;
			Vector3 size = val2.size;
			if (size.sqrMagnitude > 0f)
			{
				if (val.HasValue)
				{
					Bounds value = val.Value;
					value.Encapsulate(((Renderer)item).bounds);
					val = value;
				}
				else
				{
					val = ((Renderer)item).bounds;
				}
			}
		}
		GameObject val3 = Models.Make(entry.Key, entry.Name);
		val3.transform.SetParent(scaffold.transform, false);
		if (val.HasValue)
		{
			Bounds? val4 = null;
			foreach (Renderer componentsInChild in val3.GetComponentsInChildren<Renderer>(true))
			{
				if (val4.HasValue)
				{
					Bounds value2 = val4.Value;
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
				Transform transform = val3.transform;
				Vector3 position = transform.position;
				val2 = val.Value;
				Vector3 center = val2.center;
				val2 = val4.Value;
				transform.position = position + (center - val2.center);
			}
		}
		MeshRenderer val5 = ((IEnumerable<MeshRenderer>)componentsInChildren).FirstOrDefault((Func<MeshRenderer, bool>)((MeshRenderer r) => ((Renderer)r).enabled && ((Component)r).gameObject.activeSelf));
		int layer = (((Object)(object)val5 != (Object)null) ? ((Component)val5).gameObject.layer : scaffold.layer);
		Layer(val3.transform, layer);
		foreach (MeshRenderer item2 in componentsInChildren)
		{
			((Renderer)item2).enabled = false;
		}
		return val3;
	}

	internal static void ConfigureHeld(StorableItemDefinition source, StorableItemDefinition target, Entry entry)
	{
		if ((Object)(object)((ItemDefinition)source).Equippable == (Object)null)
		{
			throw new InvalidOperationException("Modèle tenu de référence absent");
		}
		Equippable val = Object.Instantiate<Equippable>(((ItemDefinition)source).Equippable);
		((Object)val).name = "VX_EnMain_" + entry.Key;
		Equippable_Viewmodel val2 = ((Il2CppObjectBase)val).TryCast<Equippable_Viewmodel>();
		if ((Object)(object)((val2 != null) ? val2.AvatarEquippable : null) != (Object)null)
		{
			AvatarEquippable val3 = Object.Instantiate<AvatarEquippable>(val2.AvatarEquippable);
			((Object)val3).name = "VX_Avatar_" + entry.Key;
			ReplaceMeshes(((Component)val3).gameObject, entry);
			string text2 = (val3.AssetPath = "Equippables/VarietesEtendues/" + entry.Key);
			string text3 = text2;
			if (!AvatarEquippableRegistry.RegisterAvatarEquippable(text3, ((Component)val3).gameObject))
			{
				throw new InvalidOperationException("Présentation réseau non enregistrée : " + text3);
			}
			Cache(((Component)val3).gameObject);
			val2.AvatarEquippable = val3;
			if ((Object)(object)((Il2CppObjectBase)val).TryCast<Equippable_AvatarViewmodel>() == (Object)null)
			{
				ReplaceMeshes(((Component)val).gameObject, entry);
			}
		}
		else
		{
			ReplaceMeshes(((Component)val).gameObject, entry);
		}
		Cache(((Component)val).gameObject);
		((ItemDefinition)target).Equippable = val;
	}
}
