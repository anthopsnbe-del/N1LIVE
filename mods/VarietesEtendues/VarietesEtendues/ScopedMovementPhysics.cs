using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.PlayerScripts.PlayerMovement), "FixedUpdate")]
internal static class ScopedMovementPhysics
{
	[HarmonyPrefix]
	private static void Prefix(Il2CppScheduleOne.PlayerScripts.PlayerMovement __instance, out bool __state)
	{
		__state = MovementScope.Enter(__instance);
	}

	[HarmonyFinalizer]
	private static void Finalizer(bool __state)
	{
		MovementScope.Leave(__state);
	}
}
