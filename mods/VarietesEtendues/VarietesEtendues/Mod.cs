using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Reflection;
using System.Text.Json;
using Il2CppFishNet.Object;
using Il2CppInterop.Runtime.InteropTypes;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne;
using Il2CppScheduleOne.Core.Items.Framework;
using Il2CppScheduleOne.DevUtilities;
using Il2CppScheduleOne.Economy;
using Il2CppScheduleOne.Effects;
using Il2CppScheduleOne.ItemFramework;
using Il2CppScheduleOne.Product;
using Il2CppScheduleOne.UI.Shop;
using Il2CppSystem.Collections.Generic;
using MelonLoader;
using MelonLoader.Utils;
using S1API.Entities;
using S1API.Items;
using S1API.Items.Ingredient;
using S1API.Items.Storable;
using S1API.Lifecycle;
using S1API.Properties;
using S1API.Properties.Interfaces;
using S1API.Rendering;
using S1API.Shops;
using UnityEngine;

namespace VarietesEtendues;

public sealed class Mod : MelonMod
{
	internal static Mod? Current;

	internal static Entry[] Catalogue = LoadCatalogue();

	private bool registered;

	private bool sceneReady;

	private readonly System.Collections.Generic.List<(S1API.Items.ItemDefinition item, UnityEngine.GameObject model)> icons = new System.Collections.Generic.List<(S1API.Items.ItemDefinition, UnityEngine.GameObject)>();

	private float iconRetry;

	private float supplierCheck;

	private float nextEffectCheck;

	private float validationCheck;

	private int supplierAttempts;

	private static Entry[] LoadCatalogue()
	{
		using System.IO.Stream utf8Json = typeof(Mod).Assembly.GetManifestResourceStream("VarietesEtendues.catalogue.json");
		return System.Text.Json.JsonSerializer.Deserialize<Entry[]>(utf8Json);
	}

	public override void OnInitializeMelon()
	{
		Current = this;
		Settings.Initialize();
		Localization.Initialize();
		using System.IO.Stream utf8Json = System.Reflection.Assembly.GetExecutingAssembly().GetManifestResourceStream("VarietesEtendues.catalogue.json");
		Catalogue = System.Text.Json.JsonSerializer.Deserialize<Entry[]>(utf8Json);
		if (Catalogue.Length != 19 || Catalogue.Select((Entry x) => x.Key).Distinct().Count() != 19)
		{
			throw new InvalidDataException("Catalogue incomplet ou identifiants dupliqués.");
		}
		S1API.Lifecycle.GameLifecycle.OnPreLoad += RegisterContent;
		S1API.Lifecycle.GameLifecycle.OnPreSceneChange += Devil.ClearAll;
		S1API.Lifecycle.GameLifecycle.OnPreSceneChange += IngredientEffects.ClearAll;
		S1API.Lifecycle.GameLifecycle.OnPreSceneChange += SupplierUI.Clear;
		S1API.Lifecycle.GameLifecycle.OnPreSceneChange += DevilShop.Clear;
		S1API.Lifecycle.GameLifecycle.OnPreSceneChange += Crops.ClearRuntime;
		S1API.Lifecycle.GameLifecycle.OnPreSceneChange += LucienCounter.Clear;
		S1API.Lifecycle.GameLifecycle.OnPreSceneChange += delegate
		{
			sceneReady = false;
			validationCheck = 0f;
			supplierCheck = 0f;
		};
		S1API.Lifecycle.GameLifecycle.OnLoadComplete += delegate
		{
			sceneReady = true;
			Crops.PopulateProducts();
			validationCheck = (Settings.Diagnostics ? (UnityEngine.Time.unscaledTime + 3f) : 0f);
			supplierAttempts = 0;
			supplierCheck = UnityEngine.Time.unscaledTime + 5f;
		};
		MelonLoader.MelonLogger.Msg("19 ingrédients configurés. Prototype IL2CPP — validation coop requise.");
	}

	internal void QueueIcon(string id, UnityEngine.GameObject model)
	{
		icons.Add((S1API.Items.ItemManager.GetDefinition(id) ?? throw new InvalidOperationException("Objet d'icône absent : " + id), model));
	}

	private void RegisterContent()
	{
		if (registered)
		{
			Crops.Register();
			return;
		}
		S1API.Properties.CustomEffect val = new CustomEffectBuilder().WithBasicInfo("vx_diable", Localization.Text("Diable", "Devil", "Teufel", "Diablo"), Localization.Text("Corps noir, yeux et cornes rouges, fumée et grondement. Invulnérabilité et gravité réduite pendant l'effet. Alt + molette : vitesse du sprint.", "Black body, red eyes and horns, smoke and growl. Invulnerability and reduced gravity for the effect duration. Alt + wheel: sprint speed.", "Schwarzer Körper, rote Augen und Hörner, Rauch und Grollen. Unverwundbarkeit und verringerte Schwerkraft während der Wirkung. Alt + Mausrad: Sprinttempo.", "Cuerpo negro, ojos y cuernos rojos, humo y gruñido. Invulnerabilidad y gravedad reducida durante el efecto. Alt + rueda: velocidad de sprint.")).WithTier(5).WithColors(new Color(0.12f, 0.01f, 0.01f), UnityEngine.Color.red)
			.WithValue(0, 1f, 0.66f)
			.WithBehavior((System.Action<S1API.Entities.Player>)delegate(S1API.Entities.Player p)
			{
				Devil.Apply(p.Transform);
			})
			.WithNpcBehavior((System.Action<S1API.Entities.NPC>)delegate(S1API.Entities.NPC n)
			{
				Devil.Apply(n.Transform);
			})
			.WithClearBehavior((System.Action<S1API.Entities.Player>)delegate(S1API.Entities.Player p)
			{
				Devil.Clear(p.Transform);
			})
			.WithNpcClearBehavior((System.Action<S1API.Entities.NPC>)delegate(S1API.Entities.NPC n)
			{
				Devil.Clear(n.Transform);
			})
			.Build();
		Entry[] catalogue = Catalogue;
		foreach (Entry entry in catalogue)
		{
			Il2CppScheduleOne.ItemFramework.StorableItemDefinition obj = Presentation.Template(entry);
			UnityEngine.GameObject val2 = UnityEngine.Object.Instantiate<UnityEngine.GameObject>(((UnityEngine.Component)obj.StoredItem).gameObject);
			((UnityEngine.Object)val2).name = "VX_" + entry.Key;
			UnityEngine.GameObject item = Presentation.ReplaceMeshes(val2, entry);
			Presentation.Cache(val2);
			S1API.Properties.Interfaces.PropertyBase val3;
			if (entry.Key == "pill666")
			{
				val3 = (S1API.Properties.Interfaces.PropertyBase)(object)val;
			}
			else
			{
				val3 = NewEffects.Build(entry);
				Mixing.Ingredients[val3.ID] = entry;
			}
			S1API.Items.Ingredient.MixIngredientDefinition val4 = ((S1API.Items.Storable.StorableItemDefinitionBuilderBase<S1API.Items.Ingredient.MixIngredientDefinitionBuilder>)(object)((S1API.Items.Storable.StorableItemDefinitionBuilderBase<S1API.Items.Ingredient.MixIngredientDefinitionBuilder>)(object)((S1API.Items.Storable.StorableItemDefinitionBuilderBase<S1API.Items.Ingredient.MixIngredientDefinitionBuilder>)(object)((S1API.Items.Storable.StorableItemDefinitionBuilderBase<S1API.Items.Ingredient.MixIngredientDefinitionBuilder>)(object)S1API.Items.Ingredient.MixIngredientItemCreator.CreateBuilder()).WithBasicInfo("vx_" + entry.Key, entry.DisplayName, Localization.Text("Ingrédient du Comptoir 666. Effet : ", "Ingredient from the 666 Counter. Effect: ", "Zutat vom 666-Tresen. Wirkung: ", "Ingrediente del Mostrador 666. Efecto: ") + ((entry.Key == "pill666") ? Localization.Text("Diable", "Devil", "Teufel", "Diablo") : NewEffects.For(entry.Key).Name), (S1API.Items.ItemCategory)9)).WithPricing((float)entry.Price, 0.5f)).WithStackLimit(20).WithEffect(val3)).WithStoredItem(val2).Build();
			icons.Add(((S1API.Items.ItemDefinition)(object)val4, item));
			Il2CppScheduleOne.ItemFramework.StorableItemDefinition target = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)Il2CppScheduleOne.Registry.GetItem(((S1API.Items.ItemDefinition)val4).ID)).Cast<Il2CppScheduleOne.ItemFramework.StorableItemDefinition>();
			Presentation.ConfigureHeld(obj, target, entry);
			MelonLoader.MelonLogger.Msg($"Enregistré : {((S1API.Items.ItemDefinition)val4).ID} — {entry.Price} $");
		}
		Crops.Register();
		registered = true;
	}

	public override void OnUpdate()
	{
		if (registered && sceneReady)
		{
			DevilShop.Tick();
		}
		if (validationCheck > 0f && UnityEngine.Time.unscaledTime >= validationCheck)
		{
			validationCheck = 0f;
			ValidateRuntime();
			try
			{
				Regression.Run();
			}
			catch (System.Exception ex)
			{
				MelonLoader.MelonLogger.Error("RÉGRESSION ÉCHOUÉE : " + ex);
			}
		}
		if (registered && sceneReady && UnityEngine.Time.unscaledTime >= nextEffectCheck)
		{
			nextEffectCheck = UnityEngine.Time.unscaledTime + 0.5f;
			DevilReplication.ReconcilePlayers();
			foreach (Lucien item in S1API.Entities.NPC.All.OfType<Lucien>())
			{
				LucienCounter.Refresh(item);
				if (((S1API.Entities.NPC)item).IsVisible)
				{
					CharacterEffects.Add(((S1API.Entities.NPC)item).Transform, "vx_diable", permanent: true);
				}
				else
				{
					Devil.Clear(((S1API.Entities.NPC)item).Transform);
				}
				DevilPortrait.Capture(item);
			}
		}
		if (supplierCheck > 0f && UnityEngine.Time.unscaledTime >= supplierCheck)
		{
			supplierCheck = 0f;
			try
			{
				Lucien lucien = S1API.Entities.NPC.All.OfType<Lucien>().FirstOrDefault();
				if (lucien == null)
				{
					throw new InvalidOperationException("Lucien absent de NPC.All");
				}
				((S1API.Entities.NPC)lucien).FirstName = "Lucien";
				((S1API.Entities.NPC)lucien).LastName = "";
				((S1API.Entities.NPC)lucien).RefreshMessagingIcons();
				((S1API.Entities.NPC)lucien).Supplier.Unlock();
				if (((S1API.Entities.NPC)lucien).Relationship.Delta < 5f)
				{
					((S1API.Entities.NPC)lucien).Relationship.Add(5f - ((S1API.Entities.NPC)lucien).Relationship.Delta, true);
				}
				S1API.Shops.Shop val = ((S1API.Entities.NPC)lucien).Supplier.Shop ?? throw new InvalidOperationException("Catalogue de Lucien indisponible");
				Entry[] catalogue = Catalogue;
				foreach (Entry entry in catalogue)
				{
					if (!val.HasItem("vx_" + entry.Key))
					{
						val.AddItem(S1API.Items.ItemManager.GetDefinition("vx_" + entry.Key) ?? throw new InvalidOperationException("Objet absent : " + entry.Key), (float?)entry.Price);
					}
				}
				Crops.Variety[] varieties = Crops.Varieties;
				foreach (Crops.Variety variety in varieties)
				{
					if (!val.HasItem(Crops.SeedId(variety)))
					{
						val.AddItem(S1API.Items.ItemManager.GetDefinition(Crops.SeedId(variety)), (float?)variety.SeedPrice);
					}
				}
				Il2CppScheduleOne.Economy.Supplier component = ((S1API.Entities.NPC)lucien).gameObject.GetComponent<Il2CppScheduleOne.Economy.Supplier>();
				object obj;
				if (component == null)
				{
					obj = null;
				}
				else
				{
					Il2CppScheduleOne.UI.Shop.ShopInterface shop = component.Shop;
					obj = ((shop != null) ? shop.Listings : null);
				}
				if (obj != null)
				{
					var enumerator2 = component.Shop.Listings.GetEnumerator();
					while (enumerator2.MoveNext())
					{
						Il2CppScheduleOne.UI.Shop.ShopListing current2 = enumerator2.Current;
						if ((UnityEngine.Object)(object)current2.Item != (UnityEngine.Object)null && ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)current2.Item).ID.StartsWith("vx_", System.StringComparison.Ordinal))
						{
							current2.CanBeDelivered = true;
							current2.LimitedStock = false;
							current2.DefaultStock = -1;
						}
					}
				}
				if ((UnityEngine.Object)(object)component != (UnityEngine.Object)null && ((Il2CppFishNet.Object.NetworkBehaviour)component).IsServerInitialized && !component.DeliveriesEnabled)
				{
					component.RelationshipChange(0f);
				}
				S1API.Shops.Shop shop2 = ((S1API.Entities.NPC)lucien).Supplier.Shop;
				string[] array = ((shop2 != null) ? shop2.GetItemIds() : null) ?? Array.Empty<string>();
				MelonLoader.MelonLogger.Msg($"FOURNISSEUR : Lucien ; catalogue={array.Length} articles ; livraisons={((S1API.Entities.NPC)lucien).Supplier.DeliveriesEnabled}");
			}
			catch (System.Exception ex2)
			{
				if (++supplierAttempts < 20)
				{
					supplierCheck = UnityEngine.Time.unscaledTime + 3f;
				}
				if (supplierAttempts == 1 || supplierAttempts % 5 == 0)
				{
					MelonLoader.MelonLogger.Warning("FOURNISSEUR en attente : " + ex2.Message);
				}
			}
		}
		if (!registered || !sceneReady || UnityEngine.Time.unscaledTime < iconRetry || icons.Count == 0)
		{
			return;
		}
		iconRetry = UnityEngine.Time.unscaledTime + 3f;
		Il2CppScheduleOne.DevUtilities.IconGenerator instance = Il2CppScheduleOne.DevUtilities.Singleton<Il2CppScheduleOne.DevUtilities.IconGenerator>.Instance;
		if ((UnityEngine.Object)(object)instance == (UnityEngine.Object)null || (UnityEngine.Object)(object)instance.CameraPosition == (UnityEngine.Object)null || (UnityEngine.Object)(object)instance.MainContainer == (UnityEngine.Object)null || (UnityEngine.Object)(object)instance.ItemContainer == (UnityEngine.Object)null)
		{
			return;
		}
		for (int num = icons.Count - 1; num >= 0; num--)
		{
			try
			{
				UnityEngine.GameObject val2 = UnityEngine.Object.Instantiate<UnityEngine.GameObject>(icons[num].model);
				Presentation.Cache(val2);
				UnityEngine.Sprite val3;
				try
				{
					val3 = S1API.Rendering.IconFactory.GenerateIconSprite(val2.transform, 512, true, true, (icons[num].item.ID == "vx_pill666") ? 0.94f : 0.86f);
				}
				finally
				{
					UnityEngine.Object.Destroy((UnityEngine.Object)(object)val2);
				}
				if ((UnityEngine.Object)(object)val3 != (UnityEngine.Object)null)
				{
					icons[num].item.Icon = val3;
					string text = System.IO.Path.Combine(MelonLoader.Utils.MelonEnvironment.UserDataDirectory, "VarietesEtendues", "Apercus");
					System.IO.Directory.CreateDirectory(text);
					System.IO.File.WriteAllBytes(System.IO.Path.Combine(text, icons[num].item.ID + ".png"), ((Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<byte>)(object)UnityEngine.ImageConversion.EncodeToPNG(val3.texture)));
					MelonLoader.MelonLogger.Msg("Icône 3D prête : " + icons[num].item.ID);
					icons.RemoveAt(num);
				}
			}
			catch (System.Exception ex3)
			{
				MelonLoader.MelonLogger.Warning("Icône à réessayer : " + ex3.Message);
			}
		}
	}

	public override void OnGUI()
	{
		DevilShop.Draw();
		GameplayEffects.DrawControls();
	}

	public override void OnDeinitializeMelon()
	{
		CharacterEffects.ClearAll();
		DevilShop.Clear();
		Current = null;
	}

	public override void OnLateUpdate()
	{
		if (registered && sceneReady)
		{
			Devil.Tick();
			IngredientEffects.Tick();
		}
	}

	internal static S1API.Properties.Interfaces.PropertyBase Resolve(string name)
	{
		return (S1API.Properties.Interfaces.PropertyBase)(name switch
		{
			"Glowing" => Property.Glowie, 
			"Schizophrenia" => Property.Schizophrenic, 
			"Seizure-Inducing" => Property.Seizure, 
			_ => (object)(S1API.Properties.Interfaces.PropertyBase)(typeof(S1API.Properties.Property).GetField(name.Replace("-", "").Replace(" ", ""))?.GetValue(null) ?? throw new InvalidDataException("Effet inconnu : " + name)), 
		});
	}

	private void ValidateRuntime()
	{
		try
		{
			int num = 0;
			int num2 = 0;
			Entry[] catalogue = Catalogue;
			foreach (Entry entry in catalogue)
			{
				Il2CppScheduleOne.ItemFramework.ItemDefinition item = Il2CppScheduleOne.Registry.GetItem("vx_" + entry.Key);
				Il2CppScheduleOne.ItemFramework.StorableItemDefinition val = ((item != null) ? ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)item).TryCast<Il2CppScheduleOne.ItemFramework.StorableItemDefinition>() : null);
				if ((UnityEngine.Object)(object)val == (UnityEngine.Object)null || (UnityEngine.Object)(object)val.StoredItem == (UnityEngine.Object)null || (UnityEngine.Object)(object)((Il2CppScheduleOne.ItemFramework.ItemDefinition)val).Equippable == (UnityEngine.Object)null || UnityEngine.Mathf.Abs(val.BasePurchasePrice - (float)entry.Price) > 0.01f)
				{
					throw new InvalidDataException("Objet incomplet : " + entry.Key);
				}
				num++;
				if (entry.Key == "pill666")
				{
					continue;
				}
				Il2CppScheduleOne.Effects.Effect val2 = ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val).Cast<Il2CppScheduleOne.Product.PropertyItemDefinition>().Properties[0];
				string[][] rules = entry.Rules;
				foreach (string[] array in rules)
				{
					Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> val3 = new Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect>();
					val3.Add(Mixing.Native(Resolve(array[0]).ID));
					Il2CppSystem.Collections.Generic.List<Il2CppScheduleOne.Effects.Effect> obj = Il2CppScheduleOne.Effects.EffectMixCalculator.MixProperties(val3, val2, (Il2CppScheduleOne.Product.EDrugType)0);
					bool flag = false;
					var enumerator = obj.GetEnumerator();
					while (enumerator.MoveNext())
					{
						if (string.Equals(enumerator.Current.ID, Mixing.Native(Resolve(array[1]).ID).ID, System.StringComparison.OrdinalIgnoreCase))
						{
							flag = true;
						}
					}
					if (!flag || val3.Count != 1 || !string.Equals(val3[0].ID, Mixing.Native(Resolve(array[0]).ID).ID, System.StringComparison.OrdinalIgnoreCase))
					{
						throw new InvalidDataException("Mélange invalide : " + entry.Key + " / " + array[0]);
					}
					num2++;
				}
			}
			MelonLoader.MelonLogger.Msg($"VALIDATION : {num}/19 objets complets ; {num2} transformations de mélange réussies.");
			UnityEngine.GameObject val4 = new GameObject("VX_VerificationEffet");
			val4.transform.position = new Vector3(0f, 10000f, 0f);
			val4.transform.localScale = UnityEngine.Vector3.one * 100f;
			try
			{
				Devil.Apply(val4.transform);
				Devil.Tick();
				if (val4.GetComponentsInChildren<UnityEngine.ParticleSystem>().Length < 2)
				{
					throw new InvalidDataException("Fumée de l'effet Diable absente");
				}
				foreach (UnityEngine.MeshRenderer componentsInChild in val4.GetComponentsInChildren<UnityEngine.MeshRenderer>())
				{
					UnityEngine.Bounds bounds = ((UnityEngine.Renderer)componentsInChild).bounds;
					float x = bounds.size.x;
					bounds = ((UnityEngine.Renderer)componentsInChild).bounds;
					float y = bounds.size.y;
					bounds = ((UnityEngine.Renderer)componentsInChild).bounds;
					if (UnityEngine.Mathf.Max(x, UnityEngine.Mathf.Max(y, bounds.size.z)) > 0.5f)
					{
						throw new InvalidDataException("L'effet Diable hérite d'une échelle de squelette excessive");
					}
				}
				Devil.Clear(val4.transform);
				MelonLoader.MelonLogger.Msg("VALIDATION : effet Diable créé puis retiré sur un objet de test isolé.");
			}
			finally
			{
				Devil.Clear(val4.transform);
				UnityEngine.Object.Destroy((UnityEngine.Object)(object)val4);
			}
		}
		catch (System.Exception ex)
		{
			MelonLoader.MelonLogger.Error("VALIDATION ÉCHOUÉE : " + ex);
		}
	}
}
