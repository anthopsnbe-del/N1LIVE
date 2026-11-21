using System;
using Il2CppInterop.Runtime.InteropTypes;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne.AvatarFramework.Equipping;
using Il2CppScheduleOne.Core.Items.Framework;
using Il2CppScheduleOne.Equipping;
using Il2CppScheduleOne.ItemFramework;
using Il2CppScheduleOne.Product;
using Il2CppScheduleOne.Storage;
using S1API.Items;
using UnityEngine;

namespace VarietesEtendues;

internal static class WeedPresentation
{
	internal static GameObject Configure(WeedDefinition target, WeedDefinition donor)
	{
		if ((Object)(object)((StorableItemDefinition)donor).StoredItem != (Object)null)
		{
			GameObject val = Object.Instantiate<GameObject>(((Component)((StorableItemDefinition)donor).StoredItem).gameObject);
			Presentation.Cache(val);
			foreach (WeedVisualsSetter componentsInChild in val.GetComponentsInChildren<WeedVisualsSetter>(true))
			{
				((ProductVisualsSetter)componentsInChild).ApplyVisuals((ProductDefinition)(object)target);
			}
			((StorableItemDefinition)target).StoredItem = val.GetComponent<StoredItem>();
		}
		if ((Object)(object)((ItemDefinition)donor).Equippable != (Object)null)
		{
			Equippable val2 = Object.Instantiate<Equippable>(((ItemDefinition)donor).Equippable);
			Presentation.Cache(((Component)val2).gameObject);
			((ItemDefinition)target).Equippable = val2;
			Equippable_Viewmodel val3 = ((Il2CppObjectBase)val2).TryCast<Equippable_Viewmodel>();
			if ((Object)(object)((val3 != null) ? val3.AvatarEquippable : null) != (Object)null)
			{
				AvatarEquippable val4 = Object.Instantiate<AvatarEquippable>(val3.AvatarEquippable);
				Presentation.Cache(((Component)val4).gameObject);
				foreach (WeedVisualsSetter componentsInChild2 in ((Component)val4).GetComponentsInChildren<WeedVisualsSetter>(true))
				{
					((ProductVisualsSetter)componentsInChild2).ApplyVisuals((ProductDefinition)(object)target);
				}
				string text2 = (val4.AssetPath = "Equippables/VarietesEtendues/weed_" + ((BaseItemDefinition)target).ID);
				if (!AvatarEquippableRegistry.RegisterAvatarEquippable(text2, ((Component)val4).gameObject))
				{
					throw new InvalidOperationException("Présentation de récolte non enregistrée : " + ((BaseItemDefinition)target).ID);
				}
				val3.AvatarEquippable = val4;
			}
		}
		FunctionalProduct functionalProduct = ((ProductDefinition)donor).FunctionalProduct;
		object obj;
		if (functionalProduct == null)
		{
			obj = null;
		}
		else
		{
			ProductVisualsSetter visuals = functionalProduct.Visuals;
			obj = ((visuals != null) ? ((Il2CppObjectBase)visuals).TryCast<WeedVisualsSetter>() : null);
		}
		if ((Object)obj == (Object)null)
		{
			throw new InvalidOperationException("Modèle natif de tête de cannabis introuvable");
		}
		WeedVisualsSetter val5 = Object.Instantiate<WeedVisualsSetter>((WeedVisualsSetter)obj);
		((Object)val5).name = "VX_NativeBud_" + ((BaseItemDefinition)target).ID;
		((ProductVisualsSetter)val5).ApplyVisuals((ProductDefinition)(object)target);
		foreach (MeshMaterialSettings item in (Il2CppArrayBase<MeshMaterialSettings>)(object)val5.Meshes)
		{
			if ((Object)(object)((item != null) ? item.Mesh : null) != (Object)null)
			{
				((Renderer)item.Mesh).enabled = true;
				((Component)item.Mesh).gameObject.SetActive(true);
			}
		}
		Presentation.Cache(((Component)val5).gameObject);
		return ((Component)val5).gameObject;
	}
}
