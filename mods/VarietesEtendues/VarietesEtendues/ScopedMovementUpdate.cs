using HarmonyLib;
using Il2CppScheduleOne.PlayerScripts;

namespace VarietesEtendues;

[HarmonyPatch(typeof(PlayerMovement), "Update")]
internal static class ScopedMovementUpdate
{
	[HarmonyPrefix]
	private static void Prefix(PlayerMovement __instance, out bool __state)
	{
		__state = MovementScope.Enter(__instance);
	}

	[HarmonyFinalizer]
	private static void Finalizer(bool __state)
	{
		MovementScope.Leave(__state);
	}
}
