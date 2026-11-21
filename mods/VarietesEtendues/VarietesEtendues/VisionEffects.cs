using System;
using Il2CppInterop.Runtime.InteropTypes;
using Il2CppScheduleOne.DevUtilities;
using Il2CppScheduleOne.FX;
using Il2CppScheduleOne.Tools;
using UnityEngine;
using UnityEngine.Rendering;
using UnityEngine.Rendering.Universal;

namespace VarietesEtendues;

internal static class VisionEffects
{
	private const string Label = "VX_CharacterVision";

	private static PostProcessingManager? owner;

	private static MaterialProperties? prior;

	private static MaterialProperties? ours;

	private static bool priorActive;

	private static bool psychedelic;

	private static IntPtr appliedProperties;

	private static float exposureBase;

	private static float exposureLast;

	private static bool exposureOwned;

	internal static void Apply(string[] ids)
	{
		//IL_0132: Unknown result type (might be due to invalid IL or missing references)
		//IL_00d4: Unknown result type (might be due to invalid IL or missing references)
		//IL_025b: Unknown result type (might be due to invalid IL or missing references)
		//IL_03d3: Unknown result type (might be due to invalid IL or missing references)
		PostProcessingManager instance = Singleton<PostProcessingManager>.Instance;
		if ((Object)(object)owner != (Object)(object)instance)
		{
			Clear();
			owner = instance;
		}
		if ((Object)(object)owner == (Object)null)
		{
			return;
		}
		bool num = ((ReadOnlySpan<string>)ids).Contains("vx_mixer_absinthe");
		bool flag = ((ReadOnlySpan<string>)ids).Contains("vx_mixer_cyclo");
		bool flag2 = ((ReadOnlySpan<string>)ids).Contains("vx_mixer_focalix");
		bool flag3 = ((ReadOnlySpan<string>)ids).Contains("vx_mixer_syrup");
		if (num && (Object)(object)owner._psychedelicFullScreenFeature != (Object)null)
		{
			if (!psychedelic)
			{
				MaterialProperties activePsychedelicEffectProperties = owner.GetActivePsychedelicEffectProperties();
				prior = ((activePsychedelicEffectProperties != null) ? activePsychedelicEffectProperties.Clone() : null);
				priorActive = ((ScriptableRendererFeature)owner._psychedelicFullScreenFeature).isActive;
				MaterialProperties? obj = prior;
				ours = (MaterialProperties?)(((object)((obj != null) ? obj.Clone() : null)) ?? ((object)new MaterialProperties()));
				psychedelic = true;
			}
			if (ours != null)
			{
				ours.NoiseScale = 1.6f;
				ours.Blend = 0.23f + 0.025f * Mathf.Sin(Time.time * 0.38f);
				ours.PanSpeed = new Vector2(0.018f, 0.012f);
				ours.DoesBounce = false;
				ours.Amplitude = 0.012f + 0.002f * Mathf.Sin(Time.time * 0.55f);
				owner.SetPsychedelicEffectProperties(ours);
				owner.SetPsychedelicEffectActive(true);
				MaterialProperties activePsychedelicEffectProperties2 = owner.GetActivePsychedelicEffectProperties();
				appliedProperties = ((activePsychedelicEffectProperties2 != null) ? ((Il2CppObjectBase)activePsychedelicEffectProperties2).Pointer : IntPtr.Zero);
			}
			FloatSmoother saturationController = owner.SaturationController;
			if (saturationController != null)
			{
				saturationController.AddOverride(18f, 65, "VX_CharacterVision");
			}
			FloatSmoother chromaticAberrationController = owner.ChromaticAberrationController;
			if (chromaticAberrationController != null)
			{
				chromaticAberrationController.AddOverride(0.12f, 65, "VX_CharacterVision");
			}
			HDRColorSmoother colorFilterController = owner.ColorFilterController;
			if (colorFilterController != null)
			{
				colorFilterController.AddOverride(new Color(1f + 0.035f * Mathf.Sin(Time.time * 0.4f), 1f + 0.035f * Mathf.Sin(Time.time * 0.4f + 2f), 1f + 0.035f * Mathf.Sin(Time.time * 0.4f + 4f)), 65, "VX_CharacterVision");
			}
		}
		else
		{
			RestoreTrip();
			FloatSmoother saturationController2 = owner.SaturationController;
			if (saturationController2 != null)
			{
				saturationController2.RemoveOverride("VX_CharacterVision");
			}
			HDRColorSmoother colorFilterController2 = owner.ColorFilterController;
			if (colorFilterController2 != null)
			{
				colorFilterController2.RemoveOverride("VX_CharacterVision");
			}
			if (flag2)
			{
				FloatSmoother chromaticAberrationController2 = owner.ChromaticAberrationController;
				if (chromaticAberrationController2 != null)
				{
					chromaticAberrationController2.AddOverride(0f, 65, "VX_CharacterVision");
				}
			}
			else if (flag3)
			{
				FloatSmoother chromaticAberrationController3 = owner.ChromaticAberrationController;
				if (chromaticAberrationController3 != null)
				{
					chromaticAberrationController3.AddOverride(0.055f, 65, "VX_CharacterVision");
				}
			}
			else
			{
				FloatSmoother chromaticAberrationController4 = owner.ChromaticAberrationController;
				if (chromaticAberrationController4 != null)
				{
					chromaticAberrationController4.RemoveOverride("VX_CharacterVision");
				}
			}
		}
		ColorAdjustments colorAdjustments = owner.ColorAdjustments;
		object obj2 = ((colorAdjustments != null) ? colorAdjustments.postExposure : null);
		if (obj2 == null)
		{
			ColorAdjustments colorAdjustments2 = owner.colorAdjustments;
			obj2 = ((colorAdjustments2 != null) ? colorAdjustments2.postExposure : null);
		}
		FloatParameter val = (FloatParameter)obj2;
		if (val == null)
		{
			return;
		}
		if (flag)
		{
			if (!exposureOwned)
			{
				exposureBase = ((VolumeParameter<float>)(object)val).value;
				exposureLast = exposureBase;
				exposureOwned = true;
			}
			else if (!Mathf.Approximately(((VolumeParameter<float>)(object)val).value, exposureLast))
			{
				exposureBase = ((VolumeParameter<float>)(object)val).value;
			}
			((VolumeParameter<float>)(object)val).value = (exposureLast = Mathf.Min(exposureBase + 0.85f, 1.8f));
			HDRColorSmoother colorFilterController3 = owner.ColorFilterController;
			if (colorFilterController3 != null)
			{
				colorFilterController3.AddOverride(new Color(0.75f, 0.95f, 1f), 64, "VX_CharacterVisionNight");
			}
		}
		else
		{
			RestoreExposure();
			HDRColorSmoother colorFilterController4 = owner.ColorFilterController;
			if (colorFilterController4 != null)
			{
				colorFilterController4.RemoveOverride("VX_CharacterVisionNight");
			}
		}
	}

	private static void RestoreTrip()
	{
		if (!psychedelic)
		{
			return;
		}
		if ((Object)(object)owner != (Object)null && ours != null)
		{
			MaterialProperties activePsychedelicEffectProperties = owner.GetActivePsychedelicEffectProperties();
			if (activePsychedelicEffectProperties != null && ((Il2CppObjectBase)activePsychedelicEffectProperties).Pointer == appliedProperties)
			{
				if (prior != null)
				{
					owner.SetPsychedelicEffectProperties(prior);
				}
				owner.SetPsychedelicEffectActive(priorActive);
			}
		}
		prior = (ours = null);
		psychedelic = false;
		appliedProperties = IntPtr.Zero;
	}

	private static void RestoreExposure()
	{
		if (!exposureOwned)
		{
			return;
		}
		PostProcessingManager? obj = owner;
		object obj2;
		if (obj == null)
		{
			obj2 = null;
		}
		else
		{
			ColorAdjustments colorAdjustments = obj.ColorAdjustments;
			obj2 = ((colorAdjustments != null) ? colorAdjustments.postExposure : null);
		}
		if (obj2 == null)
		{
			PostProcessingManager? obj3 = owner;
			if (obj3 == null)
			{
				obj2 = null;
			}
			else
			{
				ColorAdjustments colorAdjustments2 = obj3.colorAdjustments;
				obj2 = ((colorAdjustments2 != null) ? colorAdjustments2.postExposure : null);
			}
		}
		FloatParameter val = (FloatParameter)obj2;
		if (val != null && Mathf.Approximately(((VolumeParameter<float>)(object)val).value, exposureLast))
		{
			((VolumeParameter<float>)(object)val).value = exposureBase;
		}
		exposureOwned = false;
	}

	internal static void Clear()
	{
		RestoreTrip();
		RestoreExposure();
		if ((Object)(object)owner != (Object)null)
		{
			FloatSmoother saturationController = owner.SaturationController;
			if (saturationController != null)
			{
				saturationController.RemoveOverride("VX_CharacterVision");
			}
			FloatSmoother chromaticAberrationController = owner.ChromaticAberrationController;
			if (chromaticAberrationController != null)
			{
				chromaticAberrationController.RemoveOverride("VX_CharacterVision");
			}
			HDRColorSmoother colorFilterController = owner.ColorFilterController;
			if (colorFilterController != null)
			{
				colorFilterController.RemoveOverride("VX_CharacterVision");
			}
			HDRColorSmoother colorFilterController2 = owner.ColorFilterController;
			if (colorFilterController2 != null)
			{
				colorFilterController2.RemoveOverride("VX_CharacterVisionNight");
			}
		}
		owner = null;
	}
}
