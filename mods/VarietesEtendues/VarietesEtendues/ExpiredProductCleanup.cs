using System;
using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts;
using UnityEngine;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.PlayerScripts.Player), "ClearProduct")]
internal static class ExpiredProductCleanup
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.PlayerScripts.Player __instance)
	{
		ActiveConsumption.Clear(__instance);
		CharacterEffects.Reconcile(((UnityEngine.Component)__instance).transform, System.Array.Empty<string>());
		if (__instance.IsLocalPlayer)
		{
			GameplayEffects.Clear();
		}
	}
}
