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
/// était renseigné — or le jeu ne le vide presque jamais (Player.ClearProduct),
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

	private static readonly Dictionary<int, Timer> timers = new Dictionary<int, Timer>();

	private static readonly HashSet<int> expiredLogged = new HashSet<int>();

	internal static string[] PlayerIds(Player player)
	{
		if ((Object)(object)player == (Object)null)
		{
			return Array.Empty<string>();
		}
		ProductItemInstance consumedProduct = player.ConsumedProduct;
		if (consumedProduct == null)
		{
			return Array.Empty<string>();
		}
		int instanceID = ((Object)player).GetInstanceID();
		string iD = ((BaseItemInstance)consumedProduct).ID;
		if (player.IsLocalPlayer && !session.Allows(instanceID, iD))
		{
			return Array.Empty<string>();
		}
		// Joueur distant dont on n'a pas vu la consommation : on démarre le minuteur
		// à la première observation (ou quand le produit change).
		if (!timers.TryGetValue(instanceID, out var value) || value.Product != iD)
		{
			value = new Timer(iD, Time.time);
			timers[instanceID] = value;
			expiredLogged.Remove(instanceID);
		}
		if (Time.time - value.Start >= Settings.EffectDuration)
		{
			if (expiredLogged.Add(instanceID) && player.IsLocalPlayer)
			{
				MelonLogger.Msg("Effets du Comptoir terminés (" + Settings.EffectDuration.ToString("0") + " s écoulées).");
			}
			return Array.Empty<string>();
		}
		return DevilReplication.EffectIds(consumedProduct);
	}

	/// <summary>Secondes restantes pour ce joueur (0 si aucun effet).</summary>
	internal static float Remaining(Player player)
	{
		if ((Object)(object)player == (Object)null || !timers.TryGetValue(((Object)player).GetInstanceID(), out var value))
		{
			return 0f;
		}
		return Mathf.Max(0f, Settings.EffectDuration - (Time.time - value.Start));
	}

	internal static bool IsPlayer(Transform actor, out Player? player)
	{
		player = (((Object)(object)actor != (Object)null) ? ((Component)actor).GetComponent<Player>() : null);
		return (Object)(object)player != (Object)null;
	}

	internal static void Consumed(Player player, ProductItemInstance product)
	{
		if ((Object)(object)player == (Object)null)
		{
			return;
		}
		int instanceID = ((Object)player).GetInstanceID();
		string text = ((product != null) ? ((BaseItemInstance)product).ID : null);
		// Chaque nouvelle consommation (même produit) relance les 2 minutes.
		if (text != null)
		{
			timers[instanceID] = new Timer(text, Time.time);
		}
		else
		{
			timers.Remove(instanceID);
		}
		expiredLogged.Remove(instanceID);
		if (player.IsLocalPlayer)
		{
			session.Consumed(instanceID, text);
			MelonLogger.Msg("Consommation locale confirmée : " + (text ?? "aucun produit"));
		}
		CharacterEffects.Reconcile(((Component)player).transform, PlayerIds(player));
	}

	internal static void Clear(Player player)
	{
		if ((Object)(object)player != (Object)null)
		{
			int instanceID = ((Object)player).GetInstanceID();
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
