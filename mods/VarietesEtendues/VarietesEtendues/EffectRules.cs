using System;
using System.Collections.Generic;

namespace VarietesEtendues;

internal static class EffectRules
{
	internal const string DevilId = "vx_diable";

	internal static string Id(string key)
	{
		return "vx_mixer_" + key;
	}

	internal static EffectTuning One(string id)
	{
		EffectTuning neutral = EffectTuning.Neutral;
		return id switch
		{
			"vx_mixer_mango" => neutral with
			{
				Regen = 1.3f
			}, 
			"vx_mixer_lemon" => neutral with
			{
				Speed = 1.2f,
				Acceleration = 1.2f
			}, 
			"vx_mixer_melon" => neutral with
			{
				Drain = 0.7f
			}, 
			"vx_mixer_dragon" => neutral with
			{
				DamageTaken = 0.8f,
				Jump = 1.15f
			}, 
			"vx_mixer_prune" => neutral with
			{
				Fall = 0.45f,
				Jump = 1.1f
			}, 
			"vx_mixer_durian" => neutral with
			{
				DamageTaken = 0.7f,
				Speed = 0.9f,
				Animation = 0.94f
			}, 
			"vx_mixer_syrup" => neutral with
			{
				Speed = 0.85f,
				Regen = 1.5f,
				Animation = 0.86f
			}, 
			"vx_mixer_focalix" => neutral with
			{
				Drain = 0.65f
			}, 
			"vx_mixer_appetix" => neutral with
			{
				Strength = 1.3f,
				Speed = 0.95f
			}, 
			"vx_mixer_fizz" => neutral with
			{
				Gravity = 0.7f,
				Jump = 1.15f,
				Fall = 0.55f
			}, 
			"vx_mixer_paranox" => neutral with
			{
				Crouch = 1.2f
			}, 
			"vx_mixer_poprocks" => neutral with
			{
				Jump = 1.5f,
				Acceleration = 1.2f
			}, 
			"vx_mixer_lithium" => neutral with
			{
				Acceleration = 1.35f,
				Regen = 1.2f
			}, 
			"vx_mixer_patee" => neutral with
			{
				Crouch = 1.35f,
				Strength = 1.15f
			}, 
			"vx_mixer_nitro" => neutral with
			{
				DamageTaken = 0.8f,
				Speed = 0.9f
			}, 
			"vx_diable" => neutral with
			{
				Gravity = 0.45f,
				Jump = 1.35f,
				Drain = 0.6f,
				God = true,
				Fall = 0f
			}, 
			_ => neutral, 
		};
	}

	internal static EffectTuning Combine(System.Collections.Generic.IEnumerable<string> effects, bool sprinting = false, float devilSpeed = 1.3f)
	{
		EffectTuning effectTuning = EffectTuning.Neutral;
		System.Collections.Generic.HashSet<string> hashSet = new System.Collections.Generic.HashSet<string>(System.StringComparer.Ordinal);
		foreach (string effect in effects)
		{
			if (hashSet.Add(effect))
			{
				EffectTuning effectTuning2 = One(effect);
				effectTuning = effectTuning with
				{
					Speed = effectTuning.Speed * effectTuning2.Speed,
					Jump = System.Math.Max(effectTuning.Jump, effectTuning2.Jump),
					Gravity = System.Math.Min(effectTuning.Gravity, effectTuning2.Gravity),
					Drain = System.Math.Min(effectTuning.Drain, effectTuning2.Drain),
					Regen = System.Math.Max(effectTuning.Regen, effectTuning2.Regen),
					Acceleration = System.Math.Max(effectTuning.Acceleration, effectTuning2.Acceleration),
					Crouch = System.Math.Max(effectTuning.Crouch, effectTuning2.Crouch),
					DamageTaken = System.Math.Min(effectTuning.DamageTaken, effectTuning2.DamageTaken),
					Strength = System.Math.Max(effectTuning.Strength, effectTuning2.Strength),
					Fall = System.Math.Min(effectTuning.Fall, effectTuning2.Fall),
					Animation = System.Math.Min(effectTuning.Animation, effectTuning2.Animation),
					God = (effectTuning.God || effectTuning2.God)
				};
			}
		}
		float num = System.Math.Clamp(effectTuning.Speed, 0.65f, 1.6f);
		if (effectTuning.God)
		{
			num = (sprinting ? Math.Clamp(devilSpeed, 1f, 2f) : System.Math.Clamp(num, 0.65f, 1.25f));
		}
		return effectTuning with
		{
			Speed = num,
			Jump = System.Math.Clamp(effectTuning.Jump, 1f, 1.6f),
			Gravity = System.Math.Clamp(effectTuning.Gravity, 0.4f, 1f),
			Drain = System.Math.Clamp(effectTuning.Drain, 0.5f, 1f),
			Regen = System.Math.Clamp(effectTuning.Regen, 1f, 1.5f),
			DamageTaken = System.Math.Clamp(effectTuning.DamageTaken, 0.65f, 1f),
			Strength = System.Math.Clamp(effectTuning.Strength, 1f, 1.35f)
		};
	}
}
