using HarmonyLib;
using Il2CppScheduleOne.Messaging;

namespace VarietesEtendues;

[HarmonyPatch(typeof(MSGConversation), "RenderMessage")]
internal static class LocalizeLucienMessage
{
	[HarmonyPostfix]
	private static void Postfix(MSGConversation __instance)
	{
		Localization.Refresh(__instance);
	}
}
