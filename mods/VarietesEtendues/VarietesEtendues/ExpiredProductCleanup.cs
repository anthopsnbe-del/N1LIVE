using System;
using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Player), "ClearProduct")]
internal static class ExpiredProductCleanup
{
	[HarmonyPostfix]
	private static void Postfix(Player __instance)
	{
		ActiveConsumption.Clear(__instance);
		CharacterEffects.Reconcile(((Component)__instance).transform, Array.Empty<string>());
		if (__instance.IsLocalPlayer)
		{
			GameplayEffects.Clear();
		}
	}
}
