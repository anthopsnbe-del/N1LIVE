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
	private static ShopInterface? counter;

	private static DeliveryShop? delivery;

	private static CanvasGroup? hidden;

	private static float oldAlpha;

	private static bool oldInteraction;

	private static bool oldRaycasts;

	private static Vector2 scroll;

	private static Vector2 cartScroll;

	private static int category;

	private static string notice = "";

	private static float lastDraw = -100f;

	private static float retryAfter;

	private static GUIStyle? title;

	private static GUIStyle? body;

	private static GUIStyle? small;

	private static GUIStyle? button;

	private static GUIStyle? selected;

	private static GUIStyle? checkout;

	private static readonly Dictionary<string, Texture2D> fills = new Dictionary<string, Texture2D>();

	private static readonly Dictionary<string, GUIStyle> panels = new Dictionary<string, GUIStyle>();

	private static GUIStyle? picture;

	private static GUIContent? empty;

	internal static bool IsLucien(ShopInterface? shop)
	{
		if ((Object)(object)shop == (Object)null)
		{
			return false;
		}
		foreach (Lucien item2 in NPC.All.OfType<Lucien>())
		{
			Supplier component = ((NPC)item2).gameObject.GetComponent<Supplier>();
			if ((Object)(object)((component != null) ? component.Shop : null) != (Object)null && ((Il2CppObjectBase)component.Shop).Pointer == ((Il2CppObjectBase)shop).Pointer)
			{
				return true;
			}
		}
		if (shop.Listings != null)
		{
			Enumerator<ShopListing> enumerator2 = shop.Listings.GetEnumerator();
			while (enumerator2.MoveNext())
			{
				StorableItemDefinition item = enumerator2.Current.Item;
				if (((item != null) ? ((BaseItemDefinition)item).ID : null) == "vx_pill666")
				{
					return true;
				}
			}
		}
		return false;
	}

	internal static void Tick()
	{
		foreach (Lucien item in NPC.All.OfType<Lucien>())
		{
			Supplier component = ((NPC)item).gameObject.GetComponent<Supplier>();
			ShopInterface val = ((component != null) ? component.Shop : null);
			if ((Object)(object)val == (Object)null)
			{
				continue;
			}
			if (val.IsOpen)
			{
				if ((Object)(object)counter == (Object)null || ((Il2CppObjectBase)counter).Pointer != ((Il2CppObjectBase)val).Pointer)
				{
					Counter(val, open: true);
					MelonLogger.Msg("Boutique 666 : interface personnalisée activée pour Lucien.");
				}
				if ((Object)(object)val.Canvas != (Object)null)
				{
					((Behaviour)val.Canvas).enabled = Time.unscaledTime - lastDraw >= 0.5f;
				}
			}
			else if ((Object)(object)counter != (Object)null && ((Il2CppObjectBase)counter).Pointer == ((Il2CppObjectBase)val).Pointer)
			{
				counter = null;
			}
		}
		if ((Object)(object)delivery != (Object)null && !delivery.IsOpen)
		{
			Restore();
			delivery = null;
		}
	}

	internal static void Counter(ShopInterface shop, bool open)
	{
		//IL_0017: Unknown result type (might be due to invalid IL or missing references)
		//IL_001c: Unknown result type (might be due to invalid IL or missing references)
		if (IsLucien(shop))
		{
			counter = (open ? shop : null);
			if (open)
			{
				scroll = Vector2.zero;
				notice = "";
			}
		}
	}

	internal static void Delivery(DeliveryShop shop, bool open)
	{
		//IL_0027: Unknown result type (might be due to invalid IL or missing references)
		//IL_002c: Unknown result type (might be due to invalid IL or missing references)
		if (IsLucien(shop.MatchingShop))
		{
			Restore();
			delivery = (open ? shop : null);
			if (open)
			{
				scroll = Vector2.zero;
				notice = "";
				hidden = ((Component)shop).gameObject.GetComponent<CanvasGroup>() ?? ((Component)shop).gameObject.AddComponent<CanvasGroup>();
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
		if ((Object)(object)hidden != (Object)null)
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

	private static Texture2D Surface(string hex)
	{
		//IL_00c0: Unknown result type (might be due to invalid IL or missing references)
		//IL_00c6: Expected O, but got Unknown
		//IL_0066: Unknown result type (might be due to invalid IL or missing references)
		//IL_0067: Unknown result type (might be due to invalid IL or missing references)
		//IL_0098: Unknown result type (might be due to invalid IL or missing references)
		//IL_009a: Unknown result type (might be due to invalid IL or missing references)
		if (fills.TryGetValue(hex, out Texture2D value) && (Object)(object)value != (Object)null)
		{
			return value;
		}
		Color val = default(Color);
		ColorUtility.TryParseHtmlString(hex, ref val);
		Color[] array = (Color[])(object)new Color[1024];
		for (int i = 0; i < 32; i++)
		{
			for (int j = 0; j < 32; j++)
			{
				float num = Mathf.Max(0, 5 - Mathf.Min(j, 31 - j));
				float num2 = Mathf.Max(0, 5 - Mathf.Min(i, 31 - i));
				Color val2 = val;
				val2.a *= Mathf.Clamp01(5.8f - Mathf.Sqrt(num * num + num2 * num2));
				array[i * 32 + j] = val2;
			}
		}
		Texture2D val3 = new Texture2D(32, 32, (TextureFormat)4, false);
		((Object)val3).hideFlags = (HideFlags)32;
		val3.SetPixels(Il2CppStructArray<Color>.op_Implicit(array));
		val3.Apply();
		fills[hex] = val3;
		return val3;
	}

	private static void Fill(Rect rect, string hex)
	{
		//IL_0025: Unknown result type (might be due to invalid IL or missing references)
		//IL_002b: Expected O, but got Unknown
		//IL_0041: Unknown result type (might be due to invalid IL or missing references)
		//IL_004b: Expected O, but got Unknown
		//IL_006d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0063: Unknown result type (might be due to invalid IL or missing references)
		//IL_006d: Expected O, but got Unknown
		if (!panels.TryGetValue(hex, out GUIStyle value) || value == null || (Object)(object)value.normal.background == (Object)null)
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
		GUI.Label(rect, empty, value);
	}

	private static void Picture(Rect rect, Texture? texture)
	{
		//IL_0029: Unknown result type (might be due to invalid IL or missing references)
		//IL_0035: Unknown result type (might be due to invalid IL or missing references)
		//IL_0044: Expected O, but got Unknown
		//IL_0011: Unknown result type (might be due to invalid IL or missing references)
		//IL_0016: Unknown result type (might be due to invalid IL or missing references)
		//IL_001d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0029: Expected O, but got Unknown
		if (!((Object)(object)texture == (Object)null))
		{
			if (picture == null)
			{
				picture = new GUIStyle
				{
					alignment = (TextAnchor)4,
					imagePosition = (ImagePosition)2
				};
			}
			GUI.Label(rect, new GUIContent("", texture, ""), picture);
		}
	}

	private static GUIStyle Style(int size, Color color)
	{
		//IL_000a: Unknown result type (might be due to invalid IL or missing references)
		//IL_000f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0016: Unknown result type (might be due to invalid IL or missing references)
		//IL_001c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0022: Unknown result type (might be due to invalid IL or missing references)
		//IL_002a: Expected O, but got Unknown
		GUIStyle val = new GUIStyle(GUI.skin.label)
		{
			fontSize = size
		};
		val.normal.textColor = color;
		val.wordWrap = true;
		return val;
	}

	private static GUIStyle Button(string normal, string hover)
	{
		//IL_000a: Unknown result type (might be due to invalid IL or missing references)
		//IL_000f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0017: Unknown result type (might be due to invalid IL or missing references)
		//IL_001c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0026: Expected O, but got Unknown
		//IL_0026: Unknown result type (might be due to invalid IL or missing references)
		//IL_0037: Unknown result type (might be due to invalid IL or missing references)
		//IL_003d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0047: Unknown result type (might be due to invalid IL or missing references)
		//IL_0058: Unknown result type (might be due to invalid IL or missing references)
		//IL_005e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0068: Unknown result type (might be due to invalid IL or missing references)
		//IL_007d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0083: Unknown result type (might be due to invalid IL or missing references)
		//IL_008e: Expected O, but got Unknown
		GUIStyle val = new GUIStyle(GUI.skin.button)
		{
			fontSize = 16,
			border = new RectOffset(6, 6, 6, 6)
		};
		val.normal.background = Surface(normal);
		val.normal.textColor = Color.white;
		val.hover.background = Surface(hover);
		val.hover.textColor = Color.white;
		val.active.background = Surface("#B7213C");
		val.active.textColor = Color.white;
		return val;
	}

	private static void Close()
	{
		if ((Object)(object)delivery != (Object)null && delivery.IsOpen)
		{
			DeliveryApp app = SupplierUI.App;
			if ((Object)(object)app != (Object)null)
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
			ShopInterface? obj = counter;
			if (obj != null)
			{
				obj.Close();
			}
		}
	}

	internal static void Draw()
	{
		//IL_0c94: Unknown result type (might be due to invalid IL or missing references)
		//IL_0063: Unknown result type (might be due to invalid IL or missing references)
		//IL_0068: Unknown result type (might be due to invalid IL or missing references)
		//IL_015b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0160: Unknown result type (might be due to invalid IL or missing references)
		//IL_016e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0173: Unknown result type (might be due to invalid IL or missing references)
		//IL_0191: Unknown result type (might be due to invalid IL or missing references)
		//IL_01b4: Unknown result type (might be due to invalid IL or missing references)
		//IL_01d7: Unknown result type (might be due to invalid IL or missing references)
		//IL_007b: Unknown result type (might be due to invalid IL or missing references)
		//IL_009b: Unknown result type (might be due to invalid IL or missing references)
		//IL_00bb: Unknown result type (might be due to invalid IL or missing references)
		//IL_0230: Unknown result type (might be due to invalid IL or missing references)
		//IL_0262: Unknown result type (might be due to invalid IL or missing references)
		//IL_0294: Unknown result type (might be due to invalid IL or missing references)
		//IL_020b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0345: Unknown result type (might be due to invalid IL or missing references)
		//IL_041e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0423: Unknown result type (might be due to invalid IL or missing references)
		//IL_045a: Unknown result type (might be due to invalid IL or missing references)
		//IL_045f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0464: Unknown result type (might be due to invalid IL or missing references)
		//IL_0372: Unknown result type (might be due to invalid IL or missing references)
		//IL_0377: Unknown result type (might be due to invalid IL or missing references)
		//IL_04a4: Unknown result type (might be due to invalid IL or missing references)
		//IL_071e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0741: Unknown result type (might be due to invalid IL or missing references)
		//IL_0535: Unknown result type (might be due to invalid IL or missing references)
		//IL_056a: Unknown result type (might be due to invalid IL or missing references)
		//IL_0500: Unknown result type (might be due to invalid IL or missing references)
		//IL_063d: Unknown result type (might be due to invalid IL or missing references)
		//IL_05d5: Unknown result type (might be due to invalid IL or missing references)
		//IL_07c9: Unknown result type (might be due to invalid IL or missing references)
		//IL_07ce: Unknown result type (might be due to invalid IL or missing references)
		//IL_07fa: Unknown result type (might be due to invalid IL or missing references)
		//IL_07ff: Unknown result type (might be due to invalid IL or missing references)
		//IL_0804: Unknown result type (might be due to invalid IL or missing references)
		//IL_067f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0831: Unknown result type (might be due to invalid IL or missing references)
		//IL_08c4: Unknown result type (might be due to invalid IL or missing references)
		//IL_06c9: Unknown result type (might be due to invalid IL or missing references)
		//IL_091b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0959: Unknown result type (might be due to invalid IL or missing references)
		//IL_098c: Unknown result type (might be due to invalid IL or missing references)
		//IL_09ca: Unknown result type (might be due to invalid IL or missing references)
		//IL_0a83: Unknown result type (might be due to invalid IL or missing references)
		//IL_09fd: Unknown result type (might be due to invalid IL or missing references)
		//IL_0aec: Unknown result type (might be due to invalid IL or missing references)
		//IL_0b2a: Unknown result type (might be due to invalid IL or missing references)
		//IL_0b61: Unknown result type (might be due to invalid IL or missing references)
		bool flag = (Object)(object)delivery != (Object)null && delivery.IsOpen;
		ShopInterface val = (ShopInterface)((!flag) ? ((object)(((Object)(object)counter != (Object)null && counter.IsOpen) ? counter : null)) : ((object)delivery.MatchingShop));
		if ((Object)(object)val == (Object)null || Time.unscaledTime < retryAfter)
		{
			return;
		}
		Matrix4x4 matrix = GUI.matrix;
		int depth = GUI.depth;
		try
		{
			if (title == null)
			{
				title = Style(29, Color.white);
				body = Style(17, new Color(0.94f, 0.92f, 0.91f));
				small = Style(13, new Color(0.7f, 0.65f, 0.66f));
				button = Button("#30282E", "#57313E");
				selected = Button("#8B1C32", "#B72A43");
				checkout = Button("#B21C38", "#D52948");
			}
			GUI.depth = -100;
			float num = Mathf.Min((float)Screen.width / 1380f, (float)Screen.height / 880f);
			GUI.matrix = Matrix4x4.TRS(new Vector3(((float)Screen.width - 1280f * num) / 2f, ((float)Screen.height - 800f * num) / 2f, 0f), Quaternion.identity, new Vector3(num, num, 1f));
			Fill(new Rect(-4f, -4f, 1288f, 808f), "#B51D37");
			Fill(new Rect(0f, 0f, 1280f, 800f), "#100C10");
			Fill(new Rect(0f, 0f, 1280f, 118f), "#211017");
			Sprite image = DevilPortrait.Image;
			if ((Object)(object)image != (Object)null)
			{
				Picture(new Rect(22f, 14f, 90f, 90f), (Texture?)(object)image.texture);
			}
			GUI.Label(new Rect(132f, 22f, 800f, 44f), Localization.Text("LE COMPTOIR 666", "THE 666 COUNTER", "DER 666-TRESEN", "EL MOSTRADOR 666"), title);
			GUI.Label(new Rect(134f, 72f, 850f, 28f), Localization.Text("LUCIEN  /  INGRÉDIENTS & VARIÉTÉS", "LUCIEN  /  INGREDIENTS & VARIETIES", "LUCIEN  /  ZUTATEN & SORTEN", "LUCIEN  /  INGREDIENTES Y VARIEDADES"), small);
			if (GUI.Button(new Rect(1168f, 32f, 88f, 40f), Localization.Text("Fermer", "Close", "Schließen", "Cerrar"), button))
			{
				Close();
				return;
			}
			string[] array = Localization.Current switch { Lang.French => new string[5] { "Tous", "Fruits", "Médicaments", "Divers", "Graines" }, Lang.German => new string[5] { "Alle", "Obst", "Medizin", "Sonstiges", "Samen" }, Lang.Spanish => new string[5] { "Todo", "Frutas", "Medicinas", "Varios", "Semillas" }, _ => new string[5] { "All", "Fruit", "Medicine", "Other", "Seeds" } };
			for (int i = 0; i < array.Length; i++)
			{
				if (GUI.Button(new Rect((float)(23 + i * 182), 136f, 171f, 39f), array[i], (category == i) ? selected : button))
				{
					category = i;
					scroll = Vector2.zero;
				}
			}
			List<ShopListing> list = new List<ShopListing>();
			Enumerator<ShopListing> enumerator = val.Listings.GetEnumerator();
			while (enumerator.MoveNext())
			{
				ShopListing current = enumerator.Current;
				if ((Object)(object)current.Item != (Object)null && ((BaseItemDefinition)current.Item).ID.StartsWith("vx_") && current.ShouldShow() && (category == 0 || Group(((BaseItemDefinition)current.Item).ID) == category))
				{
					list.Add(current);
				}
			}
			scroll = GUI.BeginScrollView(new Rect(20f, 190f, 916f, 580f), scroll, new Rect(0f, 0f, 890f, Mathf.Max(555f, Mathf.Ceil((float)list.Count / 3f) * 192f)));
			for (int j = 0; j < list.Count; j++)
			{
				ShopListing val2 = list[j];
				float num2 = j % 3 * 297;
				float num3 = j / 3 * 192;
				Fill(new Rect(num2, num3, 284f, 179f), (((BaseItemDefinition)val2.Item).ID == "vx_pill666") ? "#491722" : "#282128");
				if ((Object)(object)((BaseItemDefinition)val2.Item).Icon != (Object)null)
				{
					Picture(new Rect(num2 + 8f, num3 + 18f, 111f, 135f), (Texture?)(object)((BaseItemDefinition)val2.Item).Icon.texture);
				}
				GUI.Label(new Rect(num2 + 128f, num3 + 17f, 145f, 60f), ((BaseItemDefinition)val2.Item).Name, body);
				GUI.Label(new Rect(num2 + 130f, num3 + 82f, 142f, 28f), val2.Price.ToString("0.##") + " $", body);
				string text = ((BaseItemDefinition)val2.Item).ID.Substring(3);
				if (!text.EndsWith("seed", StringComparison.Ordinal))
				{
					GUI.Label(new Rect(num2 + 128f, num3 + 106f, 145f, 21f), (text == "pill666") ? Localization.Text("Diable", "Devil", "Teufel", "Diablo") : NewEffects.For(text).Name, small);
				}
				int quantity = GetQuantity(val, val2);
				GUI.enabled = quantity > 0;
				if (GUI.Button(new Rect(num2 + 126f, num3 + 128f, 34f, 33f), "−", button))
				{
					SetQuantity(val, val2, quantity - 1);
				}
				GUI.enabled = true;
				GUI.Label(new Rect(num2 + 172f, num3 + 134f, 45f, 26f), quantity.ToString(), body);
				GUI.enabled = val2.IsInStock && quantity < ShopInterface.MAX_ITEM_QUANTITY;
				if (GUI.Button(new Rect(num2 + 234f, num3 + 128f, 34f, 33f), "+", button))
				{
					SetQuantity(val, val2, quantity + 1);
				}
				GUI.enabled = true;
			}
			GUI.EndScrollView();
			Fill(new Rect(958f, 118f, 322f, 682f), "#1E151C");
			GUI.Label(new Rect(980f, 141f, 276f, 43f), Localization.Text("Panier", "Cart", "Warenkorb", "Carrito"), title);
			List<(ShopListing, int)> list2 = new List<(ShopListing, int)>();
			enumerator = val.Listings.GetEnumerator();
			while (enumerator.MoveNext())
			{
				ShopListing current2 = enumerator.Current;
				int quantity2 = GetQuantity(val, current2);
				if (quantity2 > 0)
				{
					list2.Add((current2, quantity2));
				}
			}
			float num4 = (flag ? 300 : 405);
			cartScroll = GUI.BeginScrollView(new Rect(976f, 194f, 294f, num4), cartScroll, new Rect(0f, 0f, 272f, Mathf.Max(num4 - 10f, (float)(list2.Count * 68))));
			for (int k = 0; k < list2.Count; k++)
			{
				(ShopListing, int) tuple = list2[k];
				GUI.Label(new Rect(0f, (float)(k * 68), 235f, 59f), tuple.Item2 + " × " + ((BaseItemDefinition)tuple.Item1.Item).Name + "\n" + (tuple.Item1.Price * (float)tuple.Item2).ToString("0.##") + " $", body);
				if (GUI.Button(new Rect(238f, (float)(k * 68 + 10), 27f, 29f), "×", button))
				{
					SetQuantity(val, tuple.Item1, 0);
				}
			}
			GUI.EndScrollView();
			if (flag)
			{
				GUI.Label(new Rect(981f, 505f, 270f, 20f), Localization.Text("Destination", "Destination", "Ziel", "Destino"), small);
				Dropdown destinationDropdown = delivery.DestinationDropdown;
				if (Cycle(new Rect(981f, 527f, 274f, 36f), destinationDropdown))
				{
					delivery.DestinationDropdownSelected(destinationDropdown.value);
				}
				GUI.Label(new Rect(981f, 569f, 270f, 20f), Localization.Text("Quai de chargement", "Loading dock", "Laderampe", "Muelle de carga"), small);
				Dropdown loadingDockDropdown = delivery.LoadingDockDropdown;
				if (Cycle(new Rect(981f, 590f, 274f, 32f), loadingDockDropdown))
				{
					delivery.LoadingDockDropdownSelected(loadingDockDropdown.value);
				}
				GUI.Label(new Rect(981f, 629f, 274f, 25f), Localization.Text("Livraison : ", "Delivery: ", "Lieferung: ", "Entrega: ") + delivery.GetDeliveryFee().ToString("0.##") + " $", small);
			}
			else if ((Object)(object)val.Cart.LoadVehicleToggle != (Object)null && ((Selectable)val.Cart.LoadVehicleToggle).interactable)
			{
				val.Cart.LoadVehicleToggle.isOn = GUI.Toggle(new Rect(982f, 617f, 272f, 29f), val.Cart.LoadVehicleToggle.isOn, Localization.Text("Charger mon véhicule", "Load my vehicle", "Mein Fahrzeug beladen", "Cargar mi vehículo"));
			}
			float num5 = (flag ? (delivery.GetCartCost() + delivery.GetDeliveryFee()) : val.Cart.GetPriceSum());
			GUI.Label(new Rect(981f, 660f, 278f, 38f), "Total : " + num5.ToString("0.##") + " $", title);
			GUI.Label(new Rect(981f, 700f, 276f, 43f), notice, small);
			GUI.enabled = list2.Count > 0;
			if (GUI.Button(new Rect(980f, 750f, 276f, 35f), Localization.Text("VALIDER LA COMMANDE", "CONFIRM ORDER", "BESTELLUNG BESTÄTIGEN", "CONFIRMAR PEDIDO"), checkout))
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
			GUI.enabled = true;
			lastDraw = Time.unscaledTime;
			ShopInterface? obj = counter;
			if ((Object)(object)((obj != null) ? obj.Canvas : null) != (Object)null)
			{
				((Behaviour)counter.Canvas).enabled = false;
			}
		}
		catch (Exception ex)
		{
			MelonLogger.Error("Boutique 666 : retour à l'interface native : " + ex);
			retryAfter = Time.unscaledTime + 10f;
			lastDraw = -100f;
			ShopInterface? obj2 = counter;
			if ((Object)(object)((obj2 != null) ? obj2.Canvas : null) != (Object)null)
			{
				((Behaviour)counter.Canvas).enabled = true;
			}
			Clear();
		}
		finally
		{
			GUI.enabled = true;
			GUI.matrix = matrix;
			GUI.depth = depth;
		}
	}

	private static bool Cycle(Rect rect, Dropdown dropdown)
	{
		//IL_0024: Unknown result type (might be due to invalid IL or missing references)
		//IL_007a: Unknown result type (might be due to invalid IL or missing references)
		if ((Object)(object)dropdown == (Object)null || dropdown.options == null || dropdown.options.Count == 0)
		{
			GUI.enabled = false;
			GUI.Button(rect, Localization.Text("Indisponible", "Unavailable", "Nicht verfügbar", "No disponible"), button);
			GUI.enabled = true;
			return false;
		}
		bool flag2 = (GUI.enabled = true);
		string text = (flag2 ? dropdown.options[dropdown.value].text : Localization.Text("Indisponible", "Unavailable", "Nicht verfügbar", "No disponible"));
		bool num = GUI.Button(rect, text, button);
		GUI.enabled = true;
		if (num && flag2)
		{
			dropdown.value = (dropdown.value + 1) % dropdown.options.Count;
			return true;
		}
		return false;
	}

	private static int Group(string id)
	{
		if (id.EndsWith("seed", StringComparison.Ordinal))
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

	private static int GetQuantity(ShopInterface shop, ShopListing listing)
	{
		if ((Object)(object)delivery != (Object)null && delivery.IsOpen)
		{
			Enumerator<ListingEntry> enumerator = delivery.listingEntries.GetEnumerator();
			while (enumerator.MoveNext())
			{
				ListingEntry current = enumerator.Current;
				if (((BaseItemDefinition)current.MatchingListing.Item).ID == ((BaseItemDefinition)listing.Item).ID)
				{
					return current.SelectedQuantity;
				}
			}
			return 0;
		}
		return shop.Cart.GetCartCount(listing);
	}

	private static void SetQuantity(ShopInterface shop, ShopListing listing, int quantity)
	{
		quantity = Mathf.Clamp(quantity, 0, ShopInterface.MAX_ITEM_QUANTITY);
		notice = "";
		if ((Object)(object)delivery != (Object)null && delivery.IsOpen)
		{
			Enumerator<ListingEntry> enumerator = delivery.listingEntries.GetEnumerator();
			while (enumerator.MoveNext())
			{
				ListingEntry current = enumerator.Current;
				if (((BaseItemDefinition)current.MatchingListing.Item).ID == ((BaseItemDefinition)listing.Item).ID)
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
