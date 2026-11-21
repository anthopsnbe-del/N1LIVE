using System;
using System.Collections.Generic;
using System.Linq;
using HarmonyLib;
using Il2CppInterop.Runtime;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne;
using Il2CppScheduleOne.Economy;
using Il2CppScheduleOne.UI.Phone.Delivery;
using Il2CppSystem;
using Il2CppSystem.Collections.Generic;
using MelonLoader;
using S1API.Entities;
using UnityEngine;
using UnityEngine.Events;
using UnityEngine.UI;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.UI.Phone.Delivery.DeliveryApp), "SetOpen")]
internal static class SupplierUI
{
	private static readonly System.Collections.Generic.HashSet<int> prepared = new System.Collections.Generic.HashSet<int>();

	internal static Il2CppScheduleOne.UI.Phone.Delivery.DeliveryApp? App;

	internal static void Clear()
	{
		prepared.Clear();
		App = null;
	}

	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.UI.Phone.Delivery.DeliveryApp __instance, bool __0)
	{
		App = __instance;
		if (!__0)
		{
			DevilShop.PhoneClosed();
			return;
		}
		try
		{
			Refresh(__instance);
		}
		catch (System.Exception ex)
		{
			MelonLoader.MelonLogger.Error("Lucien : initialisation du catalogue : " + ex);
		}
	}

	internal static void Refresh(Il2CppScheduleOne.UI.Phone.Delivery.DeliveryApp app)
	{
		Il2CppScheduleOne.UI.Phone.Delivery.DeliveryApp app2 = app;
		Lucien lucien = S1API.Entities.NPC.All.OfType<Lucien>().FirstOrDefault();
		Il2CppScheduleOne.Economy.Supplier val = ((lucien != null) ? ((S1API.Entities.NPC)lucien).gameObject.GetComponent<Il2CppScheduleOne.Economy.Supplier>() : null);
		if (lucien == null || (UnityEngine.Object)(object)((val != null) ? val.Shop : null) == (UnityEngine.Object)null)
		{
			return;
		}
		Il2CppScheduleOne.UI.Phone.Delivery.DeliveryApp obj = app2;
		if (((obj != null) ? obj._shopElements : null) == null)
		{
			return;
		}
		var enumerator = app2._shopElements.GetEnumerator();
		while (enumerator.MoveNext())
		{
			Il2CppScheduleOne.UI.Phone.Delivery.DeliveryApp.DeliveryShopElement current = enumerator.Current;
			if ((UnityEngine.Object)(object)((current != null) ? current.Shop : null) == (UnityEngine.Object)null || (UnityEngine.Object)(object)current.Button == (UnityEngine.Object)null || current.Shop.MatchingShopInterfaceName != val.Shop.ShopName)
			{
				continue;
			}
			if (!prepared.Contains(((UnityEngine.Object)current.Button).GetInstanceID()))
			{
				Il2CppScheduleOne.UI.Phone.Delivery.DeliveryShop deliveryShop = current.Shop;
				if ((UnityEngine.Object)(object)deliveryShop.MatchingShop == (UnityEngine.Object)null || deliveryShop.listingEntries == null || deliveryShop.listingEntries.Count == 0)
				{
					deliveryShop.Initialize();
				}
				current.Button.onClick = new UnityEngine.UI.Button.ButtonClickedEvent();
				((UnityEngine.Events.UnityEvent)current.Button.onClick).AddListener(((System.Action)delegate
				{
					app2.OpenShop(deliveryShop);
				}));
				Il2CppSystem.Action<Il2CppScheduleOne.UI.Phone.Delivery.DeliveryShop> onSelect = Il2CppInterop.Runtime.DelegateSupport.ConvertDelegate<Il2CppSystem.Action<Il2CppScheduleOne.UI.Phone.Delivery.DeliveryShop>>((System.Delegate)new System.Action<Il2CppScheduleOne.UI.Phone.Delivery.DeliveryShop>(app2.CloseShop));
				deliveryShop.OnSelect = onSelect;
				if ((UnityEngine.Object)(object)app2._deliveryScreen != (UnityEngine.Object)null)
				{
					var enumerator2 = deliveryShop.Panels.GetEnumerator();
					while (enumerator2.MoveNext())
					{
						Il2CppScheduleOne.UIPanel current2 = enumerator2.Current;
						app2._deliveryScreen.AddPanel(current2);
					}
				}
				prepared.Add(((UnityEngine.Object)current.Button).GetInstanceID());
				MelonLoader.MelonLogger.Msg("Lucien : bouton de commande relié au catalogue.");
			}
			((UnityEngine.UI.Selectable)current.Button).interactable = true;
			Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.UI.Text> componentsInChildren = ((UnityEngine.Component)current.Button).GetComponentsInChildren<UnityEngine.UI.Text>(true);
			if (componentsInChildren.Length > 0)
			{
				componentsInChildren[0].text = Localization.Text("Lucien — Comptoir des Variétés", "Lucien — Variety Counter", "Lucien — Sortentresen", "Lucien — Mostrador de Variedades");
			}
			if (componentsInChildren.Length > 1)
			{
				componentsInChildren[1].text = Localization.Text("Ingrédients pour tes mélanges", "Ingredients for your mixes", "Zutaten für deine Mischungen", "Ingredientes para tus mezclas");
			}
			UnityEngine.Sprite val2 = DevilPortrait.Image ?? ((S1API.Entities.NPC)lucien).Icon;
			foreach (UnityEngine.UI.Image componentsInChild in ((UnityEngine.Component)current.Button).GetComponentsInChildren<UnityEngine.UI.Image>(true))
			{
				if (IsPortrait(componentsInChild) && (UnityEngine.Object)(object)val2 != (UnityEngine.Object)null)
				{
					componentsInChild.sprite = val2;
					((UnityEngine.UI.Graphic)componentsInChild).color = UnityEngine.Color.white;
				}
			}
			foreach (UnityEngine.UI.Text componentsInChild2 in ((UnityEngine.Component)current.Shop).GetComponentsInChildren<UnityEngine.UI.Text>(true))
			{
				if (!((UnityEngine.Object)(object)current.Shop.ListingContainer != (UnityEngine.Object)null) || !((UnityEngine.Component)componentsInChild2).transform.IsChildOf((UnityEngine.Transform)(object)current.Shop.ListingContainer))
				{
					string text = ((UnityEngine.Object)componentsInChild2).name.ToLowerInvariant();
					if (text.Contains("shopname") || text.Contains("storename") || componentsInChild2.text.Contains("Quincaillerie", System.StringComparison.OrdinalIgnoreCase) || componentsInChild2.text.Contains("Hardware", System.StringComparison.OrdinalIgnoreCase))
					{
						componentsInChild2.text = "Lucien — 666";
					}
				}
			}
			foreach (UnityEngine.UI.Image componentsInChild3 in ((UnityEngine.Component)current.Shop).GetComponentsInChildren<UnityEngine.UI.Image>(true))
			{
				if ((!((UnityEngine.Object)(object)current.Shop.ListingContainer != (UnityEngine.Object)null) || !((UnityEngine.Component)componentsInChild3).transform.IsChildOf((UnityEngine.Transform)(object)current.Shop.ListingContainer)) && IsPortrait(componentsInChild3) && (UnityEngine.Object)(object)val2 != (UnityEngine.Object)null)
				{
					componentsInChild3.sprite = val2;
					((UnityEngine.UI.Graphic)componentsInChild3).color = UnityEngine.Color.white;
				}
			}
		}
	}

	private static bool IsPortrait(UnityEngine.UI.Image image)
	{
		string name = ((UnityEngine.Object)image).name;
		UnityEngine.Sprite sprite = image.sprite;
		string text = (name + " " + ((sprite != null) ? ((UnityEngine.Object)sprite).name : null)).ToLowerInvariant();
		if (text.Contains("arrow") || text.Contains("back") || text.Contains("check") || text.Contains("background"))
		{
			return false;
		}
		if (text.Contains("icon") || text.Contains("portrait") || text.Contains("mugshot") || text.Contains("avatar"))
		{
			return true;
		}
		UnityEngine.Rect rect = ((UnityEngine.UI.Graphic)image).rectTransform.rect;
		UnityEngine.Vector2 val = rect.size;
		if (val.x < 1f || val.y < 1f)
		{
			val = ((UnityEngine.UI.Graphic)image).rectTransform.sizeDelta;
		}
		if (UnityEngine.Mathf.Abs(val.x - val.y) < 4f && val.x >= 24f)
		{
			return val.x <= 100f;
		}
		return false;
	}
}
