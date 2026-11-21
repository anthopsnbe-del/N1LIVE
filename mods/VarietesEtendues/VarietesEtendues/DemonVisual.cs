using System;
using Il2CppScheduleOne.Avatar;
using Il2CppScheduleOne.AvatarFramework;
using Il2CppScheduleOne.PlayerScripts;
using MelonLoader;
using UnityEngine;

namespace VarietesEtendues;

internal sealed class DemonVisual
{
	private readonly Transform actor;

	private readonly GameObject horns;

	private readonly GameObject cloud;

	private AudioSource? audio;

	private float nextSound;

	internal DemonVisual(Transform actor)
	{
		//IL_0013: Unknown result type (might be due to invalid IL or missing references)
		//IL_001d: Expected O, but got Unknown
		//IL_0035: Unknown result type (might be due to invalid IL or missing references)
		//IL_003f: Expected O, but got Unknown
		//IL_007a: Unknown result type (might be due to invalid IL or missing references)
		//IL_007f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0090: Unknown result type (might be due to invalid IL or missing references)
		//IL_0095: Unknown result type (might be due to invalid IL or missing references)
		//IL_00a6: Unknown result type (might be due to invalid IL or missing references)
		//IL_00ab: Unknown result type (might be due to invalid IL or missing references)
		//IL_00bc: Unknown result type (might be due to invalid IL or missing references)
		//IL_00c1: Unknown result type (might be due to invalid IL or missing references)
		//IL_00d2: Unknown result type (might be due to invalid IL or missing references)
		//IL_00d7: Unknown result type (might be due to invalid IL or missing references)
		//IL_00eb: Unknown result type (might be due to invalid IL or missing references)
		//IL_010f: Unknown result type (might be due to invalid IL or missing references)
		//IL_012e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0158: Unknown result type (might be due to invalid IL or missing references)
		//IL_0195: Unknown result type (might be due to invalid IL or missing references)
		this.actor = actor;
		horns = new GameObject("VX_DemonHorns");
		horns.transform.SetParent(actor, false);
		cloud = new GameObject("VX_DemonSmoke");
		cloud.transform.SetParent(actor, false);
		for (int i = -1; i <= 1; i += 2)
		{
			GameObject obj = RealModels.Lathe(horns.transform, "VX_Corne", (Vector2[])(object)new Vector2[5]
			{
				new Vector2(0.018f, 0f),
				new Vector2(0.016f, 0.024f),
				new Vector2(0.011f, 0.049f),
				new Vector2(0.004f, 0.073f),
				new Vector2(0f, 0.088f)
			}, new Color(0.48f, 0.008f, 0.015f), 24);
			obj.transform.localPosition = new Vector3((float)i * 0.074f, 0f, 0f);
			obj.transform.localRotation = Quaternion.Euler(-17f, 0f, (float)(-i * 19));
		}
		Emitter(new Color(0.012f, 0.012f, 0.012f, 0.72f), 34f, 0.3f, 0.32f, 3.8f, 0.32f, 0.18f);
		Emitter(new Color(0.35f, 0.003f, 0.012f, 0.2f), 15f, 0.23f, 0.22f, 3f, 0.28f, 0.45f);
		try
		{
			audio = cloud.AddComponent<AudioSource>();
			audio.clip = DemonSound.Clip;
			audio.volume = 0.16f;
			audio.pitch = 0.82f;
			AudioSource? obj2 = audio;
			Player component = ((Component)actor).GetComponent<Player>();
			obj2.spatialBlend = ((component != null && component.IsLocalPlayer) ? 0.25f : 1f);
			audio.minDistance = 1f;
			audio.maxDistance = 9f;
			audio.loop = false;
		}
		catch (Exception ex)
		{
			MelonLogger.Warning("Son démon indisponible : " + ex.Message);
		}
	}

	private void Emitter(Color color, float rate, float size, float speed, float lifetime, float radius, float startHeight)
	{
		//IL_0005: Unknown result type (might be due to invalid IL or missing references)
		//IL_000b: Expected O, but got Unknown
		//IL_0037: Unknown result type (might be due to invalid IL or missing references)
		//IL_00af: Unknown result type (might be due to invalid IL or missing references)
		//IL_00c3: Unknown result type (might be due to invalid IL or missing references)
		//IL_00c9: Expected O, but got Unknown
		GameObject val = new GameObject("VX_SmokeLayer");
		val.transform.SetParent(cloud.transform, false);
		ParticleSystem obj = val.AddComponent<ParticleSystem>();
		obj.Stop(true, (ParticleSystemStopBehavior)0);
		MainModule main = obj.main;
		main.startColor = (color);
		main.startSize = (size);
		main.startSpeed = (speed);
		main.startLifetime = (lifetime);
		main.maxParticles = 150;
		obj.emission.rateOverTime = (rate);
		ShapeModule shape = obj.shape;
		shape.shapeType = (ParticleSystemShapeType)4;
		shape.radius = radius;
		shape.angle = 8f;
		shape.position = new Vector3(0f, 0f, startHeight);
		Material val2 = new Material(Shader.Find("Sprites/Default"));
		val2.mainTexture = (Texture)(object)Devil.SmokeTexture;
		((Renderer)val.GetComponent<ParticleSystemRenderer>()).sharedMaterial = val2;
		obj.Play();
	}

	internal void Tick(AvatarFrame.Frame frame)
	{
		//IL_0144: Unknown result type (might be due to invalid IL or missing references)
		//IL_014b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0157: Unknown result type (might be due to invalid IL or missing references)
		//IL_015c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0163: Unknown result type (might be due to invalid IL or missing references)
		//IL_0175: Unknown result type (might be due to invalid IL or missing references)
		//IL_017a: Unknown result type (might be due to invalid IL or missing references)
		//IL_0181: Unknown result type (might be due to invalid IL or missing references)
		//IL_01a9: Unknown result type (might be due to invalid IL or missing references)
		//IL_01b4: Unknown result type (might be due to invalid IL or missing references)
		//IL_01b9: Unknown result type (might be due to invalid IL or missing references)
		//IL_004a: Unknown result type (might be due to invalid IL or missing references)
		//IL_004f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0052: Unknown result type (might be due to invalid IL or missing references)
		//IL_0057: Unknown result type (might be due to invalid IL or missing references)
		//IL_006b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0070: Unknown result type (might be due to invalid IL or missing references)
		//IL_0084: Unknown result type (might be due to invalid IL or missing references)
		//IL_008b: Unknown result type (might be due to invalid IL or missing references)
		//IL_00ac: Unknown result type (might be due to invalid IL or missing references)
		//IL_00b3: Unknown result type (might be due to invalid IL or missing references)
		//IL_00d9: Unknown result type (might be due to invalid IL or missing references)
		//IL_00de: Unknown result type (might be due to invalid IL or missing references)
		//IL_00e3: Unknown result type (might be due to invalid IL or missing references)
		//IL_00e8: Unknown result type (might be due to invalid IL or missing references)
		//IL_00eb: Unknown result type (might be due to invalid IL or missing references)
		//IL_00ef: Unknown result type (might be due to invalid IL or missing references)
		//IL_00f4: Unknown result type (might be due to invalid IL or missing references)
		//IL_00fb: Unknown result type (might be due to invalid IL or missing references)
		if ((Object)(object)actor == (Object)null)
		{
			return;
		}
		float num = frame.Size * 0.58f;
		Avatar? avatar = frame.Avatar;
		object obj;
		if (avatar == null)
		{
			obj = null;
		}
		else
		{
			AvatarAppearance appearance = avatar.Appearance;
			obj = ((appearance != null) ? appearance._faceMesh : null);
		}
		SkinnedMeshRenderer val = (SkinnedMeshRenderer)obj;
		if ((Object)(object)val != (Object)null)
		{
			Bounds bounds = ((Renderer)val).bounds;
			Vector3 size = bounds.size;
			if (size.magnitude > 0.1f)
			{
				size = bounds.size;
				if (size.magnitude < 1.2f && Vector3.Distance(bounds.center, frame.Eyes) < 0.5f)
				{
					num = float.MinValue;
					for (int i = 0; i < 8; i++)
					{
						Vector3 val2 = bounds.center + Vector3.Scale(bounds.extents, new Vector3((float)((((uint)i & (true ? 1u : 0u)) != 0) ? 1 : (-1)), (float)((((uint)i & 2u) != 0) ? 1 : (-1)), (float)((((uint)i & 4u) != 0) ? 1 : (-1))));
						num = Mathf.Max(num, Vector3.Dot(val2 - frame.Eyes, frame.Up));
					}
					num = Mathf.Clamp(num, frame.Size * 0.4f, frame.Size * 0.85f);
				}
			}
		}
		AvatarFrame.Place(horns.transform, frame.Eyes + frame.Up * (num - 0.009f) - frame.Forward * (frame.Size * 0.2f), frame.Rotation, frame.Size / 0.25f);
		AvatarFrame.Place(cloud.transform, actor.position, Quaternion.LookRotation(actor.up));
		if ((Object)(object)audio != (Object)null && Time.unscaledTime >= nextSound)
		{
			audio.Play();
			nextSound = Time.unscaledTime + 18f;
		}
	}

	internal void Dispose()
	{
		if ((Object)(object)audio != (Object)null)
		{
			audio.Stop();
		}
		Devil.DestroyOwned(horns);
		Devil.DestroyOwned(cloud);
	}
}
