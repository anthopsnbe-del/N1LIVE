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

	private static Il2CppScheduleOne.FX.PostProcessingManager? owner;

	private static Il2CppScheduleOne.FX.PsychedelicFullScreenFeature.MaterialProperties? prior;

	private static Il2CppScheduleOne.FX.PsychedelicFullScreenFeature.MaterialProperties? ours;

	private static bool priorActive;

	private static bool psychedelic;

	private static System.IntPtr appliedProperties;

	private static float exposureBase;

	private static float exposureLast;

	private static bool exposureOwned;

	internal static void Apply(string[] ids)
	{
		Il2CppScheduleOne.FX.PostProcessingManager instance = Il2CppScheduleOne.DevUtilities.Singleton<Il2CppScheduleOne.FX.PostProcessingManager>.Instance;
		if ((UnityEngine.Object)(object)owner != (UnityEngine.Object)(object)instance)
		{
			Clear();
			owner = instance;
		}
		if ((UnityEngine.Object)(object)owner == (UnityEngine.Object)null)
		{
			return;
		}
		bool num = ((System.ReadOnlySpan<string>)ids).Contains("vx_mixer_absinthe");
		bool flag = ((System.ReadOnlySpan<string>)ids).Contains("vx_mixer_cyclo");
		bool flag2 = ((System.ReadOnlySpan<string>)ids).Contains("vx_mixer_focalix");
		bool flag3 = ((System.ReadOnlySpan<string>)ids).Contains("vx_mixer_syrup");
		if (num && (UnityEngine.Object)(object)owner._psychedelicFullScreenFeature != (UnityEngine.Object)null)
		{
			if (!psychedelic)
			{
				Il2CppScheduleOne.FX.PsychedelicFullScreenFeature.MaterialProperties activePsychedelicEffectProperties = owner.GetActivePsychedelicEffectProperties();
				prior = ((activePsychedelicEffectProperties != null) ? activePsychedelicEffectProperties.Clone() : null);
				priorActive = ((UnityEngine.Rendering.Universal.ScriptableRendererFeature)owner._psychedelicFullScreenFeature).isActive;
				Il2CppScheduleOne.FX.PsychedelicFullScreenFeature.MaterialProperties? obj = prior;
				ours = (Il2CppScheduleOne.FX.PsychedelicFullScreenFeature.MaterialProperties?)(((object)((obj != null) ? obj.Clone() : null)) ?? ((object)new Il2CppScheduleOne.FX.PsychedelicFullScreenFeature.MaterialProperties()));
				psychedelic = true;
			}
			if (ours != null)
			{
				ours.NoiseScale = 1.6f;
				ours.Blend = 0.23f + 0.025f * UnityEngine.Mathf.Sin(UnityEngine.Time.time * 0.38f);
				ours.PanSpeed = new Vector2(0.018f, 0.012f);
				ours.DoesBounce = false;
				ours.Amplitude = 0.012f + 0.002f * UnityEngine.Mathf.Sin(UnityEngine.Time.time * 0.55f);
				owner.SetPsychedelicEffectProperties(ours);
				owner.SetPsychedelicEffectActive(true);
				Il2CppScheduleOne.FX.PsychedelicFullScreenFeature.MaterialProperties activePsychedelicEffectProperties2 = owner.GetActivePsychedelicEffectProperties();
				appliedProperties = ((activePsychedelicEffectProperties2 != null) ? ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)activePsychedelicEffectProperties2).Pointer : System.IntPtr.Zero);
			}
			Il2CppScheduleOne.Tools.FloatSmoother saturationController = owner.SaturationController;
			if (saturationController != null)
			{
				saturationController.AddOverride(18f, 65, "VX_CharacterVision");
			}
			Il2CppScheduleOne.Tools.FloatSmoother chromaticAberrationController = owner.ChromaticAberrationController;
			if (chromaticAberrationController != null)
			{
				chromaticAberrationController.AddOverride(0.12f, 65, "VX_CharacterVision");
			}
			Il2CppScheduleOne.Tools.HDRColorSmoother colorFilterController = owner.ColorFilterController;
			if (colorFilterController != null)
			{
				colorFilterController.AddOverride(new Color(1f + 0.035f * UnityEngine.Mathf.Sin(UnityEngine.Time.time * 0.4f), 1f + 0.035f * UnityEngine.Mathf.Sin(UnityEngine.Time.time * 0.4f + 2f), 1f + 0.035f * UnityEngine.Mathf.Sin(UnityEngine.Time.time * 0.4f + 4f)), 65, "VX_CharacterVision");
			}
		}
		else
		{
			RestoreTrip();
			Il2CppScheduleOne.Tools.FloatSmoother saturationController2 = owner.SaturationController;
			if (saturationController2 != null)
			{
				saturationController2.RemoveOverride("VX_CharacterVision");
			}
			Il2CppScheduleOne.Tools.HDRColorSmoother colorFilterController2 = owner.ColorFilterController;
			if (colorFilterController2 != null)
			{
				colorFilterController2.RemoveOverride("VX_CharacterVision");
			}
			if (flag2)
			{
				Il2CppScheduleOne.Tools.FloatSmoother chromaticAberrationController2 = owner.ChromaticAberrationController;
				if (chromaticAberrationController2 != null)
				{
					chromaticAberrationController2.AddOverride(0f, 65, "VX_CharacterVision");
				}
			}
			else if (flag3)
			{
				Il2CppScheduleOne.Tools.FloatSmoother chromaticAberrationController3 = owner.ChromaticAberrationController;
				if (chromaticAberrationController3 != null)
				{
					chromaticAberrationController3.AddOverride(0.055f, 65, "VX_CharacterVision");
				}
			}
			else
			{
				Il2CppScheduleOne.Tools.FloatSmoother chromaticAberrationController4 = owner.ChromaticAberrationController;
				if (chromaticAberrationController4 != null)
				{
					chromaticAberrationController4.RemoveOverride("VX_CharacterVision");
				}
			}
		}
		UnityEngine.Rendering.Universal.ColorAdjustments colorAdjustments = owner.ColorAdjustments;
		object obj2 = ((colorAdjustments != null) ? colorAdjustments.postExposure : null);
		if (obj2 == null)
		{
			UnityEngine.Rendering.Universal.ColorAdjustments colorAdjustments2 = owner.colorAdjustments;
			obj2 = ((colorAdjustments2 != null) ? colorAdjustments2.postExposure : null);
		}
		UnityEngine.Rendering.FloatParameter val = (UnityEngine.Rendering.FloatParameter)obj2;
		if (val == null)
		{
			return;
		}
		if (flag)
		{
			if (!exposureOwned)
			{
				exposureBase = ((UnityEngine.Rendering.VolumeParameter<float>)(object)val).value;
				exposureLast = exposureBase;
				exposureOwned = true;
			}
			else if (!UnityEngine.Mathf.Approximately(((UnityEngine.Rendering.VolumeParameter<float>)(object)val).value, exposureLast))
			{
				exposureBase = ((UnityEngine.Rendering.VolumeParameter<float>)(object)val).value;
			}
			((UnityEngine.Rendering.VolumeParameter<float>)(object)val).value = (exposureLast = UnityEngine.Mathf.Min(exposureBase + 0.85f, 1.8f));
			Il2CppScheduleOne.Tools.HDRColorSmoother colorFilterController3 = owner.ColorFilterController;
			if (colorFilterController3 != null)
			{
				colorFilterController3.AddOverride(new Color(0.75f, 0.95f, 1f), 64, "VX_CharacterVisionNight");
			}
		}
		else
		{
			RestoreExposure();
			Il2CppScheduleOne.Tools.HDRColorSmoother colorFilterController4 = owner.ColorFilterController;
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
		if ((UnityEngine.Object)(object)owner != (UnityEngine.Object)null && ours != null)
		{
			Il2CppScheduleOne.FX.PsychedelicFullScreenFeature.MaterialProperties activePsychedelicEffectProperties = owner.GetActivePsychedelicEffectProperties();
			if (activePsychedelicEffectProperties != null && ((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)activePsychedelicEffectProperties).Pointer == appliedProperties)
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
		appliedProperties = System.IntPtr.Zero;
	}

	private static void RestoreExposure()
	{
		if (!exposureOwned)
		{
			return;
		}
		Il2CppScheduleOne.FX.PostProcessingManager? obj = owner;
		object obj2;
		if (obj == null)
		{
			obj2 = null;
		}
		else
		{
			UnityEngine.Rendering.Universal.ColorAdjustments colorAdjustments = obj.ColorAdjustments;
			obj2 = ((colorAdjustments != null) ? colorAdjustments.postExposure : null);
		}
		if (obj2 == null)
		{
			Il2CppScheduleOne.FX.PostProcessingManager? obj3 = owner;
			if (obj3 == null)
			{
				obj2 = null;
			}
			else
			{
				UnityEngine.Rendering.Universal.ColorAdjustments colorAdjustments2 = obj3.colorAdjustments;
				obj2 = ((colorAdjustments2 != null) ? colorAdjustments2.postExposure : null);
			}
		}
		UnityEngine.Rendering.FloatParameter val = (UnityEngine.Rendering.FloatParameter)obj2;
		if (val != null && UnityEngine.Mathf.Approximately(((UnityEngine.Rendering.VolumeParameter<float>)(object)val).value, exposureLast))
		{
			((UnityEngine.Rendering.VolumeParameter<float>)(object)val).value = exposureBase;
		}
		exposureOwned = false;
	}

	internal static void Clear()
	{
		RestoreTrip();
		RestoreExposure();
		if ((UnityEngine.Object)(object)owner != (UnityEngine.Object)null)
		{
			Il2CppScheduleOne.Tools.FloatSmoother saturationController = owner.SaturationController;
			if (saturationController != null)
			{
				saturationController.RemoveOverride("VX_CharacterVision");
			}
			Il2CppScheduleOne.Tools.FloatSmoother chromaticAberrationController = owner.ChromaticAberrationController;
			if (chromaticAberrationController != null)
			{
				chromaticAberrationController.RemoveOverride("VX_CharacterVision");
			}
			Il2CppScheduleOne.Tools.HDRColorSmoother colorFilterController = owner.ColorFilterController;
			if (colorFilterController != null)
			{
				colorFilterController.RemoveOverride("VX_CharacterVision");
			}
			Il2CppScheduleOne.Tools.HDRColorSmoother colorFilterController2 = owner.ColorFilterController;
			if (colorFilterController2 != null)
			{
				colorFilterController2.RemoveOverride("VX_CharacterVisionNight");
			}
		}
		owner = null;
	}
}
