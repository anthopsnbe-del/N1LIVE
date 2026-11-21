using System.Runtime.CompilerServices;
using System.Text;
using Il2CppScheduleOne.AvatarFramework;
using UnityEngine;

namespace VarietesEtendues;

internal static class AvatarFrame
{
	internal readonly record struct Frame(UnityEngine.Vector3 Eyes, UnityEngine.Quaternion Rotation, float Size, Il2CppScheduleOne.AvatarFramework.Avatar? Avatar)
	{
		internal UnityEngine.Vector3 Up => Rotation * UnityEngine.Vector3.up;

		internal UnityEngine.Vector3 Right => Rotation * UnityEngine.Vector3.right;

		internal UnityEngine.Vector3 Forward => Rotation * UnityEngine.Vector3.forward;

		[CompilerGenerated]
		private bool PrintMembers(System.Text.StringBuilder builder)
		{
			builder.Append("Eyes = ");
			UnityEngine.Vector3 eyes = Eyes;
			builder.Append(eyes.ToString());
			builder.Append(", Rotation = ");
			UnityEngine.Quaternion rotation = Rotation;
			builder.Append(rotation.ToString());
			builder.Append(", Size = ");
			builder.Append(Size.ToString());
			builder.Append(", Avatar = ");
			builder.Append(Il2CppScheduleOne.AvatarFramework.Avatar);
			return true;
		}
	}

	internal static Frame Read(UnityEngine.Transform actor)
	{
		Il2CppScheduleOne.AvatarFramework.Avatar componentInChildren = ((UnityEngine.Component)actor).GetComponentInChildren<Il2CppScheduleOne.AvatarFramework.Avatar>(true);
		object obj;
		if (componentInChildren == null)
		{
			obj = null;
		}
		else
		{
			Il2CppScheduleOne.AvatarFramework.EyeController eyes = componentInChildren.Eyes;
			if (eyes == null)
			{
				obj = null;
			}
			else
			{
				Il2CppScheduleOne.AvatarFramework.Eye leftEye = eyes.LeftEye;
				obj = ((leftEye != null) ? leftEye._eyeballRenderer : null);
			}
		}
		UnityEngine.MeshRenderer val = (UnityEngine.MeshRenderer)obj;
		object obj2;
		if (componentInChildren == null)
		{
			obj2 = null;
		}
		else
		{
			Il2CppScheduleOne.AvatarFramework.EyeController eyes2 = componentInChildren.Eyes;
			if (eyes2 == null)
			{
				obj2 = null;
			}
			else
			{
				Il2CppScheduleOne.AvatarFramework.Eye rightEye = eyes2.RightEye;
				obj2 = ((rightEye != null) ? rightEye._eyeballRenderer : null);
			}
		}
		UnityEngine.MeshRenderer val2 = (UnityEngine.MeshRenderer)obj2;
		UnityEngine.Vector3 eyes3 = actor.position + actor.up * 1.65f;
		UnityEngine.Quaternion rotation = actor.rotation;
		float size = 0.25f;
		if ((UnityEngine.Object)(object)val != (UnityEngine.Object)null && (UnityEngine.Object)(object)val2 != (UnityEngine.Object)null)
		{
			UnityEngine.Bounds bounds = ((UnityEngine.Renderer)val).bounds;
			UnityEngine.Vector3 center = bounds.center;
			bounds = ((UnityEngine.Renderer)val2).bounds;
			UnityEngine.Vector3 center2 = bounds.center;
			float num = UnityEngine.Vector3.Distance(center, center2);
			if (num > 0.015f && num < 0.5f && UnityEngine.Vector3.Distance((center + center2) * 0.5f, actor.position) < 4f)
			{
				eyes3 = (center + center2) * 0.5f;
				size = UnityEngine.Mathf.Clamp(num * 2.7f, 0.16f, 0.42f);
				UnityEngine.Vector3 val3 = center2 - center;
				UnityEngine.Vector3 val4 = val3.normalized;
				if (UnityEngine.Vector3.Dot(val4, actor.right) < 0f)
				{
					val4 = -val4;
				}
				UnityEngine.Vector3 val5 = actor.up;
				Il2CppScheduleOne.AvatarFramework.AvatarEffects effects = componentInChildren.Effects;
				if ((UnityEngine.Object)(object)((effects != null) ? effects.HeadBone : null) != (UnityEngine.Object)null && (UnityEngine.Object)(object)componentInChildren.Effects.NeckBone != (UnityEngine.Object)null)
				{
					UnityEngine.Vector3 val6 = componentInChildren.Effects.HeadBone.position - componentInChildren.Effects.NeckBone.position;
					if (val6.magnitude > 0.015f && val6.magnitude < 0.5f)
					{
						val5 = val6.normalized;
					}
				}
				val3 = UnityEngine.Vector3.Cross(val4, val5);
				UnityEngine.Vector3 normalized = val3.normalized;
				if (normalized.sqrMagnitude > 0.1f)
				{
					rotation = UnityEngine.Quaternion.LookRotation(normalized, UnityEngine.Vector3.Cross(normalized, val4));
				}
			}
		}
		else
		{
			object obj3;
			if (componentInChildren == null)
			{
				obj3 = null;
			}
			else
			{
				Il2CppScheduleOne.AvatarFramework.AvatarEffects effects2 = componentInChildren.Effects;
				obj3 = ((effects2 != null) ? effects2.HeadBone : null);
			}
			if ((UnityEngine.Object)obj3 != (UnityEngine.Object)null)
			{
				UnityEngine.Transform headBone = componentInChildren.Effects.HeadBone;
				if (UnityEngine.Vector3.Distance(headBone.position, actor.position) < 4f)
				{
					eyes3 = headBone.position + actor.up * 0.07f;
				}
			}
		}
		return new Frame(eyes3, rotation, size, componentInChildren);
	}

	internal static void Place(UnityEngine.Transform root, UnityEngine.Vector3 position, UnityEngine.Quaternion rotation, float worldScale = 1f)
	{
		root.SetPositionAndRotation(position, rotation);
		UnityEngine.Vector3 val = (((UnityEngine.Object)(object)root.parent != (UnityEngine.Object)null) ? root.parent.lossyScale : UnityEngine.Vector3.one);
		root.localScale = new Vector3(worldScale / UnityEngine.Mathf.Max(0.0001f, UnityEngine.Mathf.Abs(val.x)), worldScale / UnityEngine.Mathf.Max(0.0001f, UnityEngine.Mathf.Abs(val.y)), worldScale / UnityEngine.Mathf.Max(0.0001f, UnityEngine.Mathf.Abs(val.z)));
	}
}
