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
	private sealed record Slot(Renderer Renderer, Material[] Original, Material[] Owned, bool Skin);

	private sealed class Pose
	{
		internal Transform Bone;

		internal Quaternion Base;

		internal Quaternion Applied;

		internal bool Set;
	}

	private readonly Avatar avatar;

	private readonly Dictionary<int, Slot> slots = new Dictionary<int, Slot>();

	private float refresh;

	private readonly Color leftColor;

	private readonly Color rightColor;

	private readonly float leftEmission;

	private readonly float rightEmission;

	private Color? ownedEyes;

	private readonly Animator? animator;

	private float animationBase;

	private float lastAnimation;

	private bool hasWeight;

	private string? emotion;

	private const string Label = "VX_CharacterEffect";

	private readonly Dictionary<int, Pose> poses = new Dictionary<int, Pose>();

	internal AvatarSkinLease(Avatar avatar)
	{
		//IL_004e: Unknown result type (might be due to invalid IL or missing references)
		//IL_006b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0062: Unknown result type (might be due to invalid IL or missing references)
		//IL_0070: Unknown result type (might be due to invalid IL or missing references)
		//IL_0099: Unknown result type (might be due to invalid IL or missing references)
		//IL_00b6: Unknown result type (might be due to invalid IL or missing references)
		//IL_00ad: Unknown result type (might be due to invalid IL or missing references)
		//IL_00bb: Unknown result type (might be due to invalid IL or missing references)
		this.avatar = avatar;
		EyeController eyes = avatar.Eyes;
		Color? obj;
		if (eyes == null)
		{
			obj = null;
		}
		else
		{
			Eye leftEye = eyes.LeftEye;
			obj = ((leftEye != null) ? new Color?(leftEye._eyeballColor) : null);
		}
		leftColor = (Color)(((_003F?)obj) ?? Color.white);
		Color? obj2;
		if (eyes == null)
		{
			obj2 = null;
		}
		else
		{
			Eye rightEye = eyes.RightEye;
			obj2 = ((rightEye != null) ? new Color?(rightEye._eyeballColor) : null);
		}
		rightColor = (Color)(((_003F?)obj2) ?? Color.white);
		float? obj3;
		if (eyes == null)
		{
			obj3 = null;
		}
		else
		{
			Eye leftEye2 = eyes.LeftEye;
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
			Eye rightEye2 = eyes.RightEye;
			obj4 = ((rightEye2 != null) ? new float?(rightEye2._eyeballEmission) : null);
		}
		num = obj4;
		rightEmission = num.GetValueOrDefault(0.115f);
		AvatarAnimation animation = avatar.Animation;
		animator = ((animation != null) ? animation._animator : null);
		if ((Object)(object)animator != (Object)null)
		{
			lastAnimation = (animationBase = animator.speed);
		}
		Capture();
	}

	private static bool IsEye(Renderer renderer)
	{
		string text = ((Object)renderer).name.ToLowerInvariant();
		if (!text.Contains("eyeball") && !text.Contains("pupil") && !text.Contains("cornea") && !text.Contains("teeth"))
		{
			return text.Contains("tongue");
		}
		return true;
	}

	private void Capture()
	{
		if ((Object)(object)avatar == (Object)null)
		{
			return;
		}
		foreach (Renderer componentsInChild in ((Component)avatar).GetComponentsInChildren<Renderer>(true))
		{
			if (((Object)(object)((Il2CppObjectBase)componentsInChild).TryCast<SkinnedMeshRenderer>() == (Object)null && (Object)(object)((Il2CppObjectBase)componentsInChild).TryCast<MeshRenderer>() == (Object)null) || IsEye(componentsInChild) || slots.ContainsKey(((Object)componentsInChild).GetInstanceID()) || ((Object)(object)avatar.CurrentEquippable != (Object)null && ((Component)componentsInChild).transform.IsChildOf(((Component)avatar.CurrentEquippable).transform)) || ((Object)componentsInChild).name.StartsWith("VX_", StringComparison.Ordinal))
			{
				continue;
			}
			Material[] array = ((IEnumerable<Material>)componentsInChild.sharedMaterials).ToArray();
			Material[] array2 = array.Select((Material m) => (Material)(((Object)(object)m != (Object)null) ? ((object)new Material(m)) : ((object)Models.Material(Color.white)))).ToArray();
			string text = (((Object)componentsInChild).name + " " + string.Join(" ", from m in array
				where (Object)(object)m != (Object)null
				select ((Object)m).name)).ToLowerInvariant();
			bool skin = (Object)(object)((Il2CppObjectBase)componentsInChild).TryCast<SkinnedMeshRenderer>() != (Object)null && (text.Contains("skin") || text.Contains("body") || text.Contains("face") || text.Contains("head") || text.Contains("hand") || text.Contains("arm"));
			AvatarAppearance appearance = avatar.Appearance;
			if ((Object)(object)((appearance != null) ? appearance._faceMesh : null) == (Object)(object)componentsInChild)
			{
				skin = true;
			}
			AvatarAppearance appearance2 = avatar.Appearance;
			if (((appearance2 != null) ? appearance2._bodyMeshes : null) != null)
			{
				foreach (SkinnedMeshRenderer item in (Il2CppArrayBase<SkinnedMeshRenderer>)(object)avatar.Appearance._bodyMeshes)
				{
					if ((Object)(object)item == (Object)(object)componentsInChild)
					{
						skin = true;
					}
				}
			}
			componentsInChild.sharedMaterials = Il2CppReferenceArray<Material>.op_Implicit(array2);
			slots[((Object)componentsInChild).GetInstanceID()] = new Slot(componentsInChild, array, array2, skin);
		}
	}

	internal void Apply(HashSet<string> ids, float time)
	{
		//IL_005f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0064: Unknown result type (might be due to invalid IL or missing references)
		//IL_006c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0071: Unknown result type (might be due to invalid IL or missing references)
		//IL_0229: Unknown result type (might be due to invalid IL or missing references)
		//IL_0246: Unknown result type (might be due to invalid IL or missing references)
		//IL_024b: Unknown result type (might be due to invalid IL or missing references)
		//IL_05c6: Unknown result type (might be due to invalid IL or missing references)
		//IL_05ab: Unknown result type (might be due to invalid IL or missing references)
		//IL_059f: Unknown result type (might be due to invalid IL or missing references)
		//IL_05ee: Unknown result type (might be due to invalid IL or missing references)
		//IL_0584: Unknown result type (might be due to invalid IL or missing references)
		//IL_0569: Unknown result type (might be due to invalid IL or missing references)
		//IL_054e: Unknown result type (might be due to invalid IL or missing references)
		//IL_02fc: Unknown result type (might be due to invalid IL or missing references)
		//IL_0301: Unknown result type (might be due to invalid IL or missing references)
		//IL_0366: Unknown result type (might be due to invalid IL or missing references)
		//IL_036b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0332: Unknown result type (might be due to invalid IL or missing references)
		//IL_0339: Unknown result type (might be due to invalid IL or missing references)
		//IL_038f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0317: Unknown result type (might be due to invalid IL or missing references)
		//IL_0319: Unknown result type (might be due to invalid IL or missing references)
		//IL_031c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0313: Unknown result type (might be due to invalid IL or missing references)
		//IL_0384: Unknown result type (might be due to invalid IL or missing references)
		//IL_0386: Unknown result type (might be due to invalid IL or missing references)
		//IL_0388: Unknown result type (might be due to invalid IL or missing references)
		//IL_0380: Unknown result type (might be due to invalid IL or missing references)
		//IL_0878: Unknown result type (might be due to invalid IL or missing references)
		//IL_08ac: Unknown result type (might be due to invalid IL or missing references)
		//IL_0837: Unknown result type (might be due to invalid IL or missing references)
		//IL_08ed: Unknown result type (might be due to invalid IL or missing references)
		//IL_093a: Unknown result type (might be due to invalid IL or missing references)
		HashSet<string> ids2 = ids;
		if ((Object)(object)avatar == (Object)null)
		{
			return;
		}
		bool flag = ids2.Contains("vx_diable");
		bool flag2 = flag || Has("paranox");
		if (Time.unscaledTime >= refresh)
		{
			Capture();
			refresh = Time.unscaledTime + 0.5f;
		}
		Color white = Color.white;
		float num = 0f;
		Color val = Color.black;
		if (Has("mango"))
		{
			((Color)(ref white))._002Ector(1f, 0.77f, 0.43f);
			num = 0.15f;
		}
		if (Has("melon"))
		{
			((Color)(ref white))._002Ector(0.65f, 0.9f, 0.8f);
			num = 0.1f;
		}
		if (Has("syrup"))
		{
			((Color)(ref white))._002Ector(0.7f, 0.66f, 0.62f);
			num = 0.2f;
		}
		if (Has("patee"))
		{
			((Color)(ref white))._002Ector(0.62f, 0.44f, 0.25f);
			num = 0.2f;
		}
		if (Has("fizz"))
		{
			((Color)(ref white))._002Ector(0.35f, 0.65f, 0.8f);
			num = 0.25f;
		}
		if (Has("lithium"))
		{
			((Color)(ref white))._002Ector(0.45f, 0.63f, 0.83f);
			num = 0.12f;
			((Color)(ref val))._002Ector(0.005f, 0.015f, 0.035f);
		}
		if (Has("durian"))
		{
			((Color)(ref white))._002Ector(0.4f, 0.42f, 0.22f);
			num = 0.65f;
		}
		if (Has("dragon"))
		{
			((Color)(ref white))._002Ector(0.43f, 0.24f, 0.57f);
			num = 0.75f;
		}
		if (Has("nitro"))
		{
			((Color)(ref white))._002Ector(0.68f, 0.85f, 0.95f);
			num = 0.65f;
		}
		if (Has("glow"))
		{
			val = new Color(0.015f, 0.085f, 0.025f) * (1f + 0.12f * Mathf.Sin(time * 1.2f));
		}
		foreach (Slot value in slots.Values)
		{
			if ((Object)(object)value.Renderer == (Object)null)
			{
				continue;
			}
			for (int i = 0; i < value.Owned.Length; i++)
			{
				Material val2 = value.Owned[i];
				Material val3 = value.Original[i];
				if ((Object)(object)val2 == (Object)null || (Object)(object)val3 == (Object)null)
				{
					continue;
				}
				string[] array = new string[4] { "_Color", "_BaseColor", "_SkinColor", "_TintColor" };
				foreach (string text in array)
				{
					if (val2.HasProperty(text))
					{
						Color color = val3.GetColor(text);
						val2.SetColor(text, (Color)(flag2 ? new Color(0.003f, 0.003f, 0.005f, color.a) : (value.Skin ? Color.Lerp(color, white, num) : color)));
					}
				}
				if (val2.HasProperty("_EmissionColor"))
				{
					Color color2 = val3.GetColor("_EmissionColor");
					val2.SetColor("_EmissionColor", flag2 ? Color.black : (value.Skin ? (color2 + val) : color2));
					if ((!flag2 && value.Skin && ((Color)(ref val)).maxColorComponent > 0f) || val3.IsKeywordEnabled("_EMISSION"))
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
					val2.SetTexture("_DetailAlbedoMap", (Texture)(object)SkinPatterns.Get((!Has("dragon")) ? (Has("glow") ? 1 : 2) : 0));
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
		Color? val4 = (flag ? new Color?(new Color(0.7f, 0.002f, 0.008f)) : (Has("paranox") ? new Color?(Color.white) : (Has("cyclo") ? new Color?(new Color(0.45f, 0.95f, 1f)) : (Has("absinthe") ? new Color?(new Color(0.2f, 0.8f, 0.35f)) : (Has("patee") ? new Color?(new Color(0.8f, 0.45f, 0.08f)) : (Has("lemon") ? new Color?(new Color(0.85f, 0.75f, 0.12f)) : null))))));
		if (val4.HasValue)
		{
			EyeController eyes = avatar.Eyes;
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
			AvatarEffects effects = avatar.Effects;
			if (effects != null)
			{
				effects.AddAdditionalWeightOverride(0.72f, 70, "VX_CharacterEffect", false);
			}
			hasWeight = true;
		}
		else if (!flag3 && hasWeight)
		{
			AvatarEffects effects2 = avatar.Effects;
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
				AvatarEmotionManager emotionManager = avatar.EmotionManager;
				if (emotionManager != null)
				{
					emotionManager.RemoveEmotionOverride("VX_CharacterEffect");
				}
			}
			emotion = null;
			if (text2 != null && (Object)(object)avatar.EmotionManager != (Object)null && avatar.EmotionManager.HasEmotion(text2))
			{
				avatar.EmotionManager.AddEmotionOverride(text2, "VX_CharacterEffect", 0f, 60);
				emotion = text2;
			}
		}
		EffectTuning effectTuning = EffectRules.Combine(ids2);
		if ((Object)(object)animator != (Object)null)
		{
			if (!Mathf.Approximately(animator.speed, lastAnimation))
			{
				animationBase = animator.speed;
			}
			animator.speed = (lastAnimation = animationBase * effectTuning.Animation);
		}
		UndoPoses();
		if (Has("syrup"))
		{
			AvatarEffects effects3 = avatar.Effects;
			Offset((effects3 != null) ? effects3.NeckBone : null, Quaternion.Euler(6f + Mathf.Sin(time * 0.7f) * 2f, 0f, Mathf.Sin(time * 0.55f) * 1.3f));
		}
		else if (Has("absinthe"))
		{
			Offset(avatar.MiddleSpine, Quaternion.Euler(0f, 0f, Mathf.Sin(time * 0.8f) * 1.8f));
		}
		else if (Has("patee"))
		{
			Offset(avatar.MiddleSpine, Quaternion.Euler(7f, 0f, 0f));
		}
		else if (Has("prune"))
		{
			Offset(avatar.MiddleSpine, Quaternion.Euler(Mathf.Sin(time * 1.1f) * 0.8f, 0f, 0f));
		}
		else if (Has("poprocks"))
		{
			AvatarEffects effects4 = avatar.Effects;
			Offset((effects4 != null) ? effects4.NeckBone : null, Quaternion.Euler(Mathf.Sin(time * 1.8f) * 0.6f, 0f, 0f));
		}
		bool Has(string key)
		{
			return ids2.Contains(EffectRules.Id(key));
		}
	}

	private void Offset(Transform? bone, Quaternion delta)
	{
		//IL_003d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0042: Unknown result type (might be due to invalid IL or missing references)
		//IL_0049: Unknown result type (might be due to invalid IL or missing references)
		//IL_004e: Unknown result type (might be due to invalid IL or missing references)
		//IL_004f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0054: Unknown result type (might be due to invalid IL or missing references)
		//IL_005b: Unknown result type (might be due to invalid IL or missing references)
		if (!((Object)(object)bone == (Object)null))
		{
			int instanceID = ((Object)bone).GetInstanceID();
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
		//IL_0037: Unknown result type (might be due to invalid IL or missing references)
		//IL_003d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0055: Unknown result type (might be due to invalid IL or missing references)
		foreach (Pose value in poses.Values)
		{
			if (value.Set && (Object)(object)value.Bone != (Object)null)
			{
				if (Quaternion.Angle(value.Bone.localRotation, value.Applied) < 0.05f)
				{
					value.Bone.localRotation = value.Base;
				}
				value.Set = false;
			}
		}
	}

	private void RestoreEyes()
	{
		//IL_0043: Unknown result type (might be due to invalid IL or missing references)
		//IL_006f: Unknown result type (might be due to invalid IL or missing references)
		if (ownedEyes.HasValue && !((Object)(object)avatar == (Object)null))
		{
			EyeController eyes = avatar.Eyes;
			if ((Object)(object)((eyes != null) ? eyes.LeftEye : null) != (Object)null)
			{
				eyes.LeftEye.SetEyeballColor(leftColor, leftEmission, false);
			}
			if ((Object)(object)((eyes != null) ? eyes.RightEye : null) != (Object)null)
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
		if ((Object)(object)avatar != (Object)null)
		{
			if (hasWeight)
			{
				AvatarEffects effects = avatar.Effects;
				if (effects != null)
				{
					effects.RemoveAdditionalWeightOverride("VX_CharacterEffect", false);
				}
			}
			if (emotion != null)
			{
				AvatarEmotionManager emotionManager = avatar.EmotionManager;
				if (emotionManager != null)
				{
					emotionManager.RemoveEmotionOverride("VX_CharacterEffect");
				}
			}
		}
		if ((Object)(object)animator != (Object)null && Mathf.Approximately(animator.speed, lastAnimation))
		{
			animator.speed = animationBase;
		}
		foreach (Slot value in slots.Values)
		{
			if ((Object)(object)value.Renderer != (Object)null)
			{
				Material[] array = ((IEnumerable<Material>)value.Renderer.sharedMaterials).ToArray();
				for (int i = 0; i < Math.Min(array.Length, value.Owned.Length); i++)
				{
					if ((Object)(object)array[i] == (Object)(object)value.Owned[i])
					{
						array[i] = value.Original[i];
					}
				}
				value.Renderer.sharedMaterials = Il2CppReferenceArray<Material>.op_Implicit(array);
			}
			Material[] owned = value.Owned;
			foreach (Material val in owned)
			{
				if ((Object)(object)val != (Object)null)
				{
					Object.Destroy((Object)(object)val);
				}
			}
		}
		slots.Clear();
	}
}
