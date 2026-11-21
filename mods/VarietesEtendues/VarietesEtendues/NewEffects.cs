using System;
using System.Linq;
using System.Runtime.CompilerServices;
using System.Text;
using Il2CppScheduleOne.Effects;
using S1API.Entities;
using S1API.Properties;
using S1API.Properties.Interfaces;
using UnityEngine;

namespace VarietesEtendues;

internal static class NewEffects
{
	internal sealed record Spec(string Key, string French, string English, string FrDescription, string EnDescription, string German, string Spanish, string DeDescription, string EsDescription, Color Color, int Kind)
	{
		internal string Id => EffectRules.Id(Key);

		internal string Name => Localization.Text(French, English, German, Spanish);

		internal string Description => Localization.Text(FrDescription, EnDescription, DeDescription, EsDescription);

	}

	internal static readonly Spec[] All = new Spec[18]
	{
		new Spec("mango", "Chaleur tropicale", "Tropical Warmth", "Teint doré, visage détendu et récupération d'endurance +30 %.", "Warm golden skin, a relaxed face and 30% faster stamina recovery.", "Tropische Wärme", "Calor tropical", "Goldene Haut, entspanntes Gesicht und 30 % schnellere Ausdauer-Regeneration.", "Piel dorada, rostro relajado y recuperación de resistencia un 30 % más rápida.", new Color(1f, 0.7f, 0.28f), 0),
		new Spec("lemon", "Réflexes vifs", "Quick Reflexes", "Regard jaune, démarche vive et vitesse +20 %.", "Yellow eyes, lively movement and 20% extra movement speed.", "Schnelle Reflexe", "Reflejos rápidos", "Gelbe Augen, lebhafte Bewegungen und 20 % mehr Lauftempo.", "Ojos amarillos, movimientos ágiles y un 20 % más de velocidad.", new Color(1f, 0.85f, 0.15f), 1),
		new Spec("melon", "Hydratation", "Hydration", "Teint frais et dépense d'endurance réduite de 30 %.", "A fresh complexion and 30% less stamina drain.", "Hydration", "Hidratación", "Frischer Teint und 30 % weniger Ausdauerverbrauch.", "Tez fresca y un 30 % menos de gasto de resistencia.", new Color(0.7f, 0.9f, 0.85f), 2),
		new Spec("dragon", "Peau draconique", "Dragon Skin", "Peau pourpre écailleuse, résistance et saut renforcés.", "Purple scaled skin, improved resistance and stronger jumps.", "Drachenhaut", "Piel de dragón", "Violette Schuppenhaut, mehr Widerstand und stärkere Sprünge.", "Piel escamosa púrpura, más resistencia y saltos más fuertes.", new Color(0.48f, 0.28f, 0.62f), 3),
		new Spec("prune", "Souplesse", "Flexibility", "Mouvements souples, saut +10 % et réception amortie.", "Supple movement, 10% stronger jumps and softer landings.", "Geschmeidigkeit", "Flexibilidad", "Geschmeidige Bewegungen, 10 % stärkere Sprünge und weichere Landungen.", "Movimientos ágiles, saltos un 10 % más fuertes y aterrizajes más suaves.", new Color(0.45f, 0.3f, 0.5f), 4),
		new Spec("durian", "Carapace", "Thick Hide", "Peau rugueuse, épines courtes au dos et dégâts réduits de 30 %.", "Rough skin, short back spines and 30% less damage.", "Panzerhaut", "Caparazón", "Raue Haut, kurze Rückenstacheln und 30 % weniger Schaden.", "Piel rugosa, espinas cortas en la espalda y un 30 % menos de daño.", new Color(0.45f, 0.5f, 0.21f), 5),
		new Spec("syrup", "Léthargie", "Lethargy", "Paupières lourdes, démarche lente et endurance récupérée plus vite.", "Heavy eyelids, slow movement and faster stamina recovery.", "Lethargie", "Letargo", "Schwere Augenlider, langsame Bewegung und schnellere Ausdauer-Regeneration.", "Párpados pesados, movimiento lento y recuperación de resistencia más rápida.", new Color(0.75f, 0.65f, 0.59f), 6),
		new Spec("focalix", "Hyperfocus", "Hyperfocus", "Regard concentré, vue stable et effort prolongé.", "A focused gaze, steadier vision and longer exertion.", "Hyperfokus", "Hiperconcentración", "Konzentrierter Blick, ruhigere Sicht und längere Ausdauer.", "Mirada concentrada, visión estable y esfuerzo prolongado.", new Color(0.62f, 0.8f, 1f), 7),
		new Spec("appetix", "Hypertrophie", "Hypertrophy", "Carrure plus large et coups de poing renforcés de 30 %.", "A broader build and 30% stronger punches.", "Hypertrophie", "Hipertrofia", "Breiterer Körperbau und 30 % stärkere Schläge.", "Complexión más ancha y puñetazos un 30 % más fuertes.", new Color(0.75f, 0.6f, 0.45f), 8),
		new Spec("fizz", "Flottabilité", "Buoyancy", "Teint bleuté, gravité réduite et sauts plus légers.", "A blue complexion, reduced gravity and lighter jumps.", "Auftrieb", "Flotabilidad", "Bläulicher Teint, geringere Schwerkraft und leichtere Sprünge.", "Tez azulada, gravedad reducida y saltos más ligeros.", new Color(0.5f, 0.74f, 0.83f), 9),
		new Spec("cyclo", "Vision nocturne", "Night Vision", "Yeux cyan pâle et meilleure visibilité dans le noir.", "Pale cyan eyes and improved vision in the dark.", "Nachtsicht", "Visión nocturna", "Blasse cyanfarbene Augen und bessere Sicht im Dunkeln.", "Ojos cian pálido y mejor visión en la oscuridad.", new Color(0.2f, 0.85f, 0.9f), 10),
		new Spec("paranox", "Ombre vivante", "Living Shadow", "Corps, visage et vêtements noirs ; yeux blancs perçants.", "Black body, face and clothing with piercing white eyes.", "Lebender Schatten", "Sombra viviente", "Schwarzer Körper, Gesicht und Kleidung mit stechend weißen Augen.", "Cuerpo, rostro y ropa negros con ojos blancos penetrantes.", new Color(0.005f, 0.005f, 0.008f), 11),
		new Spec("poprocks", "Saut explosif", "Spring Loaded", "Impulsion de saut +50 %, posture dynamique et réception animée.", "50% stronger jumps, an energetic stance and animated landings.", "Sprungfeder", "Resorte", "50 % stärkere Sprünge, energische Haltung und animierte Landungen.", "Saltos un 50 % más fuertes, postura enérgica y aterrizajes animados.", new Color(0.9f, 0.5f, 0.6f), 12),
		new Spec("lithium", "Dynamo", "Dynamo", "Marques bleues discrètes et accélération renforcée.", "Subtle blue markings and stronger acceleration.", "Dynamo", "Dínamo", "Dezente blaue Markierungen und stärkere Beschleunigung.", "Marcas azules sutiles y mayor aceleración.", new Color(0.28f, 0.52f, 0.8f), 13),
		new Spec("patee", "Instinct bestial", "Animal Instinct", "Regard animal, posture basse et déplacement accroupi plus rapide.", "An animal gaze, low stance and faster crouched movement.", "Tierinstinkt", "Instinto animal", "Tierischer Blick, geduckte Haltung und schnelleres Schleichen.", "Mirada animal, postura baja y movimiento agachado más rápido.", new Color(0.65f, 0.47f, 0.28f), 14),
		new Spec("glow", "Bioluminescence", "Bioluminescence", "Marquages lumineux sur la peau et lumière douce.", "Luminous skin markings and a soft glow.", "Biolumineszenz", "Bioluminiscencia", "Leuchtende Hautmarkierungen und sanftes Glühen.", "Marcas luminosas en la piel y un brillo suave.", new Color(0.24f, 0.65f, 0.28f), 15),
		new Spec("nitro", "Cryogénie", "Cryogenic", "Peau givrée, souffle froid et résistance renforcée.", "Frosted skin, cold breath and improved resistance.", "Kryogen", "Criogenia", "Bereifte Haut, kalter Atem und mehr Widerstand.", "Piel escarchada, aliento frío y más resistencia.", new Color(0.62f, 0.8f, 0.9f), 16),
		new Spec("absinthe", "Trip psychédélique", "Psychedelic Trip", "Vue ondulante, couleurs mouvantes et léger balancement.", "Rippling vision, shifting colors and gentle swaying.", "Psychedelischer Trip", "Viaje psicodélico", "Wellige Sicht, wechselnde Farben und leichtes Schwanken.", "Visión ondulante, colores cambiantes y ligero balanceo.", new Color(0.35f, 0.7f, 0.4f), 17)
	};

	internal static Spec For(string key)
	{
		string key2 = key;
		return All.First((Spec s) => s.Key == key2);
	}

	internal static bool IsCustom(string id)
	{
		string id2 = id;
		if (!(id2 == "vx_diable"))
		{
			return All.Any((Spec s) => s.Id == id2);
		}
		return true;
	}

	internal static PropertyBase Build(Entry entry)
	{
		//IL_002d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0069: Unknown result type (might be due to invalid IL or missing references)
		//IL_0074: Unknown result type (might be due to invalid IL or missing references)
		//IL_00a2: Unknown result type (might be due to invalid IL or missing references)
		Spec s = For(entry.Key);
		Effect val = Mixing.Native(Mod.Resolve(entry.Effect).ID);
		return (PropertyBase)(object)new CustomEffectBuilder().WithBasicInfo(s.Id, s.Name, s.Description).WithTier(val.Tier).WithColors(s.Color, s.Color)
			.WithValue(0, 1f, 0.18f + (float)s.Kind * 0.012f)
			.WithMixGeometry(val.MixDirection, val.MixMagnitude)
			.WithBehavior((Action<Player>)delegate(Player p)
			{
				IngredientEffects.Apply(p.Transform, s);
			})
			.WithNpcBehavior((Action<NPC>)delegate(NPC n)
			{
				IngredientEffects.Apply(n.Transform, s);
			})
			.WithClearBehavior((Action<Player>)delegate(Player p)
			{
				IngredientEffects.Clear(p.Transform, s.Id);
			})
			.WithNpcClearBehavior((Action<NPC>)delegate(NPC n)
			{
				IngredientEffects.Clear(n.Transform, s.Id);
			})
			.Build();
	}
}
