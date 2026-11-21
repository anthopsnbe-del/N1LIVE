using System;
using System.Collections.Generic;
using System.Linq;
using Il2CppInterop.Runtime.InteropTypes;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne.Avatar;
using Il2CppScheduleOne.AvatarFramework;
using Il2CppScheduleOne.AvatarFramework.Animation;
using Il2CppScheduleOne.AvatarFramework.Emotions;
using UnityEngine;

namespace VarietesEtendues;

internal sealed class AvatarSkinLease
{
	private sealed record Slot(UnityEngine.Renderer Renderer, UnityEngine.Material[] Original, UnityEngine.Material[] Owned, bool Skin);

	private sealed class Pose
	{
		internal UnityEngine.Transform Bone;

		internal UnityEngine.Quaternion Base;

		internal UnityEngine.Quaternion Applied;

		internal bool Set;
	}

	private readonly Il2CppScheduleOne.AvatarFramework.Avatar avatar;

	private readonly System.Collections.Generic.Dictionary<int, Slot> slots = new System.Collections.Generic.Dictionary<int, Slot>();

	private float refresh;

	private readonly UnityEngine.Color leftColor;

	private readonly UnityEngine.Color rightColor;

	private readonly float leftEmission;

	private readonly float rightEmission;

	private UnityEngine.Color? ownedEyes;

	private readonly UnityEngine.Animator? animator;

	private float animationBase;

	private float lastAnimation;

	private bool hasWeight;

	private string? emotion;

	private const string Label = "VX_CharacterEffect";

	private readonly System.Collections.Generic.Dictionary<int, Pose> poses = new System.Collections.Generic.Dictionary<int, Pose>();

	internal AvatarSkinLease(Il2CppScheduleOne.AvatarFramework.Avatar avatar)
	{
		this.avatar = avatar;
		Il2CppScheduleOne.AvatarFramework.EyeController eyes = avatar.Eyes;
		UnityEngine.Color? obj;
		if (eyes == null)
		{
			obj = null;
		}
		else
		{
			Il2CppScheduleOne.AvatarFramework.Eye leftEye = eyes.LeftEye;
			obj = ((leftEye != null) ? new UnityEngine.Color?(leftEye._eyeballColor) : null);
		}
		leftColor = (((UnityEngine.Color?)obj) ?? Color.white);
		UnityEngine.Color? obj2;
		if (eyes == null)
		{
			obj2 = null;
		}
		else
		{
			Il2CppScheduleOne.AvatarFramework.Eye rightEye = eyes.RightEye;
			obj2 = ((rightEye != null) ? new UnityEngine.Color?(rightEye._eyeballColor) : null);
		}
		rightColor = (((UnityEngine.Color?)obj2) ?? Color.white);
		float? obj3;
		if (eyes == null)
		{
			obj3 = null;
		}
		else
		{
			Il2CppScheduleOne.AvatarFramework.Eye leftEye2 = eyes.LeftEye;
			obj3 = ((leftEye2 != null) ? new float?(leftEye2._eyeballEmission) : null);
		}
		float? num = obj3;
		leftEmission = num.GetValueOrDefault(0.115f);
		float? obj4;
		if (eyes == null)
		{
			obj4 = null;
		}
		else
		{
			Il2CppScheduleOne.AvatarFramework.Eye rightEye2 = eyes.RightEye;
			obj4 = ((rightEye2 != null) ? new float?(rightEye2._eyeballEmission) : null);
		}
		num = obj4;
		rightEmission = num.GetValueOrDefault(0.115f);
		Il2CppScheduleOne.AvatarFramework.Animation.AvatarAnimation animation = avatar.Animation;
		animator = ((animation != null) ? animation._animator : null);
		if ((UnityEngine.Object)(object)animator != (UnityEngine.Object)null)
		{
			lastAnimation = (animationBase = animator.speed);
		}
		Capture();
	}

	private static bool IsEye(UnityEngine.Renderer renderer)
	{
		string text = ((UnityEngine.Object)renderer).name.ToLowerInvariant();
		if (!text.Contains("eyeball") && !text.Contains("pupil") && !text.Contains("cornea") && !text.Contains("teeth"))
		{
			return text.Contains("tongue");
		}
		return true;
	}

	private void Capture()
	{
		if ((UnityEngine.Object)(object)avatar == (UnityEngine.Object)null)
		{
			return;
		}
		foreach (UnityEngine.Renderer componentsInChild in ((UnityEngine.Component)avatar).GetComponentsInChildren<UnityEngine.Renderer>(true))
		{
			if (((UnityEngine.Object)(object)((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)componentsInChild).TryCast<UnityEngine.SkinnedMeshRenderer>() == (UnityEngine.Object)null && (UnityEngine.Object)(object)((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)componentsInChild).TryCast<UnityEngine.MeshRenderer>() == (UnityEngine.Object)null) || IsEye(componentsInChild) || slots.ContainsKey(((UnityEngine.Object)componentsInChild).GetInstanceID()) || ((UnityEngine.Object)(object)avatar.CurrentEquippable != (UnityEngine.Object)null && ((UnityEngine.Component)componentsInChild).transform.IsChildOf(((UnityEngine.Component)avatar.CurrentEquippable).transform)) || ((UnityEngine.Object)componentsInChild).name.StartsWith("VX_", System.StringComparison.Ordinal))
			{
				continue;
			}
			UnityEngine.Material[] array = ((System.Collections.Generic.IEnumerable<UnityEngine.Material>)componentsInChild.sharedMaterials).ToArray();
			UnityEngine.Material[] array2 = array.Select((UnityEngine.Material m) => (UnityEngine.Material)(((UnityEngine.Object)(object)m != (UnityEngine.Object)null) ? ((object)new Material(m)) : ((object)Models.Material(UnityEngine.Color.white)))).ToArray();
			string text = (((UnityEngine.Object)componentsInChild).name + " " + string.Join(" ", from m in array
				where (UnityEngine.Object)(object)m != (UnityEngine.Object)null
				select ((UnityEngine.Object)m).name)).ToLowerInvariant();
			bool skin = (UnityEngine.Object)(object)((Il2CppInterop.Runtime.InteropTypes.Il2CppObjectBase)componentsInChild).TryCast<UnityEngine.SkinnedMeshRenderer>() != (UnityEngine.Object)null && (text.Contains("skin") || text.Contains("body") || text.Contains("face") || text.Contains("head") || text.Contains("hand") || text.Contains("arm"));
			Il2CppScheduleOne.Avatar.AvatarAppearance appearance = avatar.Appearance;
			if ((UnityEngine.Object)(object)((appearance != null) ? appearance._faceMesh : null) == (UnityEngine.Object)(object)componentsInChild)
			{
				skin = true;
			}
			Il2CppScheduleOne.Avatar.AvatarAppearance appearance2 = avatar.Appearance;
			if (((appearance2 != null) ? appearance2._bodyMeshes : null) != null)
			{
				foreach (UnityEngine.SkinnedMeshRenderer item in (Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.SkinnedMeshRenderer>)(object)avatar.Appearance._bodyMeshes)
				{
					if ((UnityEngine.Object)(object)item == (UnityEngine.Object)(object)componentsInChild)
					{
						skin = true;
					}
				}
			}
			componentsInChild.sharedMaterials = (array2);
			slots[((UnityEngine.Object)componentsInChild).GetInstanceID()] = new Slot(componentsInChild, array, array2, skin);
		}
	}

	internal void Apply(System.Collections.Generic.HashSet<string> ids, float time)
	{
		System.Collections.Generic.HashSet<string> ids2 = ids;
		if ((UnityEngine.Object)(object)avatar == (UnityEngine.Object)null)
		{
			return;
		}
		bool flag = ids2.Contains("vx_diable");
		bool flag2 = flag || Has("paranox");
		if (UnityEngine.Time.unscaledTime >= refresh)
		{
			Capture();
			refresh = UnityEngine.Time.unscaledTime + 0.5f;
		}
		UnityEngine.Color white = UnityEngine.Color.white;
		float num = 0f;
		UnityEngine.Color val = UnityEngine.Color.black;
		if (Has("mango"))
		{
			white = new Color(1f, 0.77f, 0.43f);
			num = 0.15f;
		}
		if (Has("melon"))
		{
			white = new Color(0.65f, 0.9f, 0.8f);
			num = 0.1f;
		}
		if (Has("syrup"))
		{
			white = new Color(0.7f, 0.66f, 0.62f);
			num = 0.2f;
		}
		if (Has("patee"))
		{
			white = new Color(0.62f, 0.44f, 0.25f);
			num = 0.2f;
		}
		if (Has("fizz"))
		{
			white = new Color(0.35f, 0.65f, 0.8f);
			num = 0.25f;
		}
		if (Has("lithium"))
		{
			white = new Color(0.45f, 0.63f, 0.83f);
			num = 0.12f;
			val = new Color(0.005f, 0.015f, 0.035f);
		}
		if (Has("durian"))
		{
			white = new Color(0.4f, 0.42f, 0.22f);
			num = 0.65f;
		}
		if (Has("dragon"))
		{
			white = new Color(0.43f, 0.24f, 0.57f);
			num = 0.75f;
		}
		if (Has("nitro"))
		{
			white = new Color(0.68f, 0.85f, 0.95f);
			num = 0.65f;
		}
		if (Has("glow"))
		{
			val = new Color(0.015f, 0.085f, 0.025f) * (1f + 0.12f * UnityEngine.Mathf.Sin(time * 1.2f));
		}
		foreach (Slot value in slots.Values)
		{
			if ((UnityEngine.Object)(object)value.Renderer == (UnityEngine.Object)null)
			{
				continue;
			}
			for (int i = 0; i < value.Owned.Length; i++)
			{
				UnityEngine.Material val2 = value.Owned[i];
				UnityEngine.Material val3 = value.Original[i];
				if ((UnityEngine.Object)(object)val2 == (UnityEngine.Object)null || (UnityEngine.Object)(object)val3 == (UnityEngine.Object)null)
				{
					continue;
				}
				string[] array = new string[4] { "_Color", "_BaseColor", "_SkinColor", "_TintColor" };
				foreach (string text in array)
				{
					if (val2.HasProperty(text))
					{
						UnityEngine.Color color = val3.GetColor(text);
						val2.SetColor(text, (UnityEngine.Color)(flag2 ? new Color(0.003f, 0.003f, 0.005f, color.a) : (value.Skin ? Color.Lerp(color, white, num) : color)));
					}
				}
				if (val2.HasProperty("_EmissionColor"))
				{
					UnityEngine.Color color2 = val3.GetColor("_EmissionColor");
					val2.SetColor("_EmissionColor", flag2 ? Color.black : (value.Skin ? (color2 + val) : color2));
					if ((!flag2 && value.Skin && val.maxColorComponent > 0f) || val3.IsKeywordEnabled("_EMISSION"))
					{
						val2.EnableKeyword("_EMISSION");
					}
					else
					{
						val2.DisableKeyword("_EMISSION");
					}
				}
				if (value.Skin && val2.HasProperty("_DetailAlbedoMap") && (Has("dragon") || Has("glow") || Has("lithium")))
				{
					val2.SetTexture("_DetailAlbedoMap", (UnityEngine.Texture)(object)SkinPatterns.Get((!Has("dragon")) ? (Has("glow") ? 1 : 2) : 0));
					val2.EnableKeyword("_DETAIL_MULX2");
				}
				else if (val2.HasProperty("_DetailAlbedoMap"))
				{
					val2.SetTexture("_DetailAlbedoMap", val3.GetTexture("_DetailAlbedoMap"));
					if (val3.IsKeywordEnabled("_DETAIL_MULX2"))
					{
						val2.EnableKeyword("_DETAIL_MULX2");
					}
					else
					{
						val2.DisableKeyword("_DETAIL_MULX2");
					}
				}
			}
		}
		UnityEngine.Color? val4 = (flag ? new UnityEngine.Color?(new Color(0.7f, 0.002f, 0.008f)) : (Has("paranox") ? new UnityEngine.Color?(UnityEngine.Color.white) : (Has("cyclo") ? new UnityEngine.Color?(new Color(0.45f, 0.95f, 1f)) : (Has("absinthe") ? new UnityEngine.Color?(new Color(0.2f, 0.8f, 0.35f)) : (Has("patee") ? new UnityEngine.Color?(new Color(0.8f, 0.45f, 0.08f)) : (Has("lemon") ? new UnityEngine.Color?(new Color(0.85f, 0.75f, 0.12f)) : null))))));
		if (val4.HasValue)
		{
			Il2CppScheduleOne.AvatarFramework.EyeController eyes = avatar.Eyes;
			if (eyes != null)
			{
				eyes.SetEyeballColor(val4.Value, flag2 ? 0.45f : 0.18f, false);
			}
			ownedEyes = val4;
		}
		else
		{
			RestoreEyes();
		}
		bool flag3 = Has("appetix");
		if (flag3 && !hasWeight)
		{
			Il2CppScheduleOne.AvatarFramework.AvatarEffects effects = avatar.Effects;
			if (effects != null)
			{
				effects.AddAdditionalWeightOverride(0.72f, 70, "VX_CharacterEffect", false);
			}
			hasWeight = true;
		}
		else if (!flag3 && hasWeight)
		{
			Il2CppScheduleOne.AvatarFramework.AvatarEffects effects2 = avatar.Effects;
			if (effects2 != null)
			{
				effects2.RemoveAdditionalWeightOverride("VX_CharacterEffect", false);
			}
			hasWeight = false;
		}
		string text2 = (Has("syrup") ? "Tired" : (Has("patee") ? "Angry" : (Has("focalix") ? "Focused" : (Has("mango") ? "Happy" : null))));
		if (text2 != emotion)
		{
			if (emotion != null)
			{
				Il2CppScheduleOne.AvatarFramework.Emotions.AvatarEmotionManager emotionManager = avatar.EmotionManager;
				if (emotionManager != null)
				{
					emotionManager.RemoveEmotionOverride("VX_CharacterEffect");
				}
			}
			emotion = null;
			if (text2 != null && (UnityEngine.Object)(object)avatar.EmotionManager != (UnityEngine.Object)null && avatar.EmotionManager.HasEmotion(text2))
			{
				avatar.EmotionManager.AddEmotionOverride(text2, "VX_CharacterEffect", 0f, 60);
				emotion = text2;
			}
		}
		EffectTuning effectTuning = EffectRules.Combine(ids2);
		if ((UnityEngine.Object)(object)animator != (UnityEngine.Object)null)
		{
			if (!UnityEngine.Mathf.Approximately(animator.speed, lastAnimation))
			{
				animationBase = animator.speed;
			}
			animator.speed = (lastAnimation = animationBase * effectTuning.Animation);
		}
		UndoPoses();
		if (Has("syrup"))
		{
			Il2CppScheduleOne.AvatarFramework.AvatarEffects effects3 = avatar.Effects;
			Offset((effects3 != null) ? effects3.NeckBone : null, UnityEngine.Quaternion.Euler(6f + UnityEngine.Mathf.Sin(time * 0.7f) * 2f, 0f, UnityEngine.Mathf.Sin(time * 0.55f) * 1.3f));
		}
		else if (Has("absinthe"))
		{
			Offset(avatar.MiddleSpine, UnityEngine.Quaternion.Euler(0f, 0f, UnityEngine.Mathf.Sin(time * 0.8f) * 1.8f));
		}
		else if (Has("patee"))
		{
			Offset(avatar.MiddleSpine, UnityEngine.Quaternion.Euler(7f, 0f, 0f));
		}
		else if (Has("prune"))
		{
			Offset(avatar.MiddleSpine, UnityEngine.Quaternion.Euler(UnityEngine.Mathf.Sin(time * 1.1f) * 0.8f, 0f, 0f));
		}
		else if (Has("poprocks"))
		{
			Il2CppScheduleOne.AvatarFramework.AvatarEffects effects4 = avatar.Effects;
			Offset((effects4 != null) ? effects4.NeckBone : null, UnityEngine.Quaternion.Euler(UnityEngine.Mathf.Sin(time * 1.8f) * 0.6f, 0f, 0f));
		}
		bool Has(string key)
		{
			return ids2.Contains(EffectRules.Id(key));
		}
	}

	private void Offset(UnityEngine.Transform? bone, UnityEngine.Quaternion delta)
	{
		if (!((UnityEngine.Object)(object)bone == (UnityEngine.Object)null))
		{
			int instanceID = ((UnityEngine.Object)bone).GetInstanceID();
			if (!poses.TryGetValue(instanceID, out Pose value))
			{
				value = new Pose
				{
					Bone = bone
				};
				poses[instanceID] = value;
			}
			value.Base = bone.localRotation;
			value.Applied = value.Base * delta;
			bone.localRotation = value.Applied;
			value.Set = true;
		}
	}

	private void UndoPoses()
	{
		foreach (Pose value in poses.Values)
		{
			if (value.Set && (UnityEngine.Object)(object)value.Bone != (UnityEngine.Object)null)
			{
				if (UnityEngine.Quaternion.Angle(value.Bone.localRotation, value.Applied) < 0.05f)
				{
					value.Bone.localRotation = value.Base;
				}
				value.Set = false;
			}
		}
	}

	private void RestoreEyes()
	{
		if (ownedEyes.HasValue && !((UnityEngine.Object)(object)avatar == (UnityEngine.Object)null))
		{
			Il2CppScheduleOne.AvatarFramework.EyeController eyes = avatar.Eyes;
			if ((UnityEngine.Object)(object)((eyes != null) ? eyes.LeftEye : null) != (UnityEngine.Object)null)
			{
				eyes.LeftEye.SetEyeballColor(leftColor, leftEmission, false);
			}
			if ((UnityEngine.Object)(object)((eyes != null) ? eyes.RightEye : null) != (UnityEngine.Object)null)
			{
				eyes.RightEye.SetEyeballColor(rightColor, rightEmission, false);
			}
			ownedEyes = null;
		}
	}

	internal void Dispose()
	{
		UndoPoses();
		RestoreEyes();
		if ((UnityEngine.Object)(object)avatar != (UnityEngine.Object)null)
		{
			if (hasWeight)
			{
				Il2CppScheduleOne.AvatarFramework.AvatarEffects effects = avatar.Effects;
				if (effects != null)
				{
					effects.RemoveAdditionalWeightOverride("VX_CharacterEffect", false);
				}
			}
			if (emotion != null)
			{
				Il2CppScheduleOne.AvatarFramework.Emotions.AvatarEmotionManager emotionManager = avatar.EmotionManager;
				if (emotionManager != null)
				{
					emotionManager.RemoveEmotionOverride("VX_CharacterEffect");
				}
			}
		}
		if ((UnityEngine.Object)(object)animator != (UnityEngine.Object)null && UnityEngine.Mathf.Approximately(animator.speed, lastAnimation))
		{
			animator.speed = animationBase;
		}
		foreach (Slot value in slots.Values)
		{
			if ((UnityEngine.Object)(object)value.Renderer != (UnityEngine.Object)null)
			{
				UnityEngine.Material[] array = ((System.Collections.Generic.IEnumerable<UnityEngine.Material>)value.Renderer.sharedMaterials).ToArray();
				for (int i = 0; i < System.Math.Min(array.Length, value.Owned.Length); i++)
				{
					if ((UnityEngine.Object)(object)array[i] == (UnityEngine.Object)(object)value.Owned[i])
					{
						array[i] = value.Original[i];
					}
				}
				value.Renderer.sharedMaterials = (array);
			}
			UnityEngine.Material[] owned = value.Owned;
			foreach (UnityEngine.Material val in owned)
			{
				if ((UnityEngine.Object)(object)val != (UnityEngine.Object)null)
				{
					UnityEngine.Object.Destroy((UnityEngine.Object)(object)val);
				}
			}
		}
		slots.Clear();
	}
}
