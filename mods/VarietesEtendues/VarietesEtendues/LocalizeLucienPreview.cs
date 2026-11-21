using HarmonyLib;
using Il2CppScheduleOne.Messaging;

namespace VarietesEtendues;

[HarmonyPatch(typeof(MSGConversation), "RefreshPreviewText")]
internal static class LocalizeLucienPreview
{
	[HarmonyPostfix]
	private static void Postfix(MSGConversation __instance)
	{
		Localization.Refresh(__instance);
	}
}
