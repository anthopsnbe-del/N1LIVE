using System.Collections.Generic;
using System.IO;
using System.Linq;
using Il2CppInterop.Runtime.InteropTypes;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne;
using Il2CppScheduleOne.Core.Items.Framework;
using Il2CppScheduleOne.DevUtilities;
using Il2CppScheduleOne.Effects;
using Il2CppScheduleOne.Equipping;
using Il2CppScheduleOne.Growing;
using Il2CppScheduleOne.ItemFramework;
using Il2CppScheduleOne.Persistence.Datas;
using Il2CppScheduleOne.Product;
using Il2CppSystem.Collections.Generic;
using MelonLoader;
using UnityEngine;

namespace VarietesEtendues;

internal static class Regression
{
	internal static void Run()
	{
		Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Persistence.Datas.ItemData> val = new Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Persistence.Datas.ItemData>();
		System.Collections.Generic.List<string> list = new System.Collections.Generic.List<string>();
		int num = 0;
		int num2 = 0;
		Entry[] catalogue = Mod.Catalogue;
		foreach (Entry entry in catalogue)
		{
			Il2CppScheduleOne.ItemFramework.StorableItemDefinition val2 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)Il2CppScheduleOne.Registry.GetItem("vx_" + entry.Key)).Cast<Il2CppScheduleOne.ItemFramework.StorableItemDefinition>();
			if (!((UnityEngine.Component)val2.StoredItem).gameObject.activeSelf || !((UnityEngine.Component)((Il2CppScheduleOne.ItemFramework.ItemDefinition)val2).Equippable).gameObject.activeSelf)
			{
				throw new InvalidDataException("Modèle désactivé : " + entry.Key);
			}
			UnityEngine.GameObject val3 = UnityEngine.Object.Instantiate<UnityEngine.GameObject>(((UnityEngine.Component)val2.StoredItem).gameObject);
			val3.transform.position = new Vector3(0f, 10000f, 0f);
			try
			{
				if (!val3.activeInHierarchy || !((System.Collections.Generic.IEnumerable<UnityEngine.Renderer>)val3.GetComponentsInChildren<UnityEngine.Renderer>()).Any((UnityEngine.Renderer r) => r.enabled))
				{
					throw new InvalidDataException("Clone de stockage invisible : " + entry.Key);
				}
				int iconLayer = UnityEngine.LayerMask.NameToLayer("IconGeneration");
				int previewLayer = UnityEngine.LayerMask.NameToLayer("RuntimePreviewGeneration");
				if (((System.Collections.Generic.IEnumerable<UnityEngine.Renderer>)val3.GetComponentsInChildren<UnityEngine.Renderer>()).Any((UnityEngine.Renderer r) => r.enabled && (((UnityEngine.Component)r).gameObject.layer == iconLayer || ((UnityEngine.Component)r).gameObject.layer == previewLayer)))
				{
					throw new InvalidDataException("Modèle stocké sur un calque d'icône : " + entry.Key);
				}
				num++;
			}
			finally
			{
				UnityEngine.Object.Destroy((UnityEngine.Object)(object)val3);
			}
			val.Add(((Il2CppScheduleOne.ItemFramework.ItemDefinition)val2).GetDefaultInstance(3).GetItemData());
			list.Add(((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2).ID);
			Il2CppScheduleOne.Effects.Effect val4 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val2).Cast<Il2CppScheduleOne.Product.PropertyItemDefinition>().Properties[0];
			if (!NewEffects.IsCustom(val4.ID))
			{
				throw new InvalidDataException("Effet inédit absent : " + entry.Key);
			}
			var enumerator = Il2CppScheduleOne.DevUtilities.NetworkSingleton<Il2CppScheduleOne.Product.ProductManager>.Instance.ValidMixIngredients.GetEnumerator();
			while (enumerator.MoveNext())
			{
				Il2CppScheduleOne.Product.PropertyItemDefinition current = enumerator.Current;
				if (!((UnityEngine.Object)(object)current == (UnityEngine.Object)null) && !((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)current).ID.StartsWith("vx_") && current.Properties != null && current.Properties.Count != 0)
				{
					Il2CppScheduleOne.Product.EDrugType[] array = (Il2CppScheduleOne.Product.EDrugType[])(object)new Il2CppScheduleOne.Product.EDrugType[3]
					{
						default(Il2CppScheduleOne.Product.EDrugType),
						(Il2CppScheduleOne.Product.EDrugType)1,
						(Il2CppScheduleOne.Product.EDrugType)2
					};
					foreach (Il2CppScheduleOne.Product.EDrugType val5 in array)
					{
						Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> result = Il2CppScheduleOne.Effects.EffectMixCalculator.MixProperties(Il2CppScheduleOne.Effects.EffectMixCalculator.MixProperties(new Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect>(), val4, val5), current.Properties[0], val5);
						Check(result, entry.Key + " + " + ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)current).ID);
						Require(result, val4.ID);
						Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> result2 = Il2CppScheduleOne.Effects.EffectMixCalculator.MixProperties(Il2CppScheduleOne.Effects.EffectMixCalculator.MixProperties(new Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect>(), current.Properties[0], val5), val4, val5);
						Check(result2, ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)current).ID + " + " + entry.Key);
						Require(result2, val4.ID);
						num2 += 2;
					}
				}
			}
		}
		Crops.Variety[] varieties = Crops.Varieties;
		foreach (Crops.Variety variety in varieties)
		{
			Il2CppScheduleOne.Growing.SeedDefinition val6 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)Il2CppScheduleOne.Registry.GetItem(Crops.SeedId(variety))).Cast<Il2CppScheduleOne.Growing.SeedDefinition>();
			if ((UnityEngine.Object)(object)val6.PlantPrefab == (UnityEngine.Object)null || ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val6.PlantPrefab.SeedDefinition).ID != ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val6).ID || !((UnityEngine.Component)val6.PlantPrefab).gameObject.activeSelf)
			{
				throw new InvalidDataException("Plante non restaurable : " + ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val6).ID);
			}
			Il2CppScheduleOne.Equipping.Equippable val7 = ((Il2CppScheduleOne.ItemFramework.ItemDefinition)val6).Equippable ?? throw new InvalidDataException("Équipement de graine absent : " + ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val6).ID);
			Il2CppScheduleOne.Equipping.Equippable_Seed val8 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val7).TryCast<Il2CppScheduleOne.Equipping.Equippable_Seed>();
			if ((UnityEngine.Object)(object)val8 == (UnityEngine.Object)null || (UnityEngine.Object)(object)val8.Seed == (UnityEngine.Object)null || ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val8.Seed).ID != ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val6).ID || ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val7).Pointer == ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)((Il2CppScheduleOne.ItemFramework.ItemDefinition)Crops.Source).Equippable).Pointer)
			{
				throw new InvalidDataException("Le semis pointe vers une autre variété : " + ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val6).ID);
			}
			if ((UnityEngine.Object)(object)val6.FunctionSeedPrefab == (UnityEngine.Object)null || ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val6.FunctionSeedPrefab).Pointer == ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)Crops.Source.FunctionSeedPrefab).Pointer)
			{
				throw new InvalidDataException("Récipient de semis partagé avec l'OG Kush : " + ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val6).ID);
			}
			Il2CppScheduleOne.Growing.WeedPlant val9 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)UnityEngine.Object.Instantiate<Il2CppScheduleOne.Growing.Plant>(val6.PlantPrefab)).Cast<Il2CppScheduleOne.Growing.WeedPlant>();
			((UnityEngine.Component)val9).transform.position = new Vector3(0f, 10000f, 0f);
			try
			{
				Il2CppScheduleOne.ItemFramework.ItemInstance harvestedProduct = ((Il2CppScheduleOne.Growing.Plant)val9).GetHarvestedProduct(3);
				if (((harvestedProduct != null) ? ((Il2CppScheduleOne.Core.Items.Framework.BaseItemInstance)harvestedProduct).ID : null) != "vx_" + variety.Key || harvestedProduct.GetItemData().Quantity != 3 || ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)((Il2CppScheduleOne.Growing.Plant)val9).SeedDefinition).ID != ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val6).ID)
				{
					throw new InvalidDataException("La récolte ne conserve pas la variété : " + variety.Name);
				}
			}
			finally
			{
				UnityEngine.Object.Destroy((UnityEngine.Object)(object)((UnityEngine.Component)val9).gameObject);
			}
			val.Add(((Il2CppScheduleOne.ItemFramework.ItemDefinition)val6).GetDefaultInstance(3).GetItemData());
			list.Add(((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val6).ID);
			Il2CppScheduleOne.Product.WeedDefinition val10 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)Il2CppScheduleOne.Registry.GetItem("vx_" + variety.Key)).Cast<Il2CppScheduleOne.Product.WeedDefinition>();
			val.Add(((Il2CppScheduleOne.ItemFramework.ItemDefinition)val10).GetDefaultInstance(3).GetItemData());
			list.Add(((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val10).ID);
		}
		Il2CppScheduleOne.Persistence.Datas.DeserializedItemSet val11 = default(Il2CppScheduleOne.Persistence.Datas.DeserializedItemSet);
		if (!Il2CppScheduleOne.Persistence.Datas.ItemSet.TryDeserialize(new ItemSet(val).GetJSON(), out val11) || ((val11 != null) ? val11.Items : null) == null || ((Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<Il2CppScheduleOne.ItemFramework.ItemInstance>)(object)val11.Items).Length != list.Count)
		{
			throw new InvalidDataException("Échec du chargement des objets sérialisés");
		}
		for (int k = 0; k < list.Count; k++)
		{
			Il2CppScheduleOne.ItemFramework.ItemInstance obj = ((Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<Il2CppScheduleOne.ItemFramework.ItemInstance>)(object)val11.Items)[k];
			object obj2;
			if (obj == null)
			{
				obj2 = null;
			}
			else
			{
				Il2CppScheduleOne.ItemFramework.ItemDefinition definition = obj.Definition;
				obj2 = ((definition != null) ? ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)definition).ID : null);
			}
			if ((string?)obj2 != list[k] || ((Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<Il2CppScheduleOne.ItemFramework.ItemInstance>)(object)val11.Items)[k].GetItemData().Quantity != 3)
			{
				throw new InvalidDataException("Objet perdu à la restauration : " + list[k]);
			}
		}
		MelonLoader.MelonLogger.Msg($"RÉGRESSION : {num} clones de stockage visibles ; {num2} mélanges croisés ; {list.Count} objets sauvegardés/restaurés sans perte.");
	}

	private static void Check(Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> result, string recipe)
	{
		if (result == null || result.Count == 0 || result.Count > Mixing.MaxEffects)
		{
			throw new InvalidDataException("Mélange incompatible : " + recipe);
		}
	}

	private static void Require(Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> result, string id)
	{
		var enumerator = result.GetEnumerator();
		while (enumerator.MoveNext())
		{
			if (enumerator.Current.ID == id)
			{
				return;
			}
		}
		throw new InvalidDataException("Effet inédit perdu au mélange : " + id);
	}
}
