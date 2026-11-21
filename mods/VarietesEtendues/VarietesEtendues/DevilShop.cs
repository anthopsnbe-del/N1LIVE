using System;
using System.Collections.Generic;
using System.Linq;
using Il2CppInterop.Runtime.InteropTypes;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne.Core.Items.Framework;
using Il2CppScheduleOne.Economy;
using Il2CppScheduleOne.ItemFramework;
using Il2CppScheduleOne.UI.Phone.Delivery;
using Il2CppScheduleOne.UI.Shop;
using Il2CppSystem.Collections.Generic;
using MelonLoader;
using S1API.Entities;
using UnityEngine;
using UnityEngine.UI;

namespace VarietesEtendues;

internal static class DevilShop
{
	private static Il2CppScheduleOne.UI.Shop.ShopInterface? counter;

	private static Il2CppScheduleOne.UI.Phone.Delivery.DeliveryShop? delivery;

	private static UnityEngine.CanvasGroup? hidden;

	private static float oldAlpha;

	private static bool oldInteraction;

	private static bool oldRaycasts;

	private static UnityEngine.Vector2 scroll;

	private static UnityEngine.Vector2 cartScroll;

	private static int category;

	private static string notice = "";

	private static float lastDraw = -100f;

	private static float retryAfter;

	private static UnityEngine.GUIStyle? title;

	private static UnityEngine.GUIStyle? body;

	private static UnityEngine.GUIStyle? small;

	private static UnityEngine.GUIStyle? button;

	private static UnityEngine.GUIStyle? selected;

	private static UnityEngine.GUIStyle? checkout;

	private static readonly System.Collections.Generic.Dictionary<string, UnityEngine.Texture2D> fills = new System.Collections.Generic.Dictionary<string, UnityEngine.Texture2D>();

	private static readonly System.Collections.Generic.Dictionary<string, UnityEngine.GUIStyle> panels = new System.Collections.Generic.Dictionary<string, UnityEngine.GUIStyle>();

	private static UnityEngine.GUIStyle? picture;

	private static UnityEngine.GUIContent? empty;

	internal static bool IsLucien(Il2CppScheduleOne.UI.Shop.ShopInterface? shop)
	{
		if ((UnityEngine.Object)(object)shop == (UnityEngine.Object)null)
		{
			return false;
		}
		foreach (Lucien item2 in S1API.Entities.NPC.All.OfType<Lucien>())
		{
			Il2CppScheduleOne.Economy.Supplier component = ((S1API.Entities.NPC)item2).gameObject.GetComponent<Il2CppScheduleOne.Economy.Supplier>();
			if ((UnityEngine.Object)(object)((component != null) ? component.Shop : null) != (UnityEngine.Object)null && ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)component.Shop).Pointer == ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)shop).Pointer)
			{
				return true;
			}
		}
		if (shop.Listings != null)
		{
			var enumerator2 = shop.Listings.GetEnumerator();
			while (enumerator2.MoveNext())
			{
				Il2CppScheduleOne.ItemFramework.StorableItemDefinition item = enumerator2.Current.Item;
				if (((item != null) ? ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)item).ID : null) == "vx_pill666")
				{
					return true;
				}
			}
		}
		return false;
	}

	internal static void Tick()
	{
		foreach (Lucien item in S1API.Entities.NPC.All.OfType<Lucien>())
		{
			Il2CppScheduleOne.Economy.Supplier component = ((S1API.Entities.NPC)item).gameObject.GetComponent<Il2CppScheduleOne.Economy.Supplier>();
			Il2CppScheduleOne.UI.Shop.ShopInterface val = ((component != null) ? component.Shop : null);
			if ((UnityEngine.Object)(object)val == (UnityEngine.Object)null)
			{
				continue;
			}
			if (val.IsOpen)
			{
				if ((UnityEngine.Object)(object)counter == (UnityEngine.Object)null || ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)counter).Pointer != ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val).Pointer)
				{
					Counter(val, open: true);
					MelonLoader.MelonLogger.Msg("Boutique 666 : interface personnalisée activée pour Lucien.");
				}
				if ((UnityEngine.Object)(object)val.Canvas != (UnityEngine.Object)null)
				{
					((UnityEngine.Behaviour)val.Canvas).enabled = UnityEngine.Time.unscaledTime - lastDraw >= 0.5f;
				}
			}
			else if ((UnityEngine.Object)(object)counter != (UnityEngine.Object)null && ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)counter).Pointer == ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)val).Pointer)
			{
				counter = null;
			}
		}
		if ((UnityEngine.Object)(object)delivery != (UnityEngine.Object)null && !delivery.IsOpen)
		{
			Restore();
			delivery = null;
		}
	}

	internal static void Counter(Il2CppScheduleOne.UI.Shop.ShopInterface shop, bool open)
	{
		if (IsLucien(shop))
		{
			counter = (open ? shop : null);
			if (open)
			{
				scroll = UnityEngine.Vector2.zero;
				notice = "";
			}
		}
	}

	internal static void Delivery(Il2CppScheduleOne.UI.Phone.Delivery.DeliveryShop shop, bool open)
	{
		if (IsLucien(shop.MatchingShop))
		{
			Restore();
			delivery = (open ? shop : null);
			if (open)
			{
				scroll = UnityEngine.Vector2.zero;
				notice = "";
				hidden = ((UnityEngine.Component)shop).gameObject.GetComponent<UnityEngine.CanvasGroup>() ?? ((UnityEngine.Component)shop).gameObject.AddComponent<UnityEngine.CanvasGroup>();
				oldAlpha = hidden.alpha;
				oldInteraction = hidden.interactable;
				oldRaycasts = hidden.blocksRaycasts;
				hidden.alpha = 0f;
				hidden.interactable = false;
				hidden.blocksRaycasts = false;
			}
		}
	}

	private static void Restore()
	{
		if ((UnityEngine.Object)(object)hidden != (UnityEngine.Object)null)
		{
			hidden.alpha = oldAlpha;
			hidden.interactable = oldInteraction;
			hidden.blocksRaycasts = oldRaycasts;
		}
		hidden = null;
	}

	internal static void Clear()
	{
		Restore();
		counter = null;
		delivery = null;
		notice = "";
	}

	internal static void PhoneClosed()
	{
		Restore();
		delivery = null;
	}

	private static UnityEngine.Texture2D Surface(string hex)
	{
		if (fills.TryGetValue(hex, out UnityEngine.Texture2D value) && (UnityEngine.Object)(object)value != (UnityEngine.Object)null)
		{
			return value;
		}
		UnityEngine.Color val = default(UnityEngine.Color);
		UnityEngine.ColorUtility.TryParseHtmlString(hex, ref val);
		UnityEngine.Color[] array = (UnityEngine.Color[])(object)new UnityEngine.Color[1024];
		for (int i = 0; i < 32; i++)
		{
			for (int j = 0; j < 32; j++)
			{
				float num = UnityEngine.Mathf.Max(0, 5 - UnityEngine.Mathf.Min(j, 31 - j));
				float num2 = UnityEngine.Mathf.Max(0, 5 - UnityEngine.Mathf.Min(i, 31 - i));
				UnityEngine.Color val2 = val;
				val2.a *= UnityEngine.Mathf.Clamp01(5.8f - UnityEngine.Mathf.Sqrt(num * num + num2 * num2));
				array[i * 32 + j] = val2;
			}
		}
		UnityEngine.Texture2D val3 = new Texture2D(32, 32, (UnityEngine.TextureFormat)4, false);
		((UnityEngine.Object)val3).hideFlags = (UnityEngine.HideFlags)32;
		val3.SetPixels((array));
		val3.Apply();
		fills[hex] = val3;
		return val3;
	}

	private static void Fill(UnityEngine.Rect rect, string hex)
	{
		if (!panels.TryGetValue(hex, out UnityEngine.GUIStyle value) || value == null || (UnityEngine.Object)(object)value.normal.background == (UnityEngine.Object)null)
		{
			value = new GUIStyle();
			value.normal.background = Surface(hex);
			value.border = new RectOffset(6, 6, 6, 6);
			panels[hex] = value;
		}
		if (empty == null)
		{
			empty = new GUIContent("");
		}
		UnityEngine.GUI.Label(rect, empty, value);
	}

	private static void Picture(UnityEngine.Rect rect, UnityEngine.Texture? texture)
	{
		if (!((UnityEngine.Object)(object)texture == (UnityEngine.Object)null))
		{
			if (picture == null)
			{
				picture = new GUIStyle
				{
					alignment = (UnityEngine.TextAnchor)4,
					imagePosition = (UnityEngine.ImagePosition)2
				};
			}
			UnityEngine.GUI.Label(rect, new GUIContent("", texture, ""), picture);
		}
	}

	private static UnityEngine.GUIStyle Style(int size, UnityEngine.Color color)
	{
		UnityEngine.GUIStyle val = new GUIStyle(UnityEngine.GUI.skin.label)
		{
			fontSize = size
		};
		val.normal.textColor = color;
		val.wordWrap = true;
		return val;
	}

	private static UnityEngine.GUIStyle Button(string normal, string hover)
	{
		UnityEngine.GUIStyle val = new GUIStyle(UnityEngine.GUI.skin.button)
		{
			fontSize = 16,
			border = new RectOffset(6, 6, 6, 6)
		};
		val.normal.background = Surface(normal);
		val.normal.textColor = UnityEngine.Color.white;
		val.hover.background = Surface(hover);
		val.hover.textColor = UnityEngine.Color.white;
		val.active.background = Surface("#B7213C");
		val.active.textColor = UnityEngine.Color.white;
		return val;
	}

	private static void Close()
	{
		if ((UnityEngine.Object)(object)delivery != (UnityEngine.Object)null && delivery.IsOpen)
		{
			Il2CppScheduleOne.UI.Phone.Delivery.DeliveryApp app = SupplierUI.App;
			if ((UnityEngine.Object)(object)app != (UnityEngine.Object)null)
			{
				app.CloseShop(delivery);
			}
			else
			{
				delivery.Close();
			}
		}
		else
		{
			Il2CppScheduleOne.UI.Shop.ShopInterface? obj = counter;
			if (obj != null)
			{
				obj.Close();
			}
		}
	}

	internal static void Draw()
	{
		bool flag = (UnityEngine.Object)(object)delivery != (UnityEngine.Object)null && delivery.IsOpen;
		Il2CppScheduleOne.UI.Shop.ShopInterface val = (Il2CppScheduleOne.UI.Shop.ShopInterface)((!flag) ? ((object)(((UnityEngine.Object)(object)counter != (UnityEngine.Object)null && counter.IsOpen) ? counter : null)) : ((object)delivery.MatchingShop));
		if ((UnityEngine.Object)(object)val == (UnityEngine.Object)null || UnityEngine.Time.unscaledTime < retryAfter)
		{
			return;
		}
		UnityEngine.Matrix4x4 matrix = UnityEngine.GUI.matrix;
		int depth = UnityEngine.GUI.depth;
		try
		{
			if (title == null)
			{
				title = Style(29, UnityEngine.Color.white);
				body = Style(17, new Color(0.94f, 0.92f, 0.91f));
				small = Style(13, new Color(0.7f, 0.65f, 0.66f));
				button = Button("#30282E", "#57313E");
				selected = Button("#8B1C32", "#B72A43");
				checkout = Button("#B21C38", "#D52948");
			}
			UnityEngine.GUI.depth = -100;
			float num = UnityEngine.Mathf.Min((float)UnityEngine.Screen.width / 1380f, (float)UnityEngine.Screen.height / 880f);
			UnityEngine.GUI.matrix = UnityEngine.Matrix4x4.TRS(new Vector3(((float)UnityEngine.Screen.width - 1280f * num) / 2f, ((float)UnityEngine.Screen.height - 800f * num) / 2f, 0f), UnityEngine.Quaternion.identity, new Vector3(num, num, 1f));
			Fill(new Rect(-4f, -4f, 1288f, 808f), "#B51D37");
			Fill(new Rect(0f, 0f, 1280f, 800f), "#100C10");
			Fill(new Rect(0f, 0f, 1280f, 118f), "#211017");
			UnityEngine.Sprite image = DevilPortrait.Image;
			if ((UnityEngine.Object)(object)image != (UnityEngine.Object)null)
			{
				Picture(new Rect(22f, 14f, 90f, 90f), (UnityEngine.Texture?)(object)image.texture);
			}
			UnityEngine.GUI.Label(new Rect(132f, 22f, 800f, 44f), Localization.Text("LE COMPTOIR 666", "THE 666 COUNTER", "DER 666-TRESEN", "EL MOSTRADOR 666"), title);
			UnityEngine.GUI.Label(new Rect(134f, 72f, 850f, 28f), Localization.Text("LUCIEN  /  INGRÉDIENTS & VARIÉTÉS", "LUCIEN  /  INGREDIENTS & VARIETIES", "LUCIEN  /  ZUTATEN & SORTEN", "LUCIEN  /  INGREDIENTES Y VARIEDADES"), small);
			if (UnityEngine.GUI.Button(new Rect(1168f, 32f, 88f, 40f), Localization.Text("Fermer", "Close", "Schließen", "Cerrar"), button))
			{
				Close();
				return;
			}
			string[] array = Localization.Current switch { Lang.French => new string[5] { "Tous", "Fruits", "Médicaments", "Divers", "Graines" }, Lang.German => new string[5] { "Alle", "Obst", "Medizin", "Sonstiges", "Samen" }, Lang.Spanish => new string[5] { "Todo", "Frutas", "Medicinas", "Varios", "Semillas" }, _ => new string[5] { "All", "Fruit", "Medicine", "Other", "Seeds" } };
			for (int i = 0; i < array.Length; i++)
			{
				if (UnityEngine.GUI.Button(new Rect((float)(23 + i * 182), 136f, 171f, 39f), array[i], (category == i) ? selected : button))
				{
					category = i;
					scroll = UnityEngine.Vector2.zero;
				}
			}
			System.Collections.Generic.List<Il2CppScheduleOne.UI.Shop.ShopListing> list = new System.Collections.Generic.List<Il2CppScheduleOne.UI.Shop.ShopListing>();
			var enumerator = val.Listings.GetEnumerator();
			while (enumerator.MoveNext())
			{
				Il2CppScheduleOne.UI.Shop.ShopListing current = enumerator.Current;
				if ((UnityEngine.Object)(object)current.Item != (UnityEngine.Object)null && ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)current.Item).ID.StartsWith("vx_") && current.ShouldShow() && (category == 0 || Group(((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)current.Item).ID) == category))
				{
					list.Add(current);
				}
			}
			scroll = UnityEngine.GUI.BeginScrollView(new Rect(20f, 190f, 916f, 580f), scroll, new Rect(0f, 0f, 890f, UnityEngine.Mathf.Max(555f, UnityEngine.Mathf.Ceil((float)list.Count / 3f) * 192f)));
			for (int j = 0; j < list.Count; j++)
			{
				Il2CppScheduleOne.UI.Shop.ShopListing val2 = list[j];
				float num2 = j % 3 * 297;
				float num3 = j / 3 * 192;
				Fill(new Rect(num2, num3, 284f, 179f), (((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2.Item).ID == "vx_pill666") ? "#491722" : "#282128");
				if ((UnityEngine.Object)(object)((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2.Item).Icon != (UnityEngine.Object)null)
				{
					Picture(new Rect(num2 + 8f, num3 + 18f, 111f, 135f), (UnityEngine.Texture?)(object)((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2.Item).Icon.texture);
				}
				UnityEngine.GUI.Label(new Rect(num2 + 128f, num3 + 17f, 145f, 60f), ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2.Item).Name, body);
				UnityEngine.GUI.Label(new Rect(num2 + 130f, num3 + 82f, 142f, 28f), val2.Price.ToString("0.##") + " $", body);
				string text = ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)val2.Item).ID.Substring(3);
				if (!text.EndsWith("seed", System.StringComparison.Ordinal))
				{
					UnityEngine.GUI.Label(new Rect(num2 + 128f, num3 + 106f, 145f, 21f), (text == "pill666") ? Localization.Text("Diable", "Devil", "Teufel", "Diablo") : NewEffects.For(text).Name, small);
				}
				int quantity = GetQuantity(val, val2);
				UnityEngine.GUI.enabled = quantity > 0;
				if (UnityEngine.GUI.Button(new Rect(num2 + 126f, num3 + 128f, 34f, 33f), "−", button))
				{
					SetQuantity(val, val2, quantity - 1);
				}
				UnityEngine.GUI.enabled = true;
				UnityEngine.GUI.Label(new Rect(num2 + 172f, num3 + 134f, 45f, 26f), quantity.ToString(), body);
				UnityEngine.GUI.enabled = val2.IsInStock && quantity < Il2CppScheduleOne.UI.Shop.ShopInterface.MAX_ITEM_QUANTITY;
				if (UnityEngine.GUI.Button(new Rect(num2 + 234f, num3 + 128f, 34f, 33f), "+", button))
				{
					SetQuantity(val, val2, quantity + 1);
				}
				UnityEngine.GUI.enabled = true;
			}
			UnityEngine.GUI.EndScrollView();
			Fill(new Rect(958f, 118f, 322f, 682f), "#1E151C");
			UnityEngine.GUI.Label(new Rect(980f, 141f, 276f, 43f), Localization.Text("Panier", "Cart", "Warenkorb", "Carrito"), title);
			System.Collections.Generic.List<(Il2CppScheduleOne.UI.Shop.ShopListing, int)> list2 = new System.Collections.Generic.List<(Il2CppScheduleOne.UI.Shop.ShopListing, int)>();
			enumerator = val.Listings.GetEnumerator();
			while (enumerator.MoveNext())
			{
				Il2CppScheduleOne.UI.Shop.ShopListing current2 = enumerator.Current;
				int quantity2 = GetQuantity(val, current2);
				if (quantity2 > 0)
				{
					list2.Add((current2, quantity2));
				}
			}
			float num4 = (flag ? 300 : 405);
			cartScroll = UnityEngine.GUI.BeginScrollView(new Rect(976f, 194f, 294f, num4), cartScroll, new Rect(0f, 0f, 272f, UnityEngine.Mathf.Max(num4 - 10f, (float)(list2.Count * 68))));
			for (int k = 0; k < list2.Count; k++)
			{
				(Il2CppScheduleOne.UI.Shop.ShopListing, int) tuple = list2[k];
				UnityEngine.GUI.Label(new Rect(0f, (float)(k * 68), 235f, 59f), tuple.Item2 + " × " + ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)tuple.Item1.Item).Name + "\n" + (tuple.Item1.Price * (float)tuple.Item2).ToString("0.##") + " $", body);
				if (UnityEngine.GUI.Button(new Rect(238f, (float)(k * 68 + 10), 27f, 29f), "×", button))
				{
					SetQuantity(val, tuple.Item1, 0);
				}
			}
			UnityEngine.GUI.EndScrollView();
			if (flag)
			{
				UnityEngine.GUI.Label(new Rect(981f, 505f, 270f, 20f), Localization.Text("Destination", "Destination", "Ziel", "Destino"), small);
				UnityEngine.UI.Dropdown destinationDropdown = delivery.DestinationDropdown;
				if (Cycle(new Rect(981f, 527f, 274f, 36f), destinationDropdown))
				{
					delivery.DestinationDropdownSelected(destinationDropdown.value);
				}
				UnityEngine.GUI.Label(new Rect(981f, 569f, 270f, 20f), Localization.Text("Quai de chargement", "Loading dock", "Laderampe", "Muelle de carga"), small);
				UnityEngine.UI.Dropdown loadingDockDropdown = delivery.LoadingDockDropdown;
				if (Cycle(new Rect(981f, 590f, 274f, 32f), loadingDockDropdown))
				{
					delivery.LoadingDockDropdownSelected(loadingDockDropdown.value);
				}
				UnityEngine.GUI.Label(new Rect(981f, 629f, 274f, 25f), Localization.Text("Livraison : ", "Delivery: ", "Lieferung: ", "Entrega: ") + delivery.GetDeliveryFee().ToString("0.##") + " $", small);
			}
			else if ((UnityEngine.Object)(object)val.Cart.LoadVehicleToggle != (UnityEngine.Object)null && ((UnityEngine.UI.Selectable)val.Cart.LoadVehicleToggle).interactable)
			{
				val.Cart.LoadVehicleToggle.isOn = UnityEngine.GUI.Toggle(new Rect(982f, 617f, 272f, 29f), val.Cart.LoadVehicleToggle.isOn, Localization.Text("Charger mon véhicule", "Load my vehicle", "Mein Fahrzeug beladen", "Cargar mi vehículo"));
			}
			float num5 = (flag ? (delivery.GetCartCost() + delivery.GetDeliveryFee()) : val.Cart.GetPriceSum());
			UnityEngine.GUI.Label(new Rect(981f, 660f, 278f, 38f), "Total : " + num5.ToString("0.##") + " $", title);
			UnityEngine.GUI.Label(new Rect(981f, 700f, 276f, 43f), notice, small);
			UnityEngine.GUI.enabled = list2.Count > 0;
			if (UnityEngine.GUI.Button(new Rect(980f, 750f, 276f, 35f), Localization.Text("VALIDER LA COMMANDE", "CONFIRM ORDER", "BESTELLUNG BESTÄTIGEN", "CONFIRMAR PEDIDO"), checkout))
			{
				string text2 = default(string);
				if (flag ? delivery.CanOrder(ref text2) : val.Cart.CanCheckout(ref text2))
				{
					if (flag)
					{
						delivery.SubmitOrder("");
					}
					else
					{
						val.Cart.Buy();
					}
					notice = Localization.Text("Commande validée.", "Order confirmed.", "Bestellung bestätigt.", "Pedido confirmado.");
				}
				else
				{
					notice = Localization.Text("Vérifie ton solde, la destination et la place disponible.", "Check your funds, destination and available space.", "Prüfe dein Guthaben, das Ziel und den freien Platz.", "Comprueba tu saldo, el destino y el espacio disponible.");
				}
			}
			UnityEngine.GUI.enabled = true;
			lastDraw = UnityEngine.Time.unscaledTime;
			Il2CppScheduleOne.UI.Shop.ShopInterface? obj = counter;
			if ((UnityEngine.Object)(object)((obj != null) ? obj.Canvas : null) != (UnityEngine.Object)null)
			{
				((UnityEngine.Behaviour)counter.Canvas).enabled = false;
			}
		}
		catch (System.Exception ex)
		{
			MelonLoader.MelonLogger.Error("Boutique 666 : retour à l'interface native : " + ex);
			retryAfter = UnityEngine.Time.unscaledTime + 10f;
			lastDraw = -100f;
			Il2CppScheduleOne.UI.Shop.ShopInterface? obj2 = counter;
			if ((UnityEngine.Object)(object)((obj2 != null) ? obj2.Canvas : null) != (UnityEngine.Object)null)
			{
				((UnityEngine.Behaviour)counter.Canvas).enabled = true;
			}
			Clear();
		}
		finally
		{
			UnityEngine.GUI.enabled = true;
			UnityEngine.GUI.matrix = matrix;
			UnityEngine.GUI.depth = depth;
		}
	}

	private static bool Cycle(UnityEngine.Rect rect, UnityEngine.UI.Dropdown dropdown)
	{
		if ((UnityEngine.Object)(object)dropdown == (UnityEngine.Object)null || dropdown.options == null || dropdown.options.Count == 0)
		{
			UnityEngine.GUI.enabled = false;
			UnityEngine.GUI.Button(rect, Localization.Text("Indisponible", "Unavailable", "Nicht verfügbar", "No disponible"), button);
			UnityEngine.GUI.enabled = true;
			return false;
		}
		bool flag2 = (UnityEngine.GUI.enabled = true);
		string text = (flag2 ? dropdown.options[dropdown.value].text : Localization.Text("Indisponible", "Unavailable", "Nicht verfügbar", "No disponible"));
		bool num = UnityEngine.GUI.Button(rect, text, button);
		UnityEngine.GUI.enabled = true;
		if (num && flag2)
		{
			dropdown.value = (dropdown.value + 1) % dropdown.options.Count;
			return true;
		}
		return false;
	}

	private static int Group(string id)
	{
		if (id.EndsWith("seed", System.StringComparison.Ordinal))
		{
			return 4;
		}
		string value = id.Substring(3);
		if (!new string[6] { "mango", "lemon", "melon", "dragon", "prune", "durian" }.Contains(value))
		{
			if (!new string[7] { "syrup", "focalix", "appetix", "fizz", "cyclo", "paranox", "pill666" }.Contains(value))
			{
				return 3;
			}
			return 2;
		}
		return 1;
	}

	private static int GetQuantity(Il2CppScheduleOne.UI.Shop.ShopInterface shop, Il2CppScheduleOne.UI.Shop.ShopListing listing)
	{
		if ((UnityEngine.Object)(object)delivery != (UnityEngine.Object)null && delivery.IsOpen)
		{
			var enumerator = delivery.listingEntries.GetEnumerator();
			while (enumerator.MoveNext())
			{
				Il2CppScheduleOne.UI.Phone.Delivery.ListingEntry current = enumerator.Current;
				if (((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)current.MatchingListing.Item).ID == ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)listing.Item).ID)
				{
					return current.SelectedQuantity;
				}
			}
			return 0;
		}
		return shop.Cart.GetCartCount(listing);
	}

	private static void SetQuantity(Il2CppScheduleOne.UI.Shop.ShopInterface shop, Il2CppScheduleOne.UI.Shop.ShopListing listing, int quantity)
	{
		quantity = UnityEngine.Mathf.Clamp(quantity, 0, Il2CppScheduleOne.UI.Shop.ShopInterface.MAX_ITEM_QUANTITY);
		notice = "";
		if ((UnityEngine.Object)(object)delivery != (UnityEngine.Object)null && delivery.IsOpen)
		{
			var enumerator = delivery.listingEntries.GetEnumerator();
			while (enumerator.MoveNext())
			{
				Il2CppScheduleOne.UI.Phone.Delivery.ListingEntry current = enumerator.Current;
				if (((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)current.MatchingListing.Item).ID == ((Il2CppScheduleOne.Core.Items.Framework.BaseItemDefinition)listing.Item).ID)
				{
					current.SetQuantity(quantity, true);
					break;
				}
			}
			delivery.RefreshCart();
		}
		else
		{
			shop.Cart.SetItemQuantity(listing, quantity);
		}
	}
}
