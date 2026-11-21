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
	internal sealed record Variety(string Key, string Name, string Effect, int SeedPrice, int BasePrice, UnityEngine.Color Bud, UnityEngine.Color Leaf)
	{
		[CompilerGenerated]
		private bool PrintMembers(System.Text.StringBuilder builder)
		{
			System.Runtime.CompilerServices.RuntimeHelpers.EnsureSufficientExecutionStack();
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
			UnityEngine.Color val = Bud;
			builder.Append(val.ToString());
			builder.Append(", Leaf = ");
			val = Leaf;
			builder.Append(val.ToString());
			return true;
		}
	}

	internal static readonly Variety[] Varieties = new Variety[3]
	{
		new Variety("lemonhaze", "Lemon Haze", "Focused", 40, 38, new Color(0.84f, 0.72f, 0.18f), new Color(0.56f, 0.72f, 0.28f)),
		new Variety("blueberrydream", "Blueberry Dream", "Euphoric", 45, 40, new Color(0.48f, 0.32f, 0.65f), new Color(0.35f, 0.42f, 0.48f)),
		new Variety("dragonkush", "Dragon Kush", "Glowing", 70, 52, new Color(0.4f, 0.2f, 0.54f), new Color(0.42f, 0.34f, 0.4f))
	};

	internal static readonly System.Collections.Generic.Dictionary<string, Il2CppScheduleOne.Growing.SeedDefinition> Seeds = new System.Collections.Generic.Dictionary<string, Il2CppScheduleOne.Growing.SeedDefinition>(System.StringComparer.Ordinal);

	private static readonly System.Collections.Generic.Dictionary<string, Il2CppScheduleOne.Product.WeedDefinition> Products = new System.Collections.Generic.Dictionary<string, Il2CppScheduleOne.Product.WeedDefinition>(System.StringComparer.Ordinal);

	internal static Il2CppScheduleOne.Growing.SeedDefinition? Source;

	internal static string? SourceId;

	private static readonly System.Collections.Generic.Dictionary<int, UnityEngine.Renderer> tinted = new System.Collections.Generic.Dictionary<int, UnityEngine.Renderer>();

	internal static string SeedId(Variety v)
	{
		return "vx_" + v.Key + "seed";
	}

	internal static void ClearRuntime()
	{
		tinted.Clear();
	}

	internal static void RefreshPlant(Il2CppScheduleOne.Growing.Plant plant)
	{
		Il2CppScheduleOne.Growing.Plant plant2 = plant;
		Variety variety = Varieties.FirstOrDefault(delegate(Variety v)
		{
			string text2 = SeedId(v);
			Il2CppScheduleOne.Growing.SeedDefinition seedDefinition = plant2.SeedDefinition;
			return text2 == ((seedDefinition != null) ? ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)seedDefinition).ID : null);
		});
		if (variety == null)
		{
			return;
		}
		foreach (UnityEngine.Renderer componentsInChild in ((UnityEngine.Component)plant2).GetComponentsInChildren<UnityEngine.Renderer>(true))
		{
			int instanceID = ((UnityEngine.Object)componentsInChild).GetInstanceID();
			if (tinted.TryGetValue(instanceID, out UnityEngine.Renderer value) && (UnityEngine.Object)(object)value == (UnityEngine.Object)(object)componentsInChild)
			{
				continue;
			}
			foreach (UnityEngine.Material item in (Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.Material>)(object)componentsInChild.materials)
			{
				string text = (((UnityEngine.Object)componentsInChild).name + ((UnityEngine.Object)item).name).ToLowerInvariant();
				UnityEngine.Color val2 = (item.color = ((text.Contains("leaf") || text.Contains("leaves") || text.Contains("stem")) ? variety.Leaf : variety.Bud));
				UnityEngine.Color val3 = val2;
				if (item.HasProperty("_BaseColor"))
				{
					item.SetColor("_BaseColor", val3);
				}
			}
			tinted[instanceID] = componentsInChild;
		}
	}

	private static void Tint(UnityEngine.GameObject root, Variety variety)
	{
		foreach (UnityEngine.Renderer componentsInChild in root.GetComponentsInChildren<UnityEngine.Renderer>(true))
		{
			Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppReferenceArray<UnityEngine.Material> sharedMaterials = componentsInChild.sharedMaterials;
			UnityEngine.Material[] array = (UnityEngine.Material[])(object)new UnityEngine.Material[((Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.Material>)(object)sharedMaterials).Length];
			for (int i = 0; i < ((Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.Material>)(object)sharedMaterials).Length; i++)
			{
				if ((UnityEngine.Object)(object)((Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.Material>)(object)sharedMaterials)[i] == (UnityEngine.Object)null)
				{
					array[i] = Models.Material(variety.Leaf);
					continue;
				}
				array[i] = new Material(((Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.Material>)(object)sharedMaterials)[i]);
				string text = (((UnityEngine.Object)componentsInChild).name + ((UnityEngine.Object)((Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.Material>)(object)sharedMaterials)[i]).name).ToLowerInvariant();
				bool flag = text.Contains("leaf") || text.Contains("leaves") || text.Contains("stem");
				array[i].color = (flag ? Color.Lerp(UnityEngine.Color.white, variety.Leaf, 0.55f) : variety.Bud);
			}
			componentsInChild.materials = (array);
		}
	}

	internal static void Register()
	{
		System.Collections.Generic.List<(string, UnityEngine.GameObject)> list = new System.Collections.Generic.List<(string, UnityEngine.GameObject)>();
		if (Seeds.Count == 0)
		{
			Source = (from s in (System.Collections.Generic.IEnumerable<Il2CppScheduleOne.Growing.SeedDefinition>)UnityEngine.Resources.FindObjectsOfTypeAll<Il2CppScheduleOne.Growing.SeedDefinition>()
				where (UnityEngine.Object)(object)s != (UnityEngine.Object)null && (UnityEngine.Object)(object)s.PlantPrefab != (UnityEngine.Object)null && (UnityEngine.Object)(object)((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)s.PlantPrefab).TryCast<Il2CppScheduleOne.Growing.WeedPlant>() != (UnityEngine.Object)null && !((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)s).ID.StartsWith("vx_")
				orderby (!((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)s).ID.Equals("ogkushseed", System.StringComparison.OrdinalIgnoreCase)) ? 1 : 0
				select s).ThenBy<Il2CppScheduleOne.Growing.SeedDefinition, string>((Il2CppScheduleOne.Growing.SeedDefinition s) => ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)s).ID, System.StringComparer.Ordinal).FirstOrDefault();
			if ((UnityEngine.Object)(object)Source == (UnityEngine.Object)null)
			{
				throw new InvalidOperationException("Aucune graine de cannabis native disponible");
			}
			SourceId = ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)Source).ID;
			Il2CppScheduleOne.Product.WeedDefinition val = (from p in (System.Collections.Generic.IEnumerable<Il2CppScheduleOne.Product.WeedDefinition>)UnityEngine.Resources.FindObjectsOfTypeAll<Il2CppScheduleOne.Product.WeedDefinition>()
				where (UnityEngine.Object)(object)p != (UnityEngine.Object)null && !((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)p).ID.StartsWith("vx_")
				orderby (!((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)p).ID.Equals("ogkush", System.StringComparison.OrdinalIgnoreCase)) ? 1 : 0
				select p).ThenBy<Il2CppScheduleOne.Product.WeedDefinition, string>((Il2CppScheduleOne.Product.WeedDefinition p) => ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)p).ID, System.StringComparer.Ordinal).FirstOrDefault();
			if ((UnityEngine.Object)(object)val == (UnityEngine.Object)null)
			{
				throw new InvalidOperationException("Aucune variété native disponible");
			}
			Variety[] varieties = Varieties;
			foreach (Variety variety in varieties)
			{
				Il2CppScheduleOne.Product.WeedDefinition val2 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.Product.WeedDefinition>(val);
				((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2).ID = "vx_" + variety.Key;
				((UnityEngine.Object)val2).name = ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2).ID;
				((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2).Name = variety.Name;
				((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2).Description = Localization.Text("Variété cultivée au Comptoir des Variétés : ", "Variety grown for Lucien's Variety Counter: ", "Im Sortentresen angebaute Sorte: ", "Variedad cultivada en el Mostrador de Variedades: ") + variety.Name;
				((Il2CppScheduleOne.Product.ProductDefinition)val2).BasePrice = variety.BasePrice;
				((Il2CppScheduleOne.Product.ProductDefinition)val2).MarketValue = variety.BasePrice;
				((Il2CppScheduleOne.Product.PropertyItemDefinition)val2).Properties = new Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect>();
				((Il2CppScheduleOne.Product.PropertyItemDefinition)val2).Properties.Add(Mixing.Native(Mod.Resolve(variety.Effect).ID));
				val2.appearance = new WeedAppearanceSettings((variety.Bud), (UnityEngine.Color.Lerp(variety.Bud, UnityEngine.Color.white, 0.25f)), (variety.Leaf), new Color32((byte)113, (byte)133, (byte)66, byte.MaxValue));
				if ((UnityEngine.Object)(object)val.MainMat != (UnityEngine.Object)null)
				{
					val2.MainMat = new Material(val.MainMat);
				}
				if ((UnityEngine.Object)(object)val.SecondaryMat != (UnityEngine.Object)null)
				{
					val2.SecondaryMat = new Material(val.SecondaryMat);
				}
				if ((UnityEngine.Object)(object)val.LeafMat != (UnityEngine.Object)null)
				{
					val2.LeafMat = new Material(val.LeafMat);
				}
				if ((UnityEngine.Object)(object)val.StemMat != (UnityEngine.Object)null)
				{
					val2.StemMat = new Material(val.StemMat);
				}
				val2.ApplyAppearanceSettings();
				UnityEngine.Object.DontDestroyOnLoad((UnityEngine.Object)(object)val2);
				Products[((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2).ID] = val2;
				list.Add((((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2).ID, WeedPresentation.Configure(val2, val)));
				Il2CppScheduleOne.Growing.SeedDefinition val3 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.Growing.SeedDefinition>(Source);
				((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val3).ID = SeedId(variety);
				((UnityEngine.Object)val3).name = ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val3).ID;
				((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val3).Name = Localization.Text("Graine ", "Seed: ", "Samen: ", "Semilla: ") + variety.Name;
				((Il2CppScheduleOne.ItemFramework.StorableItemDefinition)val3).BasePurchasePrice = variety.SeedPrice;
				((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val3).Description = Localization.Text("Variété de cannabis : ", "Cannabis variety: ", "Cannabissorte: ", "Variedad de cannabis: ") + variety.Name;
				UnityEngine.GameObject val4 = UnityEngine.Object.Instantiate<UnityEngine.GameObject>(((UnityEngine.Component)((Il2CppScheduleOne.ItemFramework.StorableItemDefinition)Source).StoredItem).gameObject);
				UnityEngine.GameObject item = val4;
				Presentation.Cache(val4);
				((Il2CppScheduleOne.ItemFramework.StorableItemDefinition)val3).StoredItem = val4.GetComponent<Il2CppScheduleOne.Storage.StoredItem>();
				Il2CppScheduleOne.Equipping.Equippable val5 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.Equipping.Equippable>(((Il2CppScheduleOne.ItemFramework.ItemDefinition)Source).Equippable);
				((UnityEngine.Object)val5).name = "VX_SeedHeld_" + variety.Key;
				(((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val5).TryCast<Il2CppScheduleOne.Equipping.Equippable_Seed>() ?? throw new InvalidOperationException("Équipement natif de graine incompatible")).Seed = val3;
				Presentation.Cache(((UnityEngine.Component)val5).gameObject);
				((Il2CppScheduleOne.ItemFramework.ItemDefinition)val3).Equippable = val5;
				Il2CppScheduleOne.Equipping.Equippable_Viewmodel val6 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val5).TryCast<Il2CppScheduleOne.Equipping.Equippable_Viewmodel>();
				if ((UnityEngine.Object)(object)((val6 != null) ? val6.AvatarEquippable : null) != (UnityEngine.Object)null)
				{
					Il2CppScheduleOne.AvatarFramework.Equipping.AvatarEquippable val7 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.AvatarFramework.Equipping.AvatarEquippable>(val6.AvatarEquippable);
					((UnityEngine.Object)val7).name = "VX_SeedAvatar_" + variety.Key;
					SeedPresentation.Apply(((UnityEngine.Component)val7).gameObject, variety);
					string text2 = (val7.AssetPath = "Equippables/VarietesEtendues/seed_" + variety.Key);
					if (!S1API.Items.AvatarEquippableRegistry.RegisterAvatarEquippable(text2, ((UnityEngine.Component)val7).gameObject))
					{
						throw new InvalidOperationException("Présentation réseau de graine absente : " + variety.Key);
					}
					Presentation.Cache(((UnityEngine.Component)val7).gameObject);
					val6.AvatarEquippable = val7;
				}
				Il2CppScheduleOne.Growing.FunctionalSeed val8 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.Growing.FunctionalSeed>(Source.FunctionSeedPrefab);
				((UnityEngine.Object)val8).name = "VX_SeedSowing_" + variety.Key;
				SeedPresentation.Apply(((UnityEngine.Component)val8).gameObject, variety);
				SeedPresentation.TintPhysicalSeed(val8, variety);
				SeedPresentation.Apply(((UnityEngine.Component)val5).gameObject, variety);
				SeedPresentation.Apply(val4, variety);
				Presentation.Cache(((UnityEngine.Component)val8).gameObject);
				val3.FunctionSeedPrefab = val8;
				list.Add((((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val3).ID, item));
				Il2CppScheduleOne.Growing.Plant val9 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.Growing.Plant>(Source.PlantPrefab);
				((UnityEngine.Object)val9).name = "VX_Plant_" + variety.Key;
				val9.SeedDefinition = val3;
				Tint(((UnityEngine.Component)val9).gameObject, variety);
				Il2CppScheduleOne.Growing.WeedPlant val10 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val9).Cast<Il2CppScheduleOne.Growing.WeedPlant>();
				if ((UnityEngine.Object)(object)val10.BranchPrefab != (UnityEngine.Object)null)
				{
					Il2CppScheduleOne.Growing.PlantHarvestable val11 = UnityEngine.Object.Instantiate<Il2CppScheduleOne.Growing.PlantHarvestable>(val10.BranchPrefab);
					Tint(((UnityEngine.Component)val11).gameObject, variety);
					Presentation.Cache(((UnityEngine.Component)val11).gameObject);
					val10.BranchPrefab = val11;
				}
				Presentation.Cache(((UnityEngine.Component)val9).gameObject);
				val3.PlantPrefab = val9;
				UnityEngine.Object.DontDestroyOnLoad((UnityEngine.Object)(object)val3);
				Seeds[((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val3).ID] = val3;
			}
		}
		foreach (Il2CppScheduleOne.Product.WeedDefinition value in Products.Values)
		{
			if (!Il2CppScheduleOne.Registry.ItemExists(((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)value).ID))
			{
				Il2CppScheduleOne.DevUtilities.Singleton<Il2CppScheduleOne.Registry>.Instance.AddToRegistry((Il2CppScheduleOne.ItemFramework.ItemDefinition)(object)value);
			}
		}
		foreach (Il2CppScheduleOne.Growing.SeedDefinition value2 in Seeds.Values)
		{
			if (!Il2CppScheduleOne.Registry.ItemExists(((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)value2).ID))
			{
				Il2CppScheduleOne.DevUtilities.Singleton<Il2CppScheduleOne.Registry>.Instance.AddToRegistry((Il2CppScheduleOne.ItemFramework.ItemDefinition)(object)value2);
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
		Il2CppScheduleOne.Product.ProductManager instance = Il2CppScheduleOne.DevUtilities.NetworkSingleton<Il2CppScheduleOne.Product.ProductManager>.Instance;
		if ((UnityEngine.Object)(object)instance == (UnityEngine.Object)null)
		{
			return;
		}
		foreach (Il2CppScheduleOne.Product.WeedDefinition value in Products.Values)
		{
			if (!Contains(instance.AllProducts, ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)value).ID))
			{
				instance.AllProducts.Add((Il2CppScheduleOne.Product.ProductDefinition)(object)value);
			}
			if (instance.DefaultKnownProducts != null && !Contains(instance.DefaultKnownProducts, ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)value).ID))
			{
				instance.DefaultKnownProducts.Add((Il2CppScheduleOne.Product.ProductDefinition)(object)value);
			}
			if (instance.ProductPrices != null && !instance.ProductPrices.ContainsKey((Il2CppScheduleOne.Product.ProductDefinition)(object)value))
			{
				instance.ProductPrices.Add((Il2CppScheduleOne.Product.ProductDefinition)(object)value, ((Il2CppScheduleOne.Product.ProductDefinition)value).BasePrice);
			}
			if (discover && Il2CppScheduleOne.Product.ProductManager.DiscoveredProducts != null && !Contains(Il2CppScheduleOne.Product.ProductManager.DiscoveredProducts, ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)value).ID))
			{
				if (((Il2CppFishNet.Object.NetworkBehaviour)instance).IsServerInitialized)
				{
					instance.SetProductDiscovered((Il2CppFishNet.Connection.NetworkConnection)null, ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)value).ID, false);
					continue;
				}
				Il2CppScheduleOne.Product.ProductManager.DiscoveredProducts.Add((Il2CppScheduleOne.Product.ProductDefinition)(object)value);
				instance.onProductDiscovered?.Invoke((Il2CppScheduleOne.Product.ProductDefinition)(object)value);
			}
		}
	}

	private static bool Contains(Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Product.ProductDefinition> products, string id)
	{
		var enumerator = products.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Il2CppScheduleOne.Product.ProductDefinition current = enumerator.Current;
			if ((UnityEngine.Object)(object)current != (UnityEngine.Object)null && ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)current).ID == id)
			{
				return true;
			}
		}
		return false;
	}
}
