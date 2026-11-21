using System;
using System.Collections.Generic;
using Il2CppScheduleOne.Dialogue;
using Il2CppScheduleOne.Economy;
using Il2CppScheduleOne.Interaction;
using Il2CppScheduleOne.NPCs;
using MelonLoader;
using S1API.Entities;
using UnityEngine;
using UnityEngine.Events;

namespace VarietesEtendues;

internal static class LucienCounter
{
	private sealed record Binding(Il2CppScheduleOne.Dialogue.DialogueController Controller, Il2CppScheduleOne.Dialogue.DialogueController.DialogueChoice Choice);

	private static readonly System.Collections.Generic.Dictionary<int, Binding> bindings = new System.Collections.Generic.Dictionary<int, Binding>();

	internal static void Clear()
	{
		bindings.Clear();
	}

	internal static void Refresh(Lucien lucien)
	{
		Lucien lucien2 = lucien;
		Il2CppScheduleOne.Economy.Supplier supplier = ((S1API.Entities.NPC)lucien2).gameObject.GetComponent<Il2CppScheduleOne.Economy.Supplier>();
		Il2CppScheduleOne.Economy.Supplier obj = supplier;
		Il2CppScheduleOne.Dialogue.DialogueHandler handler = ((obj != null) ? ((Il2CppScheduleOne.NPCs.NPC)obj).DialogueHandler : null);
		Il2CppScheduleOne.Economy.Supplier obj2 = supplier;
		if ((UnityEngine.Object)(object)((obj2 != null) ? obj2.Shop : null) == (UnityEngine.Object)null || (UnityEngine.Object)(object)handler == (UnityEngine.Object)null)
		{
			return;
		}
		Il2CppScheduleOne.Dialogue.DialogueController val = ((UnityEngine.Component)handler).GetComponent<Il2CppScheduleOne.Dialogue.DialogueController>() ?? ((UnityEngine.Component)((S1API.Entities.NPC)lucien2).Transform).GetComponentInChildren<Il2CppScheduleOne.Dialogue.DialogueController>(true);
		if ((UnityEngine.Object)(object)val == (UnityEngine.Object)null)
		{
			return;
		}
		int instanceID = ((UnityEngine.Object)val).GetInstanceID();
		if (!bindings.TryGetValue(instanceID, out Binding value) || (UnityEngine.Object)(object)value.Controller != (UnityEngine.Object)(object)val)
		{
			Il2CppScheduleOne.Dialogue.DialogueController.DialogueChoice val2 = new Il2CppScheduleOne.Dialogue.DialogueController.DialogueChoice
			{
				Enabled = false,
				ChoiceText = Localization.Text("Acheter au Comptoir 666", "Buy from the 666 Counter", "Am 666-Tresen kaufen", "Comprar en el Mostrador 666"),
				ShowWorldspaceDialogue = false,
				onChoosen = new UnityEvent(),
				Priority = 100
			};
			val2.onChoosen.AddListener(((System.Action)delegate
			{
				if (!((UnityEngine.Object)(object)supplier == (UnityEngine.Object)null) && !((UnityEngine.Object)(object)supplier.Shop == (UnityEngine.Object)null) && ((S1API.Entities.NPC)lucien2).IsVisible)
				{
					handler.EndDialogue();
					supplier.Shop.Open();
					DevilShop.Counter(supplier.Shop, open: true);
					MelonLoader.MelonLogger.Msg("Lucien : ouverture des achats depuis son dialogue.");
				}
			}));
			val.AddDialogueChoice(val2, 100);
			value = new Binding(val, val2);
			bindings[instanceID] = value;
			MelonLoader.MelonLogger.Msg("Lucien : choix d'achat relié au contrôleur de rendez-vous.");
		}
		bool flag = ((S1API.Entities.NPC)lucien2).IsVisible && (int)supplier.Status == 2;
		value.Choice.Enabled = flag;
		if (flag)
		{
			val.SetDialogueEnabled(true);
			if ((UnityEngine.Object)(object)val._interactable != (UnityEngine.Object)null && !handler.IsDialogueInProgress && !supplier.Shop.IsOpen)
			{
				val._interactable.SetInteractionType((Il2CppScheduleOne.Interaction.InteractableObject.EInteractionType)0);
				val._interactable.SetInteractableState((Il2CppScheduleOne.Interaction.InteractableObject.EInteractableState)0);
				val._interactable.SetMessage(Localization.Text("Parler à Lucien", "Talk to Lucien", "Mit Lucien sprechen", "Hablar con Lucien"));
			}
		}
	}
}
