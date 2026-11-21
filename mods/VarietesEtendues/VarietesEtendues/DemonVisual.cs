using System;
using Il2CppScheduleOne.Avatar;
using Il2CppScheduleOne.AvatarFramework;
using Il2CppScheduleOne.PlayerScripts;
using MelonLoader;
using UnityEngine;

namespace VarietesEtendues;

internal sealed class DemonVisual
{
	private readonly UnityEngine.Transform actor;

	private readonly UnityEngine.GameObject horns;

	private readonly UnityEngine.GameObject cloud;

	private UnityEngine.AudioSource? audio;

	private float nextSound;

	internal DemonVisual(UnityEngine.Transform actor)
	{
		this.actor = actor;
		horns = new GameObject("VX_DemonHorns");
		horns.transform.SetParent(actor, false);
		cloud = new GameObject("VX_DemonSmoke");
		cloud.transform.SetParent(actor, false);
		for (int i = -1; i <= 1; i += 2)
		{
			UnityEngine.GameObject obj = RealModels.Lathe(horns.transform, "VX_Corne", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[5]
			{
				new Vector2(0.018f, 0f),
				new Vector2(0.016f, 0.024f),
				new Vector2(0.011f, 0.049f),
				new Vector2(0.004f, 0.073f),
				new Vector2(0f, 0.088f)
			}, new Color(0.48f, 0.008f, 0.015f), 24);
			obj.transform.localPosition = new Vector3((float)i * 0.074f, 0f, 0f);
			obj.transform.localRotation = UnityEngine.Quaternion.Euler(-17f, 0f, (float)(-i * 19));
		}
		Emitter(new Color(0.012f, 0.012f, 0.012f, 0.72f), 34f, 0.3f, 0.32f, 3.8f, 0.32f, 0.18f);
		Emitter(new Color(0.35f, 0.003f, 0.012f, 0.2f), 15f, 0.23f, 0.22f, 3f, 0.28f, 0.45f);
		try
		{
			audio = cloud.AddComponent<UnityEngine.AudioSource>();
			audio.clip = DemonSound.Clip;
			audio.volume = 0.16f;
			audio.pitch = 0.82f;
			UnityEngine.AudioSource? obj2 = audio;
			Il2CppScheduleOne.PlayerScripts.Player component = ((UnityEngine.Component)actor).GetComponent<Il2CppScheduleOne.PlayerScripts.Player>();
			obj2.spatialBlend = ((component != null && component.IsLocalPlayer) ? 0.25f : 1f);
			audio.minDistance = 1f;
			audio.maxDistance = 9f;
			audio.loop = false;
		}
		catch (System.Exception ex)
		{
			MelonLoader.MelonLogger.Warning("Son démon indisponible : " + ex.Message);
		}
	}

	private void Emitter(UnityEngine.Color color, float rate, float size, float speed, float lifetime, float radius, float startHeight)
	{
		UnityEngine.GameObject val = new GameObject("VX_SmokeLayer");
		val.transform.SetParent(cloud.transform, false);
		UnityEngine.ParticleSystem obj = val.AddComponent<UnityEngine.ParticleSystem>();
		obj.Stop(true, (UnityEngine.ParticleSystemStopBehavior)0);
		UnityEngine.ParticleSystem.MainModule main = obj.main;
		main.startColor = (color);
		main.startSize = (size);
		main.startSpeed = (speed);
		main.startLifetime = (lifetime);
		main.maxParticles = 150;
		obj.emission.rateOverTime = (rate);
		UnityEngine.ParticleSystem.ShapeModule shape = obj.shape;
		shape.shapeType = (UnityEngine.ParticleSystemShapeType)4;
		shape.radius = radius;
		shape.angle = 8f;
		shape.position = new Vector3(0f, 0f, startHeight);
		UnityEngine.Material val2 = new Material(UnityEngine.Shader.Find("Sprites/Default"));
		val2.mainTexture = (UnityEngine.Texture)(object)Devil.SmokeTexture;
		((UnityEngine.Renderer)val.GetComponent<UnityEngine.ParticleSystemRenderer>()).sharedMaterial = val2;
		obj.Play();
	}

	internal void Tick(AvatarFrame.Frame frame)
	{
		if ((UnityEngine.Object)(object)actor == (UnityEngine.Object)null)
		{
			return;
		}
		float num = frame.Size * 0.58f;
		Il2CppScheduleOne.AvatarFramework.Avatar? avatar = frame.Avatar;
		object obj;
		if (avatar == null)
		{
			obj = null;
		}
		else
		{
			Il2CppScheduleOne.Avatar.AvatarAppearance appearance = avatar.Appearance;
			obj = ((appearance != null) ? appearance._faceMesh : null);
		}
		UnityEngine.SkinnedMeshRenderer val = (UnityEngine.SkinnedMeshRenderer)obj;
		if ((UnityEngine.Object)(object)val != (UnityEngine.Object)null)
		{
			UnityEngine.Bounds bounds = ((UnityEngine.Renderer)val).bounds;
			UnityEngine.Vector3 size = bounds.size;
			if (size.magnitude > 0.1f)
			{
				size = bounds.size;
				if (size.magnitude < 1.2f && UnityEngine.Vector3.Distance(bounds.center, frame.Eyes) < 0.5f)
				{
					num = float.MinValue;
					for (int i = 0; i < 8; i++)
					{
						UnityEngine.Vector3 val2 = bounds.center + UnityEngine.Vector3.Scale(bounds.extents, new Vector3((float)((((uint)i & (true ? 1u : 0u)) != 0) ? 1 : (-1)), (float)((((uint)i & 2u) != 0) ? 1 : (-1)), (float)((((uint)i & 4u) != 0) ? 1 : (-1))));
						num = UnityEngine.Mathf.Max(num, UnityEngine.Vector3.Dot(val2 - frame.Eyes, frame.Up));
					}
					num = UnityEngine.Mathf.Clamp(num, frame.Size * 0.4f, frame.Size * 0.85f);
				}
			}
		}
		AvatarFrame.Place(horns.transform, frame.Eyes + frame.Up * (num - 0.009f) - frame.Forward * (frame.Size * 0.2f), frame.Rotation, frame.Size / 0.25f);
		AvatarFrame.Place(cloud.transform, actor.position, UnityEngine.Quaternion.LookRotation(actor.up));
		if ((UnityEngine.Object)(object)audio != (UnityEngine.Object)null && UnityEngine.Time.unscaledTime >= nextSound)
		{
			audio.Play();
			nextSound = UnityEngine.Time.unscaledTime + 18f;
		}
	}

	internal void Dispose()
	{
		if ((UnityEngine.Object)(object)audio != (UnityEngine.Object)null)
		{
			audio.Stop();
		}
		Devil.DestroyOwned(horns);
		Devil.DestroyOwned(cloud);
	}
}
