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
		//IL_00a9: Unknown result type (might be due to invalid IL or missing references)
		//IL_0588: Unknown result type (might be due to invalid IL or missing references)
		//IL_0490: Unknown result type (might be due to invalid IL or missing references)
		//IL_023e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0247: Unknown result type (might be due to invalid IL or missing references)
		//IL_025b: Unknown result type (might be due to invalid IL or missing references)
		//IL_029e: Unknown result type (might be due to invalid IL or missing references)
		//IL_02a7: Unknown result type (might be due to invalid IL or missing references)
		List<ItemData> val = new List<ItemData>();
		List<string> list = new List<string>();
		int num = 0;
		int num2 = 0;
		Entry[] catalogue = Mod.Catalogue;
		foreach (Entry entry in catalogue)
		{
			StorableItemDefinition val2 = ((Il2CppObjectBase)Registry.GetItem("vx_" + entry.Key)).Cast<StorableItemDefinition>();
			if (!((Component)val2.StoredItem).gameObject.activeSelf || !((Component)((ItemDefinition)val2).Equippable).gameObject.activeSelf)
			{
				throw new InvalidDataException("Modèle désactivé : " + entry.Key);
			}
			GameObject val3 = Object.Instantiate<GameObject>(((Component)val2.StoredItem).gameObject);
			val3.transform.position = new Vector3(0f, 10000f, 0f);
			try
			{
				if (!val3.activeInHierarchy || !((IEnumerable<Renderer>)val3.GetComponentsInChildren<Renderer>()).Any((Renderer r) => r.enabled))
				{
					throw new InvalidDataException("Clone de stockage invisible : " + entry.Key);
				}
				int iconLayer = LayerMask.NameToLayer("IconGeneration");
				int previewLayer = LayerMask.NameToLayer("RuntimePreviewGeneration");
				if (((IEnumerable<Renderer>)val3.GetComponentsInChildren<Renderer>()).Any((Renderer r) => r.enabled && (((Component)r).gameObject.layer == iconLayer || ((Component)r).gameObject.layer == previewLayer)))
				{
					throw new InvalidDataException("Modèle stocké sur un calque d'icône : " + entry.Key);
				}
				num++;
			}
			finally
			{
				Object.Destroy((Object)(object)val3);
			}
			val.Add(((ItemDefinition)val2).GetDefaultInstance(3).GetItemData());
			list.Add(((BaseItemDefinition)val2).ID);
			Effect val4 = ((Il2CppObjectBase)val2).Cast<PropertyItemDefinition>().Properties[0];
			if (!NewEffects.IsCustom(val4.ID))
			{
				throw new InvalidDataException("Effet inédit absent : " + entry.Key);
			}
			Enumerator<PropertyItemDefinition> enumerator = NetworkSingleton<ProductManager>.Instance.ValidMixIngredients.GetEnumerator();
			while (enumerator.MoveNext())
			{
				PropertyItemDefinition current = enumerator.Current;
				if (!((Object)(object)current == (Object)null) && !((BaseItemDefinition)current).ID.StartsWith("vx_") && current.Properties != null && current.Properties.Count != 0)
				{
					EDrugType[] array = (EDrugType[])(object)new EDrugType[3]
					{
						default(EDrugType),
						(EDrugType)1,
						(EDrugType)2
					};
					foreach (EDrugType val5 in array)
					{
						List<Effect> result = EffectMixCalculator.MixProperties(EffectMixCalculator.MixProperties(new List<Effect>(), val4, val5), current.Properties[0], val5);
						Check(result, entry.Key + " + " + ((BaseItemDefinition)current).ID);
						Require(result, val4.ID);
						List<Effect> result2 = EffectMixCalculator.MixProperties(EffectMixCalculator.MixProperties(new List<Effect>(), current.Properties[0], val5), val4, val5);
						Check(result2, ((BaseItemDefinition)current).ID + " + " + entry.Key);
						Require(result2, val4.ID);
						num2 += 2;
					}
				}
			}
		}
		Crops.Variety[] varieties = Crops.Varieties;
		foreach (Crops.Variety variety in varieties)
		{
			SeedDefinition val6 = ((Il2CppObjectBase)Registry.GetItem(Crops.SeedId(variety))).Cast<SeedDefinition>();
			if ((Object)(object)val6.PlantPrefab == (Object)null || ((BaseItemDefinition)val6.PlantPrefab.SeedDefinition).ID != ((BaseItemDefinition)val6).ID || !((Component)val6.PlantPrefab).gameObject.activeSelf)
			{
				throw new InvalidDataException("Plante non restaurable : " + ((BaseItemDefinition)val6).ID);
			}
			Equippable val7 = ((ItemDefinition)val6).Equippable ?? throw new InvalidDataException("Équipement de graine absent : " + ((BaseItemDefinition)val6).ID);
			Equippable_Seed val8 = ((Il2CppObjectBase)val7).TryCast<Equippable_Seed>();
			if ((Object)(object)val8 == (Object)null || (Object)(object)val8.Seed == (Object)null || ((BaseItemDefinition)val8.Seed).ID != ((BaseItemDefinition)val6).ID || ((Il2CppObjectBase)val7).Pointer == ((Il2CppObjectBase)((ItemDefinition)Crops.Source).Equippable).Pointer)
			{
				throw new InvalidDataException("Le semis pointe vers une autre variété : " + ((BaseItemDefinition)val6).ID);
			}
			if ((Object)(object)val6.FunctionSeedPrefab == (Object)null || ((Il2CppObjectBase)val6.FunctionSeedPrefab).Pointer == ((Il2CppObjectBase)Crops.Source.FunctionSeedPrefab).Pointer)
			{
				throw new InvalidDataException("Récipient de semis partagé avec l'OG Kush : " + ((BaseItemDefinition)val6).ID);
			}
			WeedPlant val9 = ((Il2CppObjectBase)Object.Instantiate<Plant>(val6.PlantPrefab)).Cast<WeedPlant>();
			((Component)val9).transform.position = new Vector3(0f, 10000f, 0f);
			try
			{
				ItemInstance harvestedProduct = ((Plant)val9).GetHarvestedProduct(3);
				if (((harvestedProduct != null) ? ((BaseItemInstance)harvestedProduct).ID : null) != "vx_" + variety.Key || harvestedProduct.GetItemData().Quantity != 3 || ((BaseItemDefinition)((Plant)val9).SeedDefinition).ID != ((BaseItemDefinition)val6).ID)
				{
					throw new InvalidDataException("La récolte ne conserve pas la variété : " + variety.Name);
				}
			}
			finally
			{
				Object.Destroy((Object)(object)((Component)val9).gameObject);
			}
			val.Add(((ItemDefinition)val6).GetDefaultInstance(3).GetItemData());
			list.Add(((BaseItemDefinition)val6).ID);
			WeedDefinition val10 = ((Il2CppObjectBase)Registry.GetItem("vx_" + variety.Key)).Cast<WeedDefinition>();
			val.Add(((ItemDefinition)val10).GetDefaultInstance(3).GetItemData());
			list.Add(((BaseItemDefinition)val10).ID);
		}
		DeserializedItemSet val11 = default(DeserializedItemSet);
		if (!ItemSet.TryDeserialize(new ItemSet(val).GetJSON(), ref val11) || ((val11 != null) ? val11.Items : null) == null || ((Il2CppArrayBase<ItemInstance>)(object)val11.Items).Length != list.Count)
		{
			throw new InvalidDataException("Échec du chargement des objets sérialisés");
		}
		for (int k = 0; k < list.Count; k++)
		{
			ItemInstance obj = ((Il2CppArrayBase<ItemInstance>)(object)val11.Items)[k];
			object obj2;
			if (obj == null)
			{
				obj2 = null;
			}
			else
			{
				ItemDefinition definition = obj.Definition;
				obj2 = ((definition != null) ? ((BaseItemDefinition)definition).ID : null);
			}
			if ((string?)obj2 != list[k] || ((Il2CppArrayBase<ItemInstance>)(object)val11.Items)[k].GetItemData().Quantity != 3)
			{
				throw new InvalidDataException("Objet perdu à la restauration : " + list[k]);
			}
		}
		MelonLogger.Msg($"RÉGRESSION : {num} clones de stockage visibles ; {num2} mélanges croisés ; {list.Count} objets sauvegardés/restaurés sans perte.");
	}

	private static void Check(List<Effect> result, string recipe)
	{
		if (result == null || result.Count == 0 || result.Count > Mixing.MaxEffects)
		{
			throw new InvalidDataException("Mélange incompatible : " + recipe);
		}
	}

	private static void Require(List<Effect> result, string id)
	{
		Enumerator<Effect> enumerator = result.GetEnumerator();
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
