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

	private readonly List<(ItemDefinition item, GameObject model)> icons = new List<(ItemDefinition, GameObject)>();

	private float iconRetry;

	private float supplierCheck;

	private float nextEffectCheck;

	private float validationCheck;

	private int supplierAttempts;

	private static Entry[] LoadCatalogue()
	{
		using Stream utf8Json = typeof(Mod).Assembly.GetManifestResourceStream("VarietesEtendues.catalogue.json");
		return JsonSerializer.Deserialize<Entry[]>(utf8Json);
	}

	public override void OnInitializeMelon()
	{
		Current = this;
		Settings.Initialize();
		Localization.Initialize();
		using Stream utf8Json = Assembly.GetExecutingAssembly().GetManifestResourceStream("VarietesEtendues.catalogue.json");
		Catalogue = JsonSerializer.Deserialize<Entry[]>(utf8Json);
		if (Catalogue.Length != 19 || Catalogue.Select((Entry x) => x.Key).Distinct().Count() != 19)
		{
			throw new InvalidDataException("Catalogue incomplet ou identifiants dupliqués.");
		}
		GameLifecycle.OnPreLoad += RegisterContent;
		GameLifecycle.OnPreSceneChange += Devil.ClearAll;
		GameLifecycle.OnPreSceneChange += IngredientEffects.ClearAll;
		GameLifecycle.OnPreSceneChange += SupplierUI.Clear;
		GameLifecycle.OnPreSceneChange += DevilShop.Clear;
		GameLifecycle.OnPreSceneChange += Crops.ClearRuntime;
		GameLifecycle.OnPreSceneChange += LucienCounter.Clear;
		GameLifecycle.OnPreSceneChange += delegate
		{
			sceneReady = false;
			validationCheck = 0f;
			supplierCheck = 0f;
		};
		GameLifecycle.OnLoadComplete += delegate
		{
			sceneReady = true;
			Crops.PopulateProducts();
			validationCheck = (Settings.Diagnostics ? (Time.unscaledTime + 3f) : 0f);
			supplierAttempts = 0;
			supplierCheck = Time.unscaledTime + 5f;
		};
		MelonLogger.Msg("19 ingrédients configurés. Prototype IL2CPP — validation coop requise.");
	}

	internal void QueueIcon(string id, GameObject model)
	{
		icons.Add((ItemManager.GetDefinition(id) ?? throw new InvalidOperationException("Objet d'icône absent : " + id), model));
	}

	private void RegisterContent()
	{
		//IL_000e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0050: Unknown result type (might be due to invalid IL or missing references)
		//IL_0055: Unknown result type (might be due to invalid IL or missing references)
		if (registered)
		{
			Crops.Register();
			return;
		}
		CustomEffect val = new CustomEffectBuilder().WithBasicInfo("vx_diable", Localization.Text("Diable", "Devil", "Teufel", "Diablo"), Localization.Text("Corps noir, yeux et cornes rouges, fumée et grondement. Invulnérabilité et gravité réduite pendant l'effet. Alt + molette : vitesse du sprint.", "Black body, red eyes and horns, smoke and growl. Invulnerability and reduced gravity for the effect duration. Alt + wheel: sprint speed.", "Schwarzer Körper, rote Augen und Hörner, Rauch und Grollen. Unverwundbarkeit und verringerte Schwerkraft während der Wirkung. Alt + Mausrad: Sprinttempo.", "Cuerpo negro, ojos y cuernos rojos, humo y gruñido. Invulnerabilidad y gravedad reducida durante el efecto. Alt + rueda: velocidad de sprint.")).WithTier(5).WithColors(new Color(0.12f, 0.01f, 0.01f), Color.red)
			.WithValue(0, 1f, 0.66f)
			.WithBehavior((Action<Player>)delegate(Player p)
			{
				Devil.Apply(p.Transform);
			})
			.WithNpcBehavior((Action<NPC>)delegate(NPC n)
			{
				Devil.Apply(n.Transform);
			})
			.WithClearBehavior((Action<Player>)delegate(Player p)
			{
				Devil.Clear(p.Transform);
			})
			.WithNpcClearBehavior((Action<NPC>)delegate(NPC n)
			{
				Devil.Clear(n.Transform);
			})
			.Build();
		Entry[] catalogue = Catalogue;
		foreach (Entry entry in catalogue)
		{
			StorableItemDefinition obj = Presentation.Template(entry);
			GameObject val2 = Object.Instantiate<GameObject>(((Component)obj.StoredItem).gameObject);
			((Object)val2).name = "VX_" + entry.Key;
			GameObject item = Presentation.ReplaceMeshes(val2, entry);
			Presentation.Cache(val2);
			PropertyBase val3;
			if (entry.Key == "pill666")
			{
				val3 = (PropertyBase)(object)val;
			}
			else
			{
				val3 = NewEffects.Build(entry);
				Mixing.Ingredients[val3.ID] = entry;
			}
			MixIngredientDefinition val4 = ((StorableItemDefinitionBuilderBase<MixIngredientDefinitionBuilder>)(object)((StorableItemDefinitionBuilderBase<MixIngredientDefinitionBuilder>)(object)((StorableItemDefinitionBuilderBase<MixIngredientDefinitionBuilder>)(object)((StorableItemDefinitionBuilderBase<MixIngredientDefinitionBuilder>)(object)MixIngredientItemCreator.CreateBuilder()).WithBasicInfo("vx_" + entry.Key, entry.DisplayName, Localization.Text("Ingrédient du Comptoir 666. Effet : ", "Ingredient from the 666 Counter. Effect: ", "Zutat vom 666-Tresen. Wirkung: ", "Ingrediente del Mostrador 666. Efecto: ") + ((entry.Key == "pill666") ? Localization.Text("Diable", "Devil", "Teufel", "Diablo") : NewEffects.For(entry.Key).Name), (ItemCategory)9)).WithPricing((float)entry.Price, 0.5f)).WithStackLimit(20).WithEffect(val3)).WithStoredItem(val2).Build();
			icons.Add(((ItemDefinition)(object)val4, item));
			StorableItemDefinition target = ((Il2CppObjectBase)Registry.GetItem(((ItemDefinition)val4).ID)).Cast<StorableItemDefinition>();
			Presentation.ConfigureHeld(obj, target, entry);
			MelonLogger.Msg($"Enregistré : {((ItemDefinition)val4).ID} — {entry.Price} $");
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
		if (validationCheck > 0f && Time.unscaledTime >= validationCheck)
		{
			validationCheck = 0f;
			ValidateRuntime();
			try
			{
				Regression.Run();
			}
			catch (Exception ex)
			{
				MelonLogger.Error("RÉGRESSION ÉCHOUÉE : " + ex);
			}
		}
		if (registered && sceneReady && Time.unscaledTime >= nextEffectCheck)
		{
			nextEffectCheck = Time.unscaledTime + 0.5f;
			DevilReplication.ReconcilePlayers();
			foreach (Lucien item in NPC.All.OfType<Lucien>())
			{
				LucienCounter.Refresh(item);
				if (((NPC)item).IsVisible)
				{
					CharacterEffects.Add(((NPC)item).Transform, "vx_diable", permanent: true);
				}
				else
				{
					Devil.Clear(((NPC)item).Transform);
				}
				DevilPortrait.Capture(item);
			}
		}
		if (supplierCheck > 0f && Time.unscaledTime >= supplierCheck)
		{
			supplierCheck = 0f;
			try
			{
				Lucien lucien = NPC.All.OfType<Lucien>().FirstOrDefault();
				if (lucien == null)
				{
					throw new InvalidOperationException("Lucien absent de NPC.All");
				}
				((NPC)lucien).FirstName = "Lucien";
				((NPC)lucien).LastName = "";
				((NPC)lucien).RefreshMessagingIcons();
				((NPC)lucien).Supplier.Unlock();
				if (((NPC)lucien).Relationship.Delta < 5f)
				{
					((NPC)lucien).Relationship.Add(5f - ((NPC)lucien).Relationship.Delta, true);
				}
				Shop val = ((NPC)lucien).Supplier.Shop ?? throw new InvalidOperationException("Catalogue de Lucien indisponible");
				Entry[] catalogue = Catalogue;
				foreach (Entry entry in catalogue)
				{
					if (!val.HasItem("vx_" + entry.Key))
					{
						val.AddItem(ItemManager.GetDefinition("vx_" + entry.Key) ?? throw new InvalidOperationException("Objet absent : " + entry.Key), (float?)entry.Price);
					}
				}
				Crops.Variety[] varieties = Crops.Varieties;
				foreach (Crops.Variety variety in varieties)
				{
					if (!val.HasItem(Crops.SeedId(variety)))
					{
						val.AddItem(ItemManager.GetDefinition(Crops.SeedId(variety)), (float?)variety.SeedPrice);
					}
				}
				Supplier component = ((NPC)lucien).gameObject.GetComponent<Supplier>();
				object obj;
				if (component == null)
				{
					obj = null;
				}
				else
				{
					ShopInterface shop = component.Shop;
					obj = ((shop != null) ? shop.Listings : null);
				}
				if (obj != null)
				{
					Enumerator<ShopListing> enumerator2 = component.Shop.Listings.GetEnumerator();
					while (enumerator2.MoveNext())
					{
						ShopListing current2 = enumerator2.Current;
						if ((Object)(object)current2.Item != (Object)null && ((BaseItemDefinition)current2.Item).ID.StartsWith("vx_", StringComparison.Ordinal))
						{
							current2.CanBeDelivered = true;
							current2.LimitedStock = false;
							current2.DefaultStock = -1;
						}
					}
				}
				if ((Object)(object)component != (Object)null && ((NetworkBehaviour)component).IsServerInitialized && !component.DeliveriesEnabled)
				{
					component.RelationshipChange(0f);
				}
				Shop shop2 = ((NPC)lucien).Supplier.Shop;
				string[] array = ((shop2 != null) ? shop2.GetItemIds() : null) ?? Array.Empty<string>();
				MelonLogger.Msg($"FOURNISSEUR : Lucien ; catalogue={array.Length} articles ; livraisons={((NPC)lucien).Supplier.DeliveriesEnabled}");
			}
			catch (Exception ex2)
			{
				if (++supplierAttempts < 20)
				{
					supplierCheck = Time.unscaledTime + 3f;
				}
				if (supplierAttempts == 1 || supplierAttempts % 5 == 0)
				{
					MelonLogger.Warning("FOURNISSEUR en attente : " + ex2.Message);
				}
			}
		}
		if (!registered || !sceneReady || Time.unscaledTime < iconRetry || icons.Count == 0)
		{
			return;
		}
		iconRetry = Time.unscaledTime + 3f;
		IconGenerator instance = Singleton<IconGenerator>.Instance;
		if ((Object)(object)instance == (Object)null || (Object)(object)instance.CameraPosition == (Object)null || (Object)(object)instance.MainContainer == (Object)null || (Object)(object)instance.ItemContainer == (Object)null)
		{
			return;
		}
		for (int num = icons.Count - 1; num >= 0; num--)
		{
			try
			{
				GameObject val2 = Object.Instantiate<GameObject>(icons[num].model);
				Presentation.Cache(val2);
				Sprite val3;
				try
				{
					val3 = IconFactory.GenerateIconSprite(val2.transform, 512, true, true, (icons[num].item.ID == "vx_pill666") ? 0.94f : 0.86f);
				}
				finally
				{
					Object.Destroy((Object)(object)val2);
				}
				if ((Object)(object)val3 != (Object)null)
				{
					icons[num].item.Icon = val3;
					string text = Path.Combine(MelonEnvironment.UserDataDirectory, "VarietesEtendues", "Apercus");
					Directory.CreateDirectory(text);
					File.WriteAllBytes(Path.Combine(text, icons[num].item.ID + ".png"), Il2CppArrayBase<byte>.op_Implicit((Il2CppArrayBase<byte>)(object)ImageConversion.EncodeToPNG(val3.texture)));
					MelonLogger.Msg("Icône 3D prête : " + icons[num].item.ID);
					icons.RemoveAt(num);
				}
			}
			catch (Exception ex3)
			{
				MelonLogger.Warning("Icône à réessayer : " + ex3.Message);
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

	internal static PropertyBase Resolve(string name)
	{
		//IL_0091: Unknown result type (might be due to invalid IL or missing references)
		//IL_0097: Expected O, but got Unknown
		return (PropertyBase)(name switch
		{
			"Glowing" => Property.Glowie, 
			"Schizophrenia" => Property.Schizophrenic, 
			"Seizure-Inducing" => Property.Seizure, 
			_ => (object)(PropertyBase)(typeof(Property).GetField(name.Replace("-", "").Replace(" ", ""))?.GetValue(null) ?? throw new InvalidDataException("Effet inconnu : " + name)), 
		});
	}

	private void ValidateRuntime()
	{
		//IL_021b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0221: Expected O, but got Unknown
		//IL_0236: Unknown result type (might be due to invalid IL or missing references)
		//IL_0246: Unknown result type (might be due to invalid IL or missing references)
		//IL_0250: Unknown result type (might be due to invalid IL or missing references)
		//IL_029d: Unknown result type (might be due to invalid IL or missing references)
		//IL_02a2: Unknown result type (might be due to invalid IL or missing references)
		//IL_02a6: Unknown result type (might be due to invalid IL or missing references)
		//IL_02b2: Unknown result type (might be due to invalid IL or missing references)
		//IL_02b7: Unknown result type (might be due to invalid IL or missing references)
		//IL_02bb: Unknown result type (might be due to invalid IL or missing references)
		//IL_02c7: Unknown result type (might be due to invalid IL or missing references)
		//IL_02cc: Unknown result type (might be due to invalid IL or missing references)
		//IL_02d0: Unknown result type (might be due to invalid IL or missing references)
		try
		{
			int num = 0;
			int num2 = 0;
			Entry[] catalogue = Catalogue;
			foreach (Entry entry in catalogue)
			{
				ItemDefinition item = Registry.GetItem("vx_" + entry.Key);
				StorableItemDefinition val = ((item != null) ? ((Il2CppObjectBase)item).TryCast<StorableItemDefinition>() : null);
				if ((Object)(object)val == (Object)null || (Object)(object)val.StoredItem == (Object)null || (Object)(object)((ItemDefinition)val).Equippable == (Object)null || Mathf.Abs(val.BasePurchasePrice - (float)entry.Price) > 0.01f)
				{
					throw new InvalidDataException("Objet incomplet : " + entry.Key);
				}
				num++;
				if (entry.Key == "pill666")
				{
					continue;
				}
				Effect val2 = ((Il2CppObjectBase)val).Cast<PropertyItemDefinition>().Properties[0];
				string[][] rules = entry.Rules;
				foreach (string[] array in rules)
				{
					List<Effect> val3 = new List<Effect>();
					val3.Add(Mixing.Native(Resolve(array[0]).ID));
					List<Effect> obj = EffectMixCalculator.MixProperties(val3, val2, (EDrugType)0);
					bool flag = false;
					Enumerator<Effect> enumerator = obj.GetEnumerator();
					while (enumerator.MoveNext())
					{
						if (string.Equals(enumerator.Current.ID, Mixing.Native(Resolve(array[1]).ID).ID, StringComparison.OrdinalIgnoreCase))
						{
							flag = true;
						}
					}
					if (!flag || val3.Count != 1 || !string.Equals(val3[0].ID, Mixing.Native(Resolve(array[0]).ID).ID, StringComparison.OrdinalIgnoreCase))
					{
						throw new InvalidDataException("Mélange invalide : " + entry.Key + " / " + array[0]);
					}
					num2++;
				}
			}
			MelonLogger.Msg($"VALIDATION : {num}/19 objets complets ; {num2} transformations de mélange réussies.");
			GameObject val4 = new GameObject("VX_VerificationEffet");
			val4.transform.position = new Vector3(0f, 10000f, 0f);
			val4.transform.localScale = Vector3.one * 100f;
			try
			{
				Devil.Apply(val4.transform);
				Devil.Tick();
				if (val4.GetComponentsInChildren<ParticleSystem>().Length < 2)
				{
					throw new InvalidDataException("Fumée de l'effet Diable absente");
				}
				foreach (MeshRenderer componentsInChild in val4.GetComponentsInChildren<MeshRenderer>())
				{
					Bounds bounds = ((Renderer)componentsInChild).bounds;
					float x = ((Bounds)(ref bounds)).size.x;
					bounds = ((Renderer)componentsInChild).bounds;
					float y = ((Bounds)(ref bounds)).size.y;
					bounds = ((Renderer)componentsInChild).bounds;
					if (Mathf.Max(x, Mathf.Max(y, ((Bounds)(ref bounds)).size.z)) > 0.5f)
					{
						throw new InvalidDataException("L'effet Diable hérite d'une échelle de squelette excessive");
					}
				}
				Devil.Clear(val4.transform);
				MelonLogger.Msg("VALIDATION : effet Diable créé puis retiré sur un objet de test isolé.");
			}
			finally
			{
				Devil.Clear(val4.transform);
				Object.Destroy((Object)(object)val4);
			}
		}
		catch (Exception ex)
		{
			MelonLogger.Error("VALIDATION ÉCHOUÉE : " + ex);
		}
	}
}
