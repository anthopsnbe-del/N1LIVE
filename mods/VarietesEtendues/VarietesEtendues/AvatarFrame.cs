using System.Runtime.CompilerServices;
using System.Text;
using Il2CppScheduleOne.AvatarFramework;
using UnityEngine;

namespace VarietesEtendues;

internal static class AvatarFrame
{
	internal readonly record struct Frame(Vector3 Eyes, Quaternion Rotation, float Size, Avatar? Avatar)
	{
		internal Vector3 Up => Rotation * Vector3.up;

		internal Vector3 Right => Rotation * Vector3.right;

		internal Vector3 Forward => Rotation * Vector3.forward;

		[CompilerGenerated]
		private bool PrintMembers(StringBuilder builder)
		{
			//IL_000e: Unknown result type (might be due to invalid IL or missing references)
			//IL_0013: Unknown result type (might be due to invalid IL or missing references)
			//IL_0035: Unknown result type (might be due to invalid IL or missing references)
			//IL_003a: Unknown result type (might be due to invalid IL or missing references)
			builder.Append("Eyes = ");
			Vector3 eyes = Eyes;
			builder.Append(eyes.ToString());
			builder.Append(", Rotation = ");
			Quaternion rotation = Rotation;
			builder.Append(rotation.ToString());
			builder.Append(", Size = ");
			builder.Append(Size.ToString());
			builder.Append(", Avatar = ");
			builder.Append(Avatar);
			return true;
		}
	}

	internal static Frame Read(Transform actor)
	{
		//IL_0053: Unknown result type (might be due to invalid IL or missing references)
		//IL_0059: Unknown result type (might be due to invalid IL or missing references)
		//IL_0063: Unknown result type (might be due to invalid IL or missing references)
		//IL_0068: Unknown result type (might be due to invalid IL or missing references)
		//IL_006d: Unknown result type (might be due to invalid IL or missing references)
		//IL_006f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0074: Unknown result type (might be due to invalid IL or missing references)
		//IL_0096: Unknown result type (might be due to invalid IL or missing references)
		//IL_009b: Unknown result type (might be due to invalid IL or missing references)
		//IL_009f: Unknown result type (might be due to invalid IL or missing references)
		//IL_00a4: Unknown result type (might be due to invalid IL or missing references)
		//IL_00a7: Unknown result type (might be due to invalid IL or missing references)
		//IL_00ac: Unknown result type (might be due to invalid IL or missing references)
		//IL_00b0: Unknown result type (might be due to invalid IL or missing references)
		//IL_00b5: Unknown result type (might be due to invalid IL or missing references)
		//IL_00b7: Unknown result type (might be due to invalid IL or missing references)
		//IL_00b9: Unknown result type (might be due to invalid IL or missing references)
		//IL_027a: Unknown result type (might be due to invalid IL or missing references)
		//IL_027b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0246: Unknown result type (might be due to invalid IL or missing references)
		//IL_024c: Unknown result type (might be due to invalid IL or missing references)
		//IL_00da: Unknown result type (might be due to invalid IL or missing references)
		//IL_00dc: Unknown result type (might be due to invalid IL or missing references)
		//IL_00de: Unknown result type (might be due to invalid IL or missing references)
		//IL_00e8: Unknown result type (might be due to invalid IL or missing references)
		//IL_00ee: Unknown result type (might be due to invalid IL or missing references)
		//IL_025f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0265: Unknown result type (might be due to invalid IL or missing references)
		//IL_026f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0274: Unknown result type (might be due to invalid IL or missing references)
		//IL_0279: Unknown result type (might be due to invalid IL or missing references)
		//IL_0102: Unknown result type (might be due to invalid IL or missing references)
		//IL_0104: Unknown result type (might be due to invalid IL or missing references)
		//IL_0106: Unknown result type (might be due to invalid IL or missing references)
		//IL_0110: Unknown result type (might be due to invalid IL or missing references)
		//IL_0115: Unknown result type (might be due to invalid IL or missing references)
		//IL_012f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0131: Unknown result type (might be due to invalid IL or missing references)
		//IL_0133: Unknown result type (might be due to invalid IL or missing references)
		//IL_0138: Unknown result type (might be due to invalid IL or missing references)
		//IL_013c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0141: Unknown result type (might be due to invalid IL or missing references)
		//IL_0143: Unknown result type (might be due to invalid IL or missing references)
		//IL_0146: Unknown result type (might be due to invalid IL or missing references)
		//IL_0161: Unknown result type (might be due to invalid IL or missing references)
		//IL_0166: Unknown result type (might be due to invalid IL or missing references)
		//IL_0157: Unknown result type (might be due to invalid IL or missing references)
		//IL_0159: Unknown result type (might be due to invalid IL or missing references)
		//IL_015e: Unknown result type (might be due to invalid IL or missing references)
		//IL_01e1: Unknown result type (might be due to invalid IL or missing references)
		//IL_01e3: Unknown result type (might be due to invalid IL or missing references)
		//IL_01e5: Unknown result type (might be due to invalid IL or missing references)
		//IL_01ea: Unknown result type (might be due to invalid IL or missing references)
		//IL_01ee: Unknown result type (might be due to invalid IL or missing references)
		//IL_01f3: Unknown result type (might be due to invalid IL or missing references)
		//IL_0203: Unknown result type (might be due to invalid IL or missing references)
		//IL_0205: Unknown result type (might be due to invalid IL or missing references)
		//IL_0207: Unknown result type (might be due to invalid IL or missing references)
		//IL_0209: Unknown result type (might be due to invalid IL or missing references)
		//IL_020e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0213: Unknown result type (might be due to invalid IL or missing references)
		//IL_01a0: Unknown result type (might be due to invalid IL or missing references)
		//IL_01b0: Unknown result type (might be due to invalid IL or missing references)
		//IL_01b5: Unknown result type (might be due to invalid IL or missing references)
		//IL_01ba: Unknown result type (might be due to invalid IL or missing references)
		//IL_01da: Unknown result type (might be due to invalid IL or missing references)
		//IL_01df: Unknown result type (might be due to invalid IL or missing references)
		Avatar componentInChildren = ((Component)actor).GetComponentInChildren<Avatar>(true);
		object obj;
		if (componentInChildren == null)
		{
			obj = null;
		}
		else
		{
			EyeController eyes = componentInChildren.Eyes;
			if (eyes == null)
			{
				obj = null;
			}
			else
			{
				Eye leftEye = eyes.LeftEye;
				obj = ((leftEye != null) ? leftEye._eyeballRenderer : null);
			}
		}
		MeshRenderer val = (MeshRenderer)obj;
		object obj2;
		if (componentInChildren == null)
		{
			obj2 = null;
		}
		else
		{
			EyeController eyes2 = componentInChildren.Eyes;
			if (eyes2 == null)
			{
				obj2 = null;
			}
			else
			{
				Eye rightEye = eyes2.RightEye;
				obj2 = ((rightEye != null) ? rightEye._eyeballRenderer : null);
			}
		}
		MeshRenderer val2 = (MeshRenderer)obj2;
		Vector3 eyes3 = actor.position + actor.up * 1.65f;
		Quaternion rotation = actor.rotation;
		float size = 0.25f;
		if ((Object)(object)val != (Object)null && (Object)(object)val2 != (Object)null)
		{
			Bounds bounds = ((Renderer)val).bounds;
			Vector3 center = bounds.center;
			bounds = ((Renderer)val2).bounds;
			Vector3 center2 = bounds.center;
			float num = Vector3.Distance(center, center2);
			if (num > 0.015f && num < 0.5f && Vector3.Distance((center + center2) * 0.5f, actor.position) < 4f)
			{
				eyes3 = (center + center2) * 0.5f;
				size = Mathf.Clamp(num * 2.7f, 0.16f, 0.42f);
				Vector3 val3 = center2 - center;
				Vector3 val4 = val3.normalized;
				if (Vector3.Dot(val4, actor.right) < 0f)
				{
					val4 = -val4;
				}
				Vector3 val5 = actor.up;
				AvatarEffects effects = componentInChildren.Effects;
				if ((Object)(object)((effects != null) ? effects.HeadBone : null) != (Object)null && (Object)(object)componentInChildren.Effects.NeckBone != (Object)null)
				{
					Vector3 val6 = componentInChildren.Effects.HeadBone.position - componentInChildren.Effects.NeckBone.position;
					if (val6.magnitude > 0.015f && val6.magnitude < 0.5f)
					{
						val5 = val6.normalized;
					}
				}
				val3 = Vector3.Cross(val4, val5);
				Vector3 normalized = val3.normalized;
				if (normalized.sqrMagnitude > 0.1f)
				{
					rotation = Quaternion.LookRotation(normalized, Vector3.Cross(normalized, val4));
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
				AvatarEffects effects2 = componentInChildren.Effects;
				obj3 = ((effects2 != null) ? effects2.HeadBone : null);
			}
			if ((Object)obj3 != (Object)null)
			{
				Transform headBone = componentInChildren.Effects.HeadBone;
				if (Vector3.Distance(headBone.position, actor.position) < 4f)
				{
					eyes3 = headBone.position + actor.up * 0.07f;
				}
			}
		}
		return new Frame(eyes3, rotation, size, componentInChildren);
	}

	internal static void Place(Transform root, Vector3 position, Quaternion rotation, float worldScale = 1f)
	{
		//IL_0001: Unknown result type (might be due to invalid IL or missing references)
		//IL_0002: Unknown result type (might be due to invalid IL or missing references)
		//IL_0023: Unknown result type (might be due to invalid IL or missing references)
		//IL_0016: Unknown result type (might be due to invalid IL or missing references)
		//IL_0028: Unknown result type (might be due to invalid IL or missing references)
		//IL_0030: Unknown result type (might be due to invalid IL or missing references)
		//IL_0047: Unknown result type (might be due to invalid IL or missing references)
		//IL_005e: Unknown result type (might be due to invalid IL or missing references)
		//IL_006f: Unknown result type (might be due to invalid IL or missing references)
		root.SetPositionAndRotation(position, rotation);
		Vector3 val = (((Object)(object)root.parent != (Object)null) ? root.parent.lossyScale : Vector3.one);
		root.localScale = new Vector3(worldScale / Mathf.Max(0.0001f, Mathf.Abs(val.x)), worldScale / Mathf.Max(0.0001f, Mathf.Abs(val.y)), worldScale / Mathf.Max(0.0001f, Mathf.Abs(val.z)));
	}
}
