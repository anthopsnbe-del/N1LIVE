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

[HarmonyPatch(typeof(DeliveryApp), "SetOpen")]
internal static class SupplierUI
{
	private static readonly HashSet<int> prepared = new HashSet<int>();

	internal static DeliveryApp? App;

	internal static void Clear()
	{
		prepared.Clear();
		App = null;
	}

	[HarmonyPostfix]
	private static void Postfix(DeliveryApp __instance, bool __0)
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
		catch (Exception ex)
		{
			MelonLogger.Error("Lucien : initialisation du catalogue : " + ex);
		}
	}

	internal static void Refresh(DeliveryApp app)
	{
		//IL_0145: Unknown result type (might be due to invalid IL or missing references)
		//IL_014f: Expected O, but got Unknown
		//IL_02cd: Unknown result type (might be due to invalid IL or missing references)
		//IL_0421: Unknown result type (might be due to invalid IL or missing references)
		DeliveryApp app2 = app;
		Lucien lucien = NPC.All.OfType<Lucien>().FirstOrDefault();
		Supplier val = ((lucien != null) ? ((NPC)lucien).gameObject.GetComponent<Supplier>() : null);
		if (lucien == null || (Object)(object)((val != null) ? val.Shop : null) == (Object)null)
		{
			return;
		}
		DeliveryApp obj = app2;
		if (((obj != null) ? obj._shopElements : null) == null)
		{
			return;
		}
		Enumerator<DeliveryShopElement> enumerator = app2._shopElements.GetEnumerator();
		while (enumerator.MoveNext())
		{
			DeliveryShopElement current = enumerator.Current;
			if ((Object)(object)((current != null) ? current.Shop : null) == (Object)null || (Object)(object)current.Button == (Object)null || current.Shop.MatchingShopInterfaceName != val.Shop.ShopName)
			{
				continue;
			}
			if (!prepared.Contains(((Object)current.Button).GetInstanceID()))
			{
				DeliveryShop deliveryShop = current.Shop;
				if ((Object)(object)deliveryShop.MatchingShop == (Object)null || deliveryShop.listingEntries == null || deliveryShop.listingEntries.Count == 0)
				{
					deliveryShop.Initialize();
				}
				current.Button.onClick = new ButtonClickedEvent();
				((UnityEvent)current.Button.onClick).AddListener(UnityAction.op_Implicit((Action)delegate
				{
					app2.OpenShop(deliveryShop);
				}));
				Action<DeliveryShop> onSelect = DelegateSupport.ConvertDelegate<Action<DeliveryShop>>((Delegate)new Action<DeliveryShop>(app2.CloseShop));
				deliveryShop.OnSelect = onSelect;
				if ((Object)(object)app2._deliveryScreen != (Object)null)
				{
					Enumerator<UIPanel> enumerator2 = deliveryShop.Panels.GetEnumerator();
					while (enumerator2.MoveNext())
					{
						UIPanel current2 = enumerator2.Current;
						app2._deliveryScreen.AddPanel(current2);
					}
				}
				prepared.Add(((Object)current.Button).GetInstanceID());
				MelonLogger.Msg("Lucien : bouton de commande relié au catalogue.");
			}
			((Selectable)current.Button).interactable = true;
			Il2CppArrayBase<Text> componentsInChildren = ((Component)current.Button).GetComponentsInChildren<Text>(true);
			if (componentsInChildren.Length > 0)
			{
				componentsInChildren[0].text = Localization.Text("Lucien — Comptoir des Variétés", "Lucien — Variety Counter", "Lucien — Sortentresen", "Lucien — Mostrador de Variedades");
			}
			if (componentsInChildren.Length > 1)
			{
				componentsInChildren[1].text = Localization.Text("Ingrédients pour tes mélanges", "Ingredients for your mixes", "Zutaten für deine Mischungen", "Ingredientes para tus mezclas");
			}
			Sprite val2 = DevilPortrait.Image ?? ((NPC)lucien).Icon;
			foreach (Image componentsInChild in ((Component)current.Button).GetComponentsInChildren<Image>(true))
			{
				if (IsPortrait(componentsInChild) && (Object)(object)val2 != (Object)null)
				{
					componentsInChild.sprite = val2;
					((Graphic)componentsInChild).color = Color.white;
				}
			}
			foreach (Text componentsInChild2 in ((Component)current.Shop).GetComponentsInChildren<Text>(true))
			{
				if (!((Object)(object)current.Shop.ListingContainer != (Object)null) || !((Component)componentsInChild2).transform.IsChildOf((Transform)(object)current.Shop.ListingContainer))
				{
					string text = ((Object)componentsInChild2).name.ToLowerInvariant();
					if (text.Contains("shopname") || text.Contains("storename") || componentsInChild2.text.Contains("Quincaillerie", StringComparison.OrdinalIgnoreCase) || componentsInChild2.text.Contains("Hardware", StringComparison.OrdinalIgnoreCase))
					{
						componentsInChild2.text = "Lucien — 666";
					}
				}
			}
			foreach (Image componentsInChild3 in ((Component)current.Shop).GetComponentsInChildren<Image>(true))
			{
				if ((!((Object)(object)current.Shop.ListingContainer != (Object)null) || !((Component)componentsInChild3).transform.IsChildOf((Transform)(object)current.Shop.ListingContainer)) && IsPortrait(componentsInChild3) && (Object)(object)val2 != (Object)null)
				{
					componentsInChild3.sprite = val2;
					((Graphic)componentsInChild3).color = Color.white;
				}
			}
		}
	}

	private static bool IsPortrait(Image image)
	{
		//IL_009a: Unknown result type (might be due to invalid IL or missing references)
		//IL_009f: Unknown result type (might be due to invalid IL or missing references)
		//IL_00a2: Unknown result type (might be due to invalid IL or missing references)
		//IL_00a7: Unknown result type (might be due to invalid IL or missing references)
		//IL_00a8: Unknown result type (might be due to invalid IL or missing references)
		//IL_00c8: Unknown result type (might be due to invalid IL or missing references)
		//IL_00cd: Unknown result type (might be due to invalid IL or missing references)
		//IL_00b5: Unknown result type (might be due to invalid IL or missing references)
		//IL_00ce: Unknown result type (might be due to invalid IL or missing references)
		//IL_00d4: Unknown result type (might be due to invalid IL or missing references)
		//IL_00e7: Unknown result type (might be due to invalid IL or missing references)
		//IL_00f4: Unknown result type (might be due to invalid IL or missing references)
		string name = ((Object)image).name;
		Sprite sprite = image.sprite;
		string text = (name + " " + ((sprite != null) ? ((Object)sprite).name : null)).ToLowerInvariant();
		if (text.Contains("arrow") || text.Contains("back") || text.Contains("check") || text.Contains("background"))
		{
			return false;
		}
		if (text.Contains("icon") || text.Contains("portrait") || text.Contains("mugshot") || text.Contains("avatar"))
		{
			return true;
		}
		Rect rect = ((Graphic)image).rectTransform.rect;
		Vector2 val = ((Rect)(ref rect)).size;
		if (val.x < 1f || val.y < 1f)
		{
			val = ((Graphic)image).rectTransform.sizeDelta;
		}
		if (Mathf.Abs(val.x - val.y) < 4f && val.x >= 24f)
		{
			return val.x <= 100f;
		}
		return false;
	}
}
