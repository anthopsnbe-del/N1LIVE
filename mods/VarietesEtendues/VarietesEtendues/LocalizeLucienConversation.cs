using HarmonyLib;
using Il2CppScheduleOne.Messaging;

namespace VarietesEtendues;

[HarmonyPatch(typeof(MSGConversation), "SetOpen")]
internal static class LocalizeLucienConversation
{
	[HarmonyPostfix]
	private static void Postfix(MSGConversation __instance)
	{
		Localization.Refresh(__instance);
	}
}
