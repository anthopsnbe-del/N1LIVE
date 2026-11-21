using System;
using System.Collections.Generic;
using System.Linq;
using System.Runtime.CompilerServices;
using System.Text;
using Il2CppFishNet.Connection;
using Il2CppFishNet.Object;
using Il2CppInterop.Runtime.InteropTypes;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne;
using Il2CppScheduleOne.AvatarFramework.Equipping;
using Il2CppScheduleOne.Core.Items.Framework;
using Il2CppScheduleOne.DevUtilities;
using Il2CppScheduleOne.Effects;
using Il2CppScheduleOne.Equipping;
using Il2CppScheduleOne.Growing;
using Il2CppScheduleOne.ItemFramework;
using Il2CppScheduleOne.Product;
using Il2CppScheduleOne.Storage;
using Il2CppSystem.Collections.Generic;
using S1API.Items;
using UnityEngine;

namespace VarietesEtendues;

internal static class Crops
{
	internal sealed record Variety(string Key, string Name, string Effect, int SeedPrice, int BasePrice, Color Bud, Color Leaf)
	{
		[CompilerGenerated]
		private bool PrintMembers(StringBuilder builder)
		{
			//IL_00ac: Unknown result type (might be due to invalid IL or missing references)
			//IL_00b1: Unknown result type (might be due to invalid IL or missing references)
			//IL_00d3: Unknown result type (might be due to invalid IL or missing references)
			//IL_00d8: Unknown result type (might be due to invalid IL or missing references)
			RuntimeHelpers.EnsureSufficientExecutionStack();
			builder.Append("Key = ");
			builder.Append((object?)Key);
			builder.Append(", Name = ");
			builder.Append((object?)Name);
			builder.Append(", Effect = ");
			builder.Append((object?)Effect);
			builder.Append(", SeedPrice = ");
			builder.Append(SeedPrice.ToString());
			builder.Append(", BasePrice = ");
			builder.Append(BasePrice.ToString());
			builder.Append(", Bud = ");
			Color val = Bud;
			builder.Append(((object)(Color)(ref val)).ToString());
			builder.Append(", Leaf = ");
			val = Leaf;
			builder.Append(((object)(Color)(ref val)).ToString());
			return true;
		}
	}

	internal static readonly Variety[] Varieties = new Variety[3]
	{
		new Variety("lemonhaze", "Lemon Haze", "Focused", 40, 38, new Color(0.84f, 0.72f, 0.18f), new Color(0.56f, 0.72f, 0.28f)),
		new Variety("blueberrydream", "Blueberry Dream", "Euphoric", 45, 40, new Color(0.48f, 0.32f, 0.65f), new Color(0.35f, 0.42f, 0.48f)),
		new Variety("dragonkush", "Dragon Kush", "Glowing", 70, 52, new Color(0.4f, 0.2f, 0.54f), new Color(0.42f, 0.34f, 0.4f))
	};

	internal static readonly Dictionary<string, SeedDefinition> Seeds = new Dictionary<string, SeedDefinition>(StringComparer.Ordinal);

	private static readonly Dictionary<string, WeedDefinition> Products = new Dictionary<string, WeedDefinition>(StringComparer.Ordinal);

	internal static SeedDefinition? Source;

	internal static string? SourceId;

	private static readonly Dictionary<int, Renderer> tinted = new Dictionary<int, Renderer>();

	internal static string SeedId(Variety v)
	{
		return "vx_" + v.Key + "seed";
	}

	internal static void ClearRuntime()
	{
		tinted.Clear();
	}

	internal static void RefreshPlant(Plant plant)
	{
		//IL_00da: Unknown result type (might be due to invalid IL or missing references)
		//IL_00df: Unknown result type (might be due to invalid IL or missing references)
		//IL_00e0: Unknown result type (might be due to invalid IL or missing references)
		//IL_00e7: Unknown result type (might be due to invalid IL or missing references)
		//IL_00e9: Unknown result type (might be due to invalid IL or missing references)
		//IL_0100: Unknown result type (might be due to invalid IL or missing references)
		//IL_00d2: Unknown result type (might be due to invalid IL or missing references)
		Plant plant2 = plant;
		Variety variety = Varieties.FirstOrDefault(delegate(Variety v)
		{
			string text2 = SeedId(v);
			SeedDefinition seedDefinition = plant2.SeedDefinition;
			return text2 == ((seedDefinition != null) ? ((BaseItemDefinition)seedDefinition).ID : null);
		});
		if (variety == null)
		{
			return;
		}
		foreach (Renderer componentsInChild in ((Component)plant2).GetComponentsInChildren<Renderer>(true))
		{
			int instanceID = ((Object)componentsInChild).GetInstanceID();
			if (tinted.TryGetValue(instanceID, out Renderer value) && (Object)(object)value == (Object)(object)componentsInChild)
			{
				continue;
			}
			foreach (Material item in (Il2CppArrayBase<Material>)(object)componentsInChild.materials)
			{
				string text = (((Object)componentsInChild).name + ((Object)item).name).ToLowerInvariant();
				Color val2 = (item.color = ((text.Contains("leaf") || text.Contains("leaves") || text.Contains("stem")) ? variety.Leaf : variety.Bud));
				Color val3 = val2;
				if (item.HasProperty("_BaseColor"))
				{
					item.SetColor("_BaseColor", val3);
				}
			}
			tinted[instanceID] = componentsInChild;
		}
	}

	private static void Tint(GameObject root, Variety variety)
	{
		//IL_0063: Unknown result type (might be due to invalid IL or missing references)
		//IL_0069: Expected O, but got Unknown
		//IL_0048: Unknown result type (might be due to invalid IL or missing references)
		//IL_00c5: Unknown result type (might be due to invalid IL or missing references)
		//IL_00cb: Unknown result type (might be due to invalid IL or missing references)
		//IL_00d5: Unknown result type (might be due to invalid IL or missing references)
		//IL_00be: Unknown result type (might be due to invalid IL or missing references)
		foreach (Renderer componentsInChild in root.GetComponentsInChildren<Renderer>(true))
		{
			Il2CppReferenceArray<Material> sharedMaterials = componentsInChild.sharedMaterials;
			Material[] array = (Material[])(object)new Material[((Il2CppArrayBase<Material>)(object)sharedMaterials).Length];
			for (int i = 0; i < ((Il2CppArrayBase<Material>)(object)sharedMaterials).Length; i++)
			{
				if ((Object)(object)((Il2CppArrayBase<Material>)(object)sharedMaterials)[i] == (Object)null)
				{
					array[i] = Models.Material(variety.Leaf);
					continue;
				}
				array[i] = new Material(((Il2CppArrayBase<Material>)(object)sharedMaterials)[i]);
				string text = (((Object)componentsInChild).name + ((Object)((Il2CppArrayBase<Material>)(object)sharedMaterials)[i]).name).ToLowerInvariant();
				bool flag = text.Contains("leaf") || text.Contains("leaves") || text.Contains("stem");
				array[i].color = (flag ? Color.Lerp(Color.white, variety.Leaf, 0.55f) : variety.Bud);
			}
			componentsInChild.materials = Il2CppReferenceArray<Material>.op_Implicit(array);
		}
	}

	internal static void Register()
	{
		//IL_020c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0211: Unknown result type (might be due to invalid IL or missing references)
		//IL_0218: Unknown result type (might be due to invalid IL or missing references)
		//IL_021d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0227: Unknown result type (might be due to invalid IL or missing references)
		//IL_022c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0233: Unknown result type (might be due to invalid IL or missing references)
		//IL_0238: Unknown result type (might be due to invalid IL or missing references)
		//IL_024b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0250: Unknown result type (might be due to invalid IL or missing references)
		//IL_025a: Expected O, but got Unknown
		//IL_0270: Unknown result type (might be due to invalid IL or missing references)
		//IL_027a: Expected O, but got Unknown
		//IL_0290: Unknown result type (might be due to invalid IL or missing references)
		//IL_029a: Expected O, but got Unknown
		//IL_02b0: Unknown result type (might be due to invalid IL or missing references)
		//IL_02ba: Expected O, but got Unknown
		//IL_02d0: Unknown result type (might be due to invalid IL or missing references)
		//IL_02da: Expected O, but got Unknown
		List<(string, GameObject)> list = new List<(string, GameObject)>();
		if (Seeds.Count == 0)
		{
			Source = (from s in (IEnumerable<SeedDefinition>)Resources.FindObjectsOfTypeAll<SeedDefinition>()
				where (Object)(object)s != (Object)null && (Object)(object)s.PlantPrefab != (Object)null && (Object)(object)((Il2CppObjectBase)s.PlantPrefab).TryCast<WeedPlant>() != (Object)null && !((BaseItemDefinition)s).ID.StartsWith("vx_")
				orderby (!((BaseItemDefinition)s).ID.Equals("ogkushseed", StringComparison.OrdinalIgnoreCase)) ? 1 : 0
				select s).ThenBy<SeedDefinition, string>((SeedDefinition s) => ((BaseItemDefinition)s).ID, StringComparer.Ordinal).FirstOrDefault();
			if ((Object)(object)Source == (Object)null)
			{
				throw new InvalidOperationException("Aucune graine de cannabis native disponible");
			}
			SourceId = ((BaseItemDefinition)Source).ID;
			WeedDefinition val = (from p in (IEnumerable<WeedDefinition>)Resources.FindObjectsOfTypeAll<WeedDefinition>()
				where (Object)(object)p != (Object)null && !((BaseItemDefinition)p).ID.StartsWith("vx_")
				orderby (!((BaseItemDefinition)p).ID.Equals("ogkush", StringComparison.OrdinalIgnoreCase)) ? 1 : 0
				select p).ThenBy<WeedDefinition, string>((WeedDefinition p) => ((BaseItemDefinition)p).ID, StringComparer.Ordinal).FirstOrDefault();
			if ((Object)(object)val == (Object)null)
			{
				throw new InvalidOperationException("Aucune variété native disponible");
			}
			Variety[] varieties = Varieties;
			foreach (Variety variety in varieties)
			{
				WeedDefinition val2 = Object.Instantiate<WeedDefinition>(val);
				((BaseItemDefinition)val2).ID = "vx_" + variety.Key;
				((Object)val2).name = ((BaseItemDefinition)val2).ID;
				((BaseItemDefinition)val2).Name = variety.Name;
				((BaseItemDefinition)val2).Description = Localization.Text("Variété cultivée au Comptoir des Variétés : ", "Variety grown for Lucien's Variety Counter: ", "Im Sortentresen angebaute Sorte: ", "Variedad cultivada en el Mostrador de Variedades: ") + variety.Name;
				((ProductDefinition)val2).BasePrice = variety.BasePrice;
				((ProductDefinition)val2).MarketValue = variety.BasePrice;
				((PropertyItemDefinition)val2).Properties = new List<Effect>();
				((PropertyItemDefinition)val2).Properties.Add(Mixing.Native(Mod.Resolve(variety.Effect).ID));
				val2.appearance = new WeedAppearanceSettings(Color32.op_Implicit(variety.Bud), Color32.op_Implicit(Color.Lerp(variety.Bud, Color.white, 0.25f)), Color32.op_Implicit(variety.Leaf), new Color32((byte)113, (byte)133, (byte)66, byte.MaxValue));
				if ((Object)(object)val.MainMat != (Object)null)
				{
					val2.MainMat = new Material(val.MainMat);
				}
				if ((Object)(object)val.SecondaryMat != (Object)null)
				{
					val2.SecondaryMat = new Material(val.SecondaryMat);
				}
				if ((Object)(object)val.LeafMat != (Object)null)
				{
					val2.LeafMat = new Material(val.LeafMat);
				}
				if ((Object)(object)val.StemMat != (Object)null)
				{
					val2.StemMat = new Material(val.StemMat);
				}
				val2.ApplyAppearanceSettings();
				Object.DontDestroyOnLoad((Object)(object)val2);
				Products[((BaseItemDefinition)val2).ID] = val2;
				list.Add((((BaseItemDefinition)val2).ID, WeedPresentation.Configure(val2, val)));
				SeedDefinition val3 = Object.Instantiate<SeedDefinition>(Source);
				((BaseItemDefinition)val3).ID = SeedId(variety);
				((Object)val3).name = ((BaseItemDefinition)val3).ID;
				((BaseItemDefinition)val3).Name = Localization.Text("Graine ", "Seed: ", "Samen: ", "Semilla: ") + variety.Name;
				((StorableItemDefinition)val3).BasePurchasePrice = variety.SeedPrice;
				((BaseItemDefinition)val3).Description = Localization.Text("Variété de cannabis : ", "Cannabis variety: ", "Cannabissorte: ", "Variedad de cannabis: ") + variety.Name;
				GameObject val4 = Object.Instantiate<GameObject>(((Component)((StorableItemDefinition)Source).StoredItem).gameObject);
				GameObject item = val4;
				Presentation.Cache(val4);
				((StorableItemDefinition)val3).StoredItem = val4.GetComponent<StoredItem>();
				Equippable val5 = Object.Instantiate<Equippable>(((ItemDefinition)Source).Equippable);
				((Object)val5).name = "VX_SeedHeld_" + variety.Key;
				(((Il2CppObjectBase)val5).TryCast<Equippable_Seed>() ?? throw new InvalidOperationException("Équipement natif de graine incompatible")).Seed = val3;
				Presentation.Cache(((Component)val5).gameObject);
				((ItemDefinition)val3).Equippable = val5;
				Equippable_Viewmodel val6 = ((Il2CppObjectBase)val5).TryCast<Equippable_Viewmodel>();
				if ((Object)(object)((val6 != null) ? val6.AvatarEquippable : null) != (Object)null)
				{
					AvatarEquippable val7 = Object.Instantiate<AvatarEquippable>(val6.AvatarEquippable);
					((Object)val7).name = "VX_SeedAvatar_" + variety.Key;
					SeedPresentation.Apply(((Component)val7).gameObject, variety);
					string text2 = (val7.AssetPath = "Equippables/VarietesEtendues/seed_" + variety.Key);
					if (!AvatarEquippableRegistry.RegisterAvatarEquippable(text2, ((Component)val7).gameObject))
					{
						throw new InvalidOperationException("Présentation réseau de graine absente : " + variety.Key);
					}
					Presentation.Cache(((Component)val7).gameObject);
					val6.AvatarEquippable = val7;
				}
				FunctionalSeed val8 = Object.Instantiate<FunctionalSeed>(Source.FunctionSeedPrefab);
				((Object)val8).name = "VX_SeedSowing_" + variety.Key;
				SeedPresentation.Apply(((Component)val8).gameObject, variety);
				SeedPresentation.TintPhysicalSeed(val8, variety);
				SeedPresentation.Apply(((Component)val5).gameObject, variety);
				SeedPresentation.Apply(val4, variety);
				Presentation.Cache(((Component)val8).gameObject);
				val3.FunctionSeedPrefab = val8;
				list.Add((((BaseItemDefinition)val3).ID, item));
				Plant val9 = Object.Instantiate<Plant>(Source.PlantPrefab);
				((Object)val9).name = "VX_Plant_" + variety.Key;
				val9.SeedDefinition = val3;
				Tint(((Component)val9).gameObject, variety);
				WeedPlant val10 = ((Il2CppObjectBase)val9).Cast<WeedPlant>();
				if ((Object)(object)val10.BranchPrefab != (Object)null)
				{
					PlantHarvestable val11 = Object.Instantiate<PlantHarvestable>(val10.BranchPrefab);
					Tint(((Component)val11).gameObject, variety);
					Presentation.Cache(((Component)val11).gameObject);
					val10.BranchPrefab = val11;
				}
				Presentation.Cache(((Component)val9).gameObject);
				val3.PlantPrefab = val9;
				Object.DontDestroyOnLoad((Object)(object)val3);
				Seeds[((BaseItemDefinition)val3).ID] = val3;
			}
		}
		foreach (WeedDefinition value in Products.Values)
		{
			if (!Registry.ItemExists(((BaseItemDefinition)value).ID))
			{
				Singleton<Registry>.Instance.AddToRegistry((ItemDefinition)(object)value);
			}
		}
		foreach (SeedDefinition value2 in Seeds.Values)
		{
			if (!Registry.ItemExists(((BaseItemDefinition)value2).ID))
			{
				Singleton<Registry>.Instance.AddToRegistry((ItemDefinition)(object)value2);
			}
		}
		PopulateProducts(discover: false);
		BotanistSeeds.Register();
		foreach (var item2 in list)
		{
			Mod.Current?.QueueIcon(item2.Item1, item2.Item2);
		}
	}

	internal static void PopulateProducts(bool discover = true)
	{
		BotanistSeeds.Register();
		ProductManager instance = NetworkSingleton<ProductManager>.Instance;
		if ((Object)(object)instance == (Object)null)
		{
			return;
		}
		foreach (WeedDefinition value in Products.Values)
		{
			if (!Contains(instance.AllProducts, ((BaseItemDefinition)value).ID))
			{
				instance.AllProducts.Add((ProductDefinition)(object)value);
			}
			if (instance.DefaultKnownProducts != null && !Contains(instance.DefaultKnownProducts, ((BaseItemDefinition)value).ID))
			{
				instance.DefaultKnownProducts.Add((ProductDefinition)(object)value);
			}
			if (instance.ProductPrices != null && !instance.ProductPrices.ContainsKey((ProductDefinition)(object)value))
			{
				instance.ProductPrices.Add((ProductDefinition)(object)value, ((ProductDefinition)value).BasePrice);
			}
			if (discover && ProductManager.DiscoveredProducts != null && !Contains(ProductManager.DiscoveredProducts, ((BaseItemDefinition)value).ID))
			{
				if (((NetworkBehaviour)instance).IsServerInitialized)
				{
					instance.SetProductDiscovered((NetworkConnection)null, ((BaseItemDefinition)value).ID, false);
					continue;
				}
				ProductManager.DiscoveredProducts.Add((ProductDefinition)(object)value);
				instance.onProductDiscovered?.Invoke((ProductDefinition)(object)value);
			}
		}
	}

	private static bool Contains(List<ProductDefinition> products, string id)
	{
		Enumerator<ProductDefinition> enumerator = products.GetEnumerator();
		while (enumerator.MoveNext())
		{
			ProductDefinition current = enumerator.Current;
			if ((Object)(object)current != (Object)null && ((BaseItemDefinition)current).ID == id)
			{
				return true;
			}
		}
		return false;
	}
}
