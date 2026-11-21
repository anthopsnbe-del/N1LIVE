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
	private sealed record Binding(DialogueController Controller, DialogueChoice Choice);

	private static readonly Dictionary<int, Binding> bindings = new Dictionary<int, Binding>();

	internal static void Clear()
	{
		bindings.Clear();
	}

	internal static void Refresh(Lucien lucien)
	{
		//IL_00b6: Unknown result type (might be due to invalid IL or missing references)
		//IL_00bb: Unknown result type (might be due to invalid IL or missing references)
		//IL_00c2: Unknown result type (might be due to invalid IL or missing references)
		//IL_00d7: Unknown result type (might be due to invalid IL or missing references)
		//IL_00de: Unknown result type (might be due to invalid IL or missing references)
		//IL_00df: Unknown result type (might be due to invalid IL or missing references)
		//IL_00e9: Expected O, but got Unknown
		//IL_00e9: Unknown result type (might be due to invalid IL or missing references)
		//IL_00f3: Expected O, but got Unknown
		//IL_014d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0153: Invalid comparison between Unknown and I4
		Lucien lucien2 = lucien;
		Supplier supplier = ((NPC)lucien2).gameObject.GetComponent<Supplier>();
		Supplier obj = supplier;
		DialogueHandler handler = ((obj != null) ? ((NPC)obj).DialogueHandler : null);
		Supplier obj2 = supplier;
		if ((Object)(object)((obj2 != null) ? obj2.Shop : null) == (Object)null || (Object)(object)handler == (Object)null)
		{
			return;
		}
		DialogueController val = ((Component)handler).GetComponent<DialogueController>() ?? ((Component)((NPC)lucien2).Transform).GetComponentInChildren<DialogueController>(true);
		if ((Object)(object)val == (Object)null)
		{
			return;
		}
		int instanceID = ((Object)val).GetInstanceID();
		if (!bindings.TryGetValue(instanceID, out Binding value) || (Object)(object)value.Controller != (Object)(object)val)
		{
			DialogueChoice val2 = new DialogueChoice
			{
				Enabled = false,
				ChoiceText = Localization.Text("Acheter au Comptoir 666", "Buy from the 666 Counter", "Am 666-Tresen kaufen", "Comprar en el Mostrador 666"),
				ShowWorldspaceDialogue = false,
				onChoosen = new UnityEvent(),
				Priority = 100
			};
			val2.onChoosen.AddListener(UnityAction.op_Implicit((Action)delegate
			{
				if (!((Object)(object)supplier == (Object)null) && !((Object)(object)supplier.Shop == (Object)null) && ((NPC)lucien2).IsVisible)
				{
					handler.EndDialogue();
					supplier.Shop.Open();
					DevilShop.Counter(supplier.Shop, open: true);
					MelonLogger.Msg("Lucien : ouverture des achats depuis son dialogue.");
				}
			}));
			val.AddDialogueChoice(val2, 100);
			value = new Binding(val, val2);
			bindings[instanceID] = value;
			MelonLogger.Msg("Lucien : choix d'achat relié au contrôleur de rendez-vous.");
		}
		bool flag = ((NPC)lucien2).IsVisible && (int)supplier.Status == 2;
		value.Choice.Enabled = flag;
		if (flag)
		{
			val.SetDialogueEnabled(true);
			if ((Object)(object)val._interactable != (Object)null && !handler.IsDialogueInProgress && !supplier.Shop.IsOpen)
			{
				val._interactable.SetInteractionType((EInteractionType)0);
				val._interactable.SetInteractableState((EInteractableState)0);
				val._interactable.SetMessage(Localization.Text("Parler à Lucien", "Talk to Lucien", "Mit Lucien sprechen", "Hablar con Lucien"));
			}
		}
	}
}
