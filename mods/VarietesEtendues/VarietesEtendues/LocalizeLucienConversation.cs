using HarmonyLib;
using Il2CppScheduleOne.Messaging;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.Messaging.MSGConversation), "SetOpen")]
internal static class LocalizeLucienConversation
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.Messaging.MSGConversation __instance)
	{
		Localization.Refresh(__instance);
	}
}
