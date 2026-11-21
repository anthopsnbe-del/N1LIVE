using System;
using System.Collections.Generic;
using Il2CppScheduleOne.Core.Items.Framework;
using Il2CppScheduleOne.PlayerScripts;
using Il2CppScheduleOne.Product;
using MelonLoader;
using UnityEngine;

namespace VarietesEtendues;

/// <summary>
/// Effets actifs des joueurs.
/// CORRECTIF : avant, les effets restaient actifs tant que Player.ConsumedProduct
/// était renseigné — or le jeu ne le vide presque jamais (Il2CppScheduleOne.PlayerScripts.Player.ClearProduct),
/// d'où une vitesse du Diable qui ne s'arrêtait jamais. Chaque consommation
/// démarre maintenant un minuteur (Settings.EffectDuration, 2 min par défaut) ;
/// à l'échéance, la liste d'effets devient vide et tout est retiré net
/// (vitesse, saut, gravité, apparence, vision).
/// </summary>
internal static class ActiveConsumption
{
	private readonly struct Timer
	{
		internal readonly string Product;

		internal readonly float Start;

		internal Timer(string product, float start)
		{
			Product = product;
			Start = start;
		}
	}

	private static readonly ConsumptionSession session = new ConsumptionSession();

	private static readonly System.Collections.Generic.Dictionary<int, Timer> timers = new System.Collections.Generic.Dictionary<int, Timer>();

	private static readonly System.Collections.Generic.HashSet<int> expiredLogged = new System.Collections.Generic.HashSet<int>();

	internal static string[] PlayerIds(Il2CppScheduleOne.PlayerScripts.Player player)
	{
		if ((UnityEngine.Object)(object)player == (UnityEngine.Object)null)
		{
			return System.Array.Empty<string>();
		}
		Il2CppScheduleOne.Product.ProductItemInstance consumedProduct = player.ConsumedProduct;
		if (consumedProduct == null)
		{
			return System.Array.Empty<string>();
		}
		int instanceID = ((UnityEngine.Object)player).GetInstanceID();
		string iD = ((Il2CppScheduleOne.Core.Items.Framework.BaseItemInstance)consumedProduct).ID;
		if (player.IsLocalPlayer && !session.Allows(instanceID, iD))
		{
			return System.Array.Empty<string>();
		}
		// Joueur distant dont on n'a pas vu la consommation : on démarre le minuteur
		// à la première observation (ou quand le produit change).
		if (!timers.TryGetValue(instanceID, out var value) || value.Product != iD)
		{
			value = new Timer(iD, UnityEngine.Time.time);
			timers[instanceID] = value;
			expiredLogged.Remove(instanceID);
		}
		if (UnityEngine.Time.time - value.Start >= Settings.EffectDuration)
		{
			if (expiredLogged.Add(instanceID) && player.IsLocalPlayer)
			{
				MelonLoader.MelonLogger.Msg("Effets du Comptoir terminés (" + Settings.EffectDuration.ToString("0") + " s écoulées).");
			}
			return System.Array.Empty<string>();
		}
		return DevilReplication.EffectIds(consumedProduct);
	}

	/// <summary>Secondes restantes pour ce joueur (0 si aucun effet).</summary>
	internal static float Remaining(Il2CppScheduleOne.PlayerScripts.Player player)
	{
		if ((UnityEngine.Object)(object)player == (UnityEngine.Object)null || !timers.TryGetValue(((UnityEngine.Object)player).GetInstanceID(), out var value))
		{
			return 0f;
		}
		return UnityEngine.Mathf.Max(0f, Settings.EffectDuration - (UnityEngine.Time.time - value.Start));
	}

	internal static bool IsPlayer(UnityEngine.Transform actor, out Il2CppScheduleOne.PlayerScripts.Player? player)
	{
		player = (((UnityEngine.Object)(object)actor != (UnityEngine.Object)null) ? ((UnityEngine.Component)actor).GetComponent<Il2CppScheduleOne.PlayerScripts.Player>() : null);
		return (UnityEngine.Object)(object)player != (UnityEngine.Object)null;
	}

	internal static void Consumed(Il2CppScheduleOne.PlayerScripts.Player player, Il2CppScheduleOne.Product.ProductItemInstance product)
	{
		if ((UnityEngine.Object)(object)player == (UnityEngine.Object)null)
		{
			return;
		}
		int instanceID = ((UnityEngine.Object)player).GetInstanceID();
		string text = ((product != null) ? ((Il2CppScheduleOne.Core.Items.Framework.BaseItemInstance)product).ID : null);
		// Chaque nouvelle consommation (même produit) relance les 2 minutes.
		if (text != null)
		{
			timers[instanceID] = new Timer(text, UnityEngine.Time.time);
		}
		else
		{
			timers.Remove(instanceID);
		}
		expiredLogged.Remove(instanceID);
		if (player.IsLocalPlayer)
		{
			session.Consumed(instanceID, text);
			MelonLoader.MelonLogger.Msg("Consommation locale confirmée : " + (text ?? "aucun produit"));
		}
		CharacterEffects.Reconcile(((UnityEngine.Component)player).transform, PlayerIds(player));
	}

	internal static void Clear(Il2CppScheduleOne.PlayerScripts.Player player)
	{
		if ((UnityEngine.Object)(object)player != (UnityEngine.Object)null)
		{
			int instanceID = ((UnityEngine.Object)player).GetInstanceID();
			session.Clear(instanceID);
			timers.Remove(instanceID);
			expiredLogged.Remove(instanceID);
		}
	}

	internal static void ClearAll()
	{
		session.ClearAll();
		timers.Clear();
		expiredLogged.Clear();
	}
}
