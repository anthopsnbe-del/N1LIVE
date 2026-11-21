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
	internal static UnityEngine.GameObject Configure(Il2CppScheduleOne.Product.WeedDefinition target, Il2CppScheduleOne.Product.WeedDefinition donor)
	{
		if ((UnityEngine.Object)(object)((Il2CppScheduleOne.ItemFramework.StorableItemDefinition)donor).StoredItem != (UnityEngine.Object)null)
		{
			UnityEngine.GameObject val = UnityEngine.Object.Instantiate<UnityEngine.GameObject>(((UnityEngine.Component)((Il2CppScheduleOne.ItemFramework.StorableItemDefinition)donor).StoredItem).gameObject);
			Presentation.Cache(val);
			foreach (Il2CppScheduleOne.Product.WeedVisualsSetter componentsInChild in val.GetComponentsInChildren<Il2CppScheduleOne.Product.WeedVisualsSetter>(true))
			{
				((Il2CppScheduleOne.Product.ProductVisualsSetter)componentsInChild).ApplyVisuals((Il2CppScheduleOne.Product.ProductDefinition)(object)target);
			}
			((Il2CppScheduleOne.ItemFramework.StorableItemDefinition)target).StoredItem = val.GetComponent<Il2CppScheduleOne.Storage.StoredItem>();
		}
		if ((UnityEngine.Object)(object)((Il2CppScheduleOne.ItemFramework.ItemDefinition)donor).Equippable != (UnityEngine.Object)null)
		{
			Il2CppScheduleOne.Equipping.Equippable val2 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.Equipping.Equippable>(((Il2CppScheduleOne.ItemFramework.ItemDefinition)donor).Equippable);
			Presentation.Cache(((UnityEngine.Component)val2).gameObject);
			((Il2CppScheduleOne.ItemFramework.ItemDefinition)target).Equippable = val2;
			Il2CppScheduleOne.Equipping.Equippable_Viewmodel val3 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val2).TryCast<Il2CppScheduleOne.Equipping.Equippable_Viewmodel>();
			if ((UnityEngine.Object)(object)((val3 != null) ? val3.AvatarEquippable : null) != (UnityEngine.Object)null)
			{
				Il2CppScheduleOne.AvatarFramework.Equipping.AvatarEquippable val4 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.AvatarFramework.Equipping.AvatarEquippable>(val3.AvatarEquippable);
				Presentation.Cache(((UnityEngine.Component)val4).gameObject);
				foreach (Il2CppScheduleOne.Product.WeedVisualsSetter componentsInChild2 in ((UnityEngine.Component)val4).GetComponentsInChildren<Il2CppScheduleOne.Product.WeedVisualsSetter>(true))
				{
					((Il2CppScheduleOne.Product.ProductVisualsSetter)componentsInChild2).ApplyVisuals((Il2CppScheduleOne.Product.ProductDefinition)(object)target);
				}
				string text2 = (val4.AssetPath = "Equippables/VarietesEtendues/weed_" + ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)target).ID);
				if (!S1API.Items.AvatarEquippableRegistry.RegisterAvatarEquippable(text2, ((UnityEngine.Component)val4).gameObject))
				{
					throw new InvalidOperationException("Présentation de récolte non enregistrée : " + ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)target).ID);
				}
				val3.AvatarEquippable = val4;
			}
		}
		Il2CppScheduleOne.Product.FunctionalProduct functionalProduct = ((Il2CppScheduleOne.Product.ProductDefinition)donor).FunctionalProduct;
		object obj;
		if (functionalProduct == null)
		{
			obj = null;
		}
		else
		{
			Il2CppScheduleOne.Product.ProductVisualsSetter visuals = functionalProduct.Visuals;
			obj = ((visuals != null) ? ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)visuals).TryCast<Il2CppScheduleOne.Product.WeedVisualsSetter>() : null);
		}
		if ((UnityEngine.Object)obj == (UnityEngine.Object)null)
		{
			throw new InvalidOperationException("Modèle natif de tête de cannabis introuvable");
		}
		Il2CppScheduleOne.Product.WeedVisualsSetter val5 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.Product.WeedVisualsSetter>((Il2CppScheduleOne.Product.WeedVisualsSetter)obj);
		((UnityEngine.Object)val5).name = "VX_NativeBud_" + ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)target).ID;
		((Il2CppScheduleOne.Product.ProductVisualsSetter)val5).ApplyVisuals((Il2CppScheduleOne.Product.ProductDefinition)(object)target);
		foreach (Il2CppScheduleOne.Product.WeedVisualsSetter.MeshMaterialSettings item in (Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<Il2CppScheduleOne.Product.WeedVisualsSetter.MeshMaterialSettings>)(object)val5.Meshes)
		{
			if ((UnityEngine.Object)(object)((item != null) ? item.Mesh : null) != (UnityEngine.Object)null)
			{
				((UnityEngine.Renderer)item.Mesh).enabled = true;
				((UnityEngine.Component)item.Mesh).gameObject.SetActive(true);
			}
		}
		Presentation.Cache(((UnityEngine.Component)val5).gameObject);
		return ((UnityEngine.Component)val5).gameObject;
	}
}
