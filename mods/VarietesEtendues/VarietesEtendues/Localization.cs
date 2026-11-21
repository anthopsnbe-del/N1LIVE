using System;
using System.Globalization;
using System.Runtime.InteropServices;
using Il2CppScheduleOne.Messaging;
using MelonLoader;
using MelonLoader.Preferences;
using UnityEngine;
using UnityEngine.UI;

namespace VarietesEtendues;

internal enum Lang
{
	French,
	English,
	German,
	Spanish
}

/// <summary>
/// Langue du mod : FR / EN / DE / ES.
/// Ordre de détection :
///   1. Préférence MelonLoader [VarietesEtendues] Language = fr | en | de | es (Auto par défaut)
///   2. Langue d'affichage de Windows (GetUserDefaultUILanguage)
///   3. CultureInfo.CurrentUICulture puis Application.systemLanguage (repli)
///   4. Anglais
/// </summary>
internal static class Localization
{
	private static MelonLoader.MelonPreferences_Entry<string>? language;

	private static Lang? detected;

	private static readonly string[][] Phrases = new string[4][]
	{
		new string[4]
		{
			"Je peux maintenant livrer les ingrédients directement dans tes propriétés. Utilise l'application Livraisons pour passer commande.",
			"I can now deliver supplies directly to your properties. Use the deliveries app to place an order.",
			"Ich kann dir die Zutaten jetzt direkt zu deinen Grundstücken liefern. Nutze die Lieferungen-App, um zu bestellen.",
			"Ahora puedo entregar los ingredientes directamente en tus propiedades. Usa la aplicación de Entregas para hacer un pedido."
		},
		new string[4]
		{
			"Le Comptoir des Variétés est accessible par téléphone.",
			"The Comptoir des Variétés is available by phone.",
			"Der Sortentresen ist telefonisch erreichbar.",
			"El Mostrador de Variedades está disponible por teléfono."
		},
		new string[4]
		{
			"Bienvenue au Comptoir des Variétés ! Retrouve mes 19 ingrédients dans l'application Livraisons, dont la Pilule 666 à 80 $.",
			"Welcome to the Comptoir des Variétés! Find my 19 ingredients in the Deliveries app, including the 666 Pill for $80.",
			"Willkommen am Sortentresen! Meine 19 Zutaten findest du in der Lieferungen-App, darunter die 666-Pille für 80 $.",
			"¡Bienvenido al Mostrador de Variedades! Encuentra mis 19 ingredientes en la aplicación de Entregas, incluida la Píldora 666 por 80 $."
		},
		new string[4]
		{
			"Ingrédients pour tes mélanges",
			"Ingredients for your mixes",
			"Zutaten für deine Mischungen",
			"Ingredientes para tus mezclas"
		}
	};

	[DllImport("kernel32.dll")]
	private static extern ushort GetUserDefaultUILanguage();

	internal static Lang Current
	{
		get
		{
			string text = (language?.Value ?? "Auto").Trim();
			Lang? lang = FromCode(text);
			if (lang.HasValue)
			{
				return lang.Value;
			}
			return detected ??= Detect();
		}
	}

	/// <summary>Conservé pour compatibilité avec l'ancien code.</summary>
	internal static bool French => Current == Lang.French;

	internal static void Initialize()
	{
		language = MelonLoader.MelonPreferences.CreateCategory("VarietesEtendues").CreateEntry<string>("Language", "Auto", "Language / Langue / Sprache / Idioma : Auto, fr, en, de, es", (string)null, false, false, (MelonLoader.Preferences.ValueValidator)null, (string)null);
		MelonLoader.MelonLogger.Msg("Langue du mod : " + Current);
	}

	private static Lang? FromCode(string? code)
	{
		if (string.IsNullOrWhiteSpace(code))
		{
			return null;
		}
		string text = code.Trim().ToLowerInvariant();
		if (text.StartsWith("fr"))
		{
			return Lang.French;
		}
		if (text.StartsWith("en"))
		{
			return Lang.English;
		}
		if (text.StartsWith("de") || text.StartsWith("ger"))
		{
			return Lang.German;
		}
		if (text.StartsWith("es") || text.StartsWith("spa"))
		{
			return Lang.Spanish;
		}
		return null;
	}

	private static Lang Detect()
	{
		// 1. Langue d'affichage Windows (indépendante du mode "invariant globalization").
		try
		{
			if (RuntimeInformation.IsOSPlatform(OSPlatform.Windows))
			{
				switch (GetUserDefaultUILanguage() & 0x3FF)
				{
				case 0x0C:
					return Lang.French;
				case 0x07:
					return Lang.German;
				case 0x0A:
					return Lang.Spanish;
				case 0x09:
					return Lang.English;
				}
			}
		}
		catch
		{
		}
		// 2. Culture .NET.
		try
		{
			Lang? lang = FromCode(CultureInfo.CurrentUICulture.TwoLetterISOLanguageName);
			if (lang.HasValue && CultureInfo.CurrentUICulture.Name.Length > 0)
			{
				return lang.Value;
			}
		}
		catch
		{
		}
		// 3. Langue système vue par Unity.
		try
		{
			switch (Application.systemLanguage)
			{
			case SystemLanguage.French:
				return Lang.French;
			case SystemLanguage.German:
				return Lang.German;
			case SystemLanguage.Spanish:
				return Lang.Spanish;
			}
		}
		catch
		{
		}
		return Lang.English;
	}

	internal static string Text(string fr, string en, string de, string es)
	{
		return Current switch
		{
			Lang.French => fr,
			Lang.German => de,
			Lang.Spanish => es,
			_ => en,
		};
	}

	internal static string Translate(string value)
	{
		int current = (int)Current;
		string[][] phrases = Phrases;
		foreach (string[] array in phrases)
		{
			for (int j = 0; j < array.Length; j++)
			{
				if (j != current)
				{
					value = value.Replace(array[j], array[current], System.StringComparison.Ordinal);
				}
			}
		}
		return value;
	}

	internal static void Refresh(Il2CppScheduleOne.Messaging.MSGConversation convo)
	{
		string? text = convo?._sender?._npcId;
		if (text != "vx_lucien")
		{
			return;
		}
		if ((UnityEngine.Object)(object)convo!.container != (UnityEngine.Object)null)
		{
			foreach (UnityEngine.UI.Text componentsInChild in ((UnityEngine.Component)convo.container).GetComponentsInChildren<UnityEngine.UI.Text>(true))
			{
				componentsInChild.text = Translate(componentsInChild.text);
			}
		}
		if ((UnityEngine.Object)(object)convo.entryPreviewText != (UnityEngine.Object)null)
		{
			convo.entryPreviewText.text = Translate(convo.entryPreviewText.text);
		}
	}
}
