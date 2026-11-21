using HarmonyLib;
using Il2CppScheduleOne.Messaging;

namespace VarietesEtendues;

[HarmonyPatch(typeof(Il2CppScheduleOne.Messaging.MSGConversation), "RenderMessage")]
internal static class LocalizeLucienMessage
{
	[HarmonyPostfix]
	private static void Postfix(Il2CppScheduleOne.Messaging.MSGConversation __instance)
	{
		Localization.Refresh(__instance);
	}
}
