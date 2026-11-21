using System;
using System.Collections.Generic;
using System.Linq;
using Il2CppFishNet.Object;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using Il2CppScheduleOne.AvatarFramework;
using Il2CppScheduleOne.NPCs;
using Il2CppScheduleOne.PlayerScripts;
using MelonLoader;
using UnityEngine;
using UnityEngine.AI;

namespace VarietesEtendues;

internal static class CharacterEffects
{
	private sealed class State
	{
		internal Transform Actor;

		internal readonly HashSet<string> Ids = new HashSet<string>(StringComparer.Ordinal);

		/// <summary>PNJ : heure d'application de chaque effet (pour l'arrêt après EffectDuration).</summary>
		internal readonly Dictionary<string, float> Since = new Dictionary<string, float>(StringComparer.Ordinal);

		/// <summary>Effets permanents (ex. Lucien, toujours en Diable) : jamais expirés.</summary>
		internal readonly HashSet<string> Permanent = new HashSet<string>(StringComparer.Ordinal);

		internal readonly Dictionary<int, AvatarSkinLease> Skins = new Dictionary<int, AvatarSkinLease>();

		internal DemonVisual? Demon;

		internal GameObject? Details;

		internal ParticleSystem? Breath;

		internal string DetailKind = "";

		internal NavMeshAgent? Agent;

		internal float BaseSpeed;

		internal float LastSpeed;

		internal bool AgentChanged;

		internal float RetryAt;

		internal int Failures;
	}

	private static readonly Dictionary<int, State> states = new Dictionary<int, State>();

	/// <summary>
	/// PNJ dont l'effet a expiré : on ignore les ré-applications du même effet
	/// jusqu'à ce que le jeu/S1API appelle le « clear » correspondant.
	/// </summary>
	private static readonly Dictionary<int, HashSet<string>> expired = new Dictionary<int, HashSet<string>>();

	internal static string[] Ids(Transform actor)
	{
		if (!ActiveConsumption.IsPlayer(actor, out Player player))
		{
			if (!((Object)(object)actor != (Object)null) || !states.TryGetValue(((Object)actor).GetInstanceID(), out State value))
			{
				return Array.Empty<string>();
			}
			return value.Ids.ToArray();
		}
		return ActiveConsumption.PlayerIds(player);
	}

	internal static bool Has(Transform actor, string id)
	{
		return ((ReadOnlySpan<string>)Ids(actor)).Contains(id);
	}

	internal static bool DemonReady(Transform actor)
	{
		if ((Object)(object)actor != (Object)null && states.TryGetValue(((Object)actor).GetInstanceID(), out State value) && value.Demon != null)
		{
			return value.Skins.Count > 0;
		}
		return false;
	}

	internal static EffectTuning Tuning(Transform actor, bool sprint = false)
	{
		return EffectRules.Combine(Ids(actor), sprint, GameplayEffects.DevilSprint);
	}

	internal static void Add(Transform actor, string id, bool permanent = false)
	{
		if ((Object)(object)actor == (Object)null)
		{
			return;
		}
		bool flag = ActiveConsumption.IsPlayer(actor, out Player player);
		if (flag && !((ReadOnlySpan<string>)ActiveConsumption.PlayerIds(player)).Contains(id))
		{
			return;
		}
		int instanceID = ((Object)actor).GetInstanceID();
		if (!flag && !permanent && expired.TryGetValue(instanceID, out HashSet<string> value2) && value2.Contains(id))
		{
			return;
		}
		if (!states.TryGetValue(instanceID, out State value))
		{
			value = new State
			{
				Actor = actor
			};
			states[instanceID] = value;
		}
		if (value.Ids.Add(id) || !value.Since.ContainsKey(id))
		{
			value.Since[id] = Time.time;
		}
		if (permanent)
		{
			value.Permanent.Add(id);
		}
	}

	internal static void Remove(Transform actor, string id)
	{
		if ((Object)(object)actor != (Object)null && expired.TryGetValue(((Object)actor).GetInstanceID(), out HashSet<string> value2))
		{
			value2.Remove(id);
		}
		if (!((Object)(object)actor == (Object)null) && states.TryGetValue(((Object)actor).GetInstanceID(), out State value))
		{
			value.Ids.Remove(id);
			value.Since.Remove(id);
			value.Permanent.Remove(id);
			if (value.Ids.Count == 0)
			{
				Dispose(value);
			}
		}
	}

	internal static void Reconcile(Transform actor, IEnumerable<string> desired)
	{
		if ((Object)(object)actor == (Object)null)
		{
			return;
		}
		HashSet<string> hashSet = new HashSet<string>(desired, StringComparer.Ordinal);
		if (hashSet.Count == 0)
		{
			if (states.TryGetValue(((Object)actor).GetInstanceID(), out State value))
			{
				Dispose(value);
			}
			return;
		}
		foreach (string item in hashSet)
		{
			Add(actor, item);
		}
		if (states.TryGetValue(((Object)actor).GetInstanceID(), out State value2))
		{
			value2.Ids.IntersectWith(hashSet);
		}
	}

	private static void BindAvatar(State state, Avatar? avatar)
	{
		if (!((Object)(object)avatar == (Object)null))
		{
			int instanceID = ((Object)avatar).GetInstanceID();
			if (!state.Skins.ContainsKey(instanceID))
			{
				state.Skins[instanceID] = new AvatarSkinLease(avatar);
			}
		}
	}

	internal static void Tick()
	{
		//IL_023b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0242: Unknown result type (might be due to invalid IL or missing references)
		//IL_024c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0251: Unknown result type (might be due to invalid IL or missing references)
		//IL_0258: Unknown result type (might be due to invalid IL or missing references)
		//IL_0262: Unknown result type (might be due to invalid IL or missing references)
		//IL_0267: Unknown result type (might be due to invalid IL or missing references)
		//IL_02d5: Unknown result type (might be due to invalid IL or missing references)
		//IL_02e2: Unknown result type (might be due to invalid IL or missing references)
		//IL_02e6: Unknown result type (might be due to invalid IL or missing references)
		//IL_029a: Unknown result type (might be due to invalid IL or missing references)
		//IL_02d0: Unknown result type (might be due to invalid IL or missing references)
		//IL_02b1: Unknown result type (might be due to invalid IL or missing references)
		//IL_02b8: Unknown result type (might be due to invalid IL or missing references)
		//IL_02c2: Unknown result type (might be due to invalid IL or missing references)
		//IL_02c7: Unknown result type (might be due to invalid IL or missing references)
		State[] array = states.Values.ToArray();
		foreach (State state in array)
		{
			if ((Object)(object)state.Actor == (Object)null)
			{
				Dispose(state);
				continue;
			}
			if (ActiveConsumption.IsPlayer(state.Actor, out Player player))
			{
				state.Ids.IntersectWith(ActiveConsumption.PlayerIds(player));
				if (state.Ids.Count == 0)
				{
					Dispose(state);
					continue;
				}
			}
			else
			{
				// PNJ : arrêt net après EffectDuration (sauf effets permanents).
				foreach (string item2 in state.Ids.ToArray())
				{
					if (!state.Permanent.Contains(item2) && state.Since.TryGetValue(item2, out float value3) && Time.time - value3 >= Settings.EffectDuration)
					{
						state.Ids.Remove(item2);
						state.Since.Remove(item2);
						int instanceID = ((Object)state.Actor).GetInstanceID();
						if (!expired.TryGetValue(instanceID, out HashSet<string> value4))
						{
							value4 = (expired[instanceID] = new HashSet<string>(StringComparer.Ordinal));
						}
						value4.Add(item2);
					}
				}
				if (state.Ids.Count == 0)
				{
					Dispose(state);
					continue;
				}
			}
			if (Time.unscaledTime < state.RetryAt)
			{
				continue;
			}
			try
			{
				AvatarFrame.Frame frame = AvatarFrame.Read(state.Actor);
				BindAvatar(state, frame.Avatar);
				Avatar? avatar = frame.Avatar;
				object obj;
				if (avatar == null)
				{
					obj = null;
				}
				else
				{
					AvatarEffects effects = avatar.Effects;
					obj = ((effects != null) ? effects.MirrorEffectsTo : null);
				}
				if (obj != null)
				{
					foreach (AvatarEffects item in (Il2CppArrayBase<AvatarEffects>)(object)frame.Avatar.Effects.MirrorEffectsTo)
					{
						if ((Object)(object)item != (Object)null)
						{
							BindAvatar(state, item.Avatar);
						}
					}
				}
				foreach (AvatarSkinLease value in state.Skins.Values)
				{
					value.Apply(state.Ids, Time.time);
				}
				bool num = state.Ids.Contains("vx_diable");
				if (num)
				{
					State state2 = state;
					if (state2.Demon == null)
					{
						state2.Demon = new DemonVisual(state.Actor);
					}
					state.Demon.Tick(frame);
				}
				else if (state.Demon != null)
				{
					state.Demon.Dispose();
					state.Demon = null;
				}
				string text = (num ? "" : (state.Ids.Contains("vx_mixer_durian") ? "spines" : (state.Ids.Contains("vx_mixer_nitro") ? "frost" : "")));
				if (text != state.DetailKind)
				{
					ClearDetails(state);
					state.DetailKind = text;
					if (text.Length > 0)
					{
						BuildDetails(state, frame, text);
					}
				}
				if ((Object)(object)state.Details != (Object)null)
				{
					_003F val;
					if (!(text == "spines"))
					{
						val = frame.Eyes - frame.Up * 0.035f + frame.Forward * 0.1f;
					}
					else
					{
						Avatar? avatar2 = frame.Avatar;
						Vector3? obj2;
						if (avatar2 == null)
						{
							obj2 = null;
						}
						else
						{
							Transform middleSpine = avatar2.MiddleSpine;
							obj2 = ((middleSpine != null) ? new Vector3?(middleSpine.position) : null);
						}
						val = ((_003F?)obj2) ?? (frame.Eyes - frame.Up * 0.4f);
					}
					Vector3 position = (Vector3)val;
					AvatarFrame.Place(state.Details.transform, position, frame.Rotation);
				}
				ApplyNpcSpeed(state);
			}
			catch (Exception ex)
			{
				state.RetryAt = Time.unscaledTime + 2f;
				if (state.Failures++ == 0)
				{
					MelonLogger.Warning("Effet visuel en attente : " + ex.Message);
				}
			}
		}
		GameplayEffects.Tick();
	}

	private static void ApplyNpcSpeed(State s)
	{
		if ((Object)(object)((Component)s.Actor).GetComponent<Player>() != (Object)null)
		{
			return;
		}
		NPC component = ((Component)s.Actor).GetComponent<NPC>();
		if ((Object)(object)component == (Object)null || !((NetworkBehaviour)component).IsServerInitialized)
		{
			return;
		}
		if (s.Agent == null)
		{
			s.Agent = ((Component)s.Actor).GetComponentInChildren<NavMeshAgent>(true);
		}
		if (!((Object)(object)s.Agent == (Object)null) && ((Behaviour)s.Agent).enabled)
		{
			float speed = Tuning(s.Actor).Speed;
			if (!s.AgentChanged)
			{
				s.BaseSpeed = s.Agent.speed;
				s.LastSpeed = s.BaseSpeed;
				s.AgentChanged = true;
			}
			else if (!Mathf.Approximately(s.Agent.speed, s.LastSpeed))
			{
				s.BaseSpeed = s.Agent.speed;
			}
			s.Agent.speed = (s.LastSpeed = s.BaseSpeed * speed);
		}
	}

	private static void BuildDetails(State s, AvatarFrame.Frame frame, string kind)
	{
		//IL_000c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0016: Expected O, but got Unknown
		//IL_015d: Unknown result type (might be due to invalid IL or missing references)
		//IL_01e3: Unknown result type (might be due to invalid IL or missing references)
		//IL_01e9: Expected O, but got Unknown
		//IL_006d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0072: Unknown result type (might be due to invalid IL or missing references)
		//IL_0083: Unknown result type (might be due to invalid IL or missing references)
		//IL_0088: Unknown result type (might be due to invalid IL or missing references)
		//IL_0099: Unknown result type (might be due to invalid IL or missing references)
		//IL_009e: Unknown result type (might be due to invalid IL or missing references)
		//IL_00b2: Unknown result type (might be due to invalid IL or missing references)
		//IL_00df: Unknown result type (might be due to invalid IL or missing references)
		//IL_00fe: Unknown result type (might be due to invalid IL or missing references)
		s.Details = new GameObject("VX_BodyDetail_" + kind);
		s.Details.transform.SetParent(s.Actor, false);
		if (kind == "spines")
		{
			for (int i = 0; i < 3; i++)
			{
				for (int j = -1; j <= 1; j += 2)
				{
					GameObject obj = RealModels.Lathe(s.Details.transform, "VX_BackSpine", (Vector2[])(object)new Vector2[3]
					{
						new Vector2(0.009f, 0f),
						new Vector2(0.006f, 0.018f),
						new Vector2(0f, 0.038f)
					}, new Color(0.22f, 0.26f, 0.11f), 10);
					obj.transform.localPosition = new Vector3((float)j * 0.08f, 0.12f - (float)i * 0.09f, -0.13f);
					obj.transform.localRotation = Quaternion.Euler(-80f, 0f, (float)(-j * 12));
				}
			}
		}
		if (kind == "frost")
		{
			ParticleSystem val = s.Details.AddComponent<ParticleSystem>();
			val.Stop(true, (ParticleSystemStopBehavior)0);
			MainModule main = val.main;
			main.startColor = MinMaxGradient.op_Implicit(new Color(0.75f, 0.9f, 1f, 0.12f));
			main.startLifetime = MinMaxCurve.op_Implicit(0.65f);
			main.startSpeed = MinMaxCurve.op_Implicit(0.07f);
			main.startSize = MinMaxCurve.op_Implicit(0.035f);
			main.maxParticles = 8;
			val.emission.rateOverTime = MinMaxCurve.op_Implicit(3f);
			ShapeModule shape = val.shape;
			shape.shapeType = (ParticleSystemShapeType)4;
			shape.radius = 0.012f;
			shape.angle = 8f;
			Material val2 = new Material(Shader.Find("Sprites/Default"));
			val2.mainTexture = (Texture)(object)Devil.SmokeTexture;
			((Renderer)s.Details.GetComponent<ParticleSystemRenderer>()).sharedMaterial = val2;
			val.Play();
			s.Breath = val;
		}
	}

	private static void ClearDetails(State s)
	{
		if ((Object)(object)s.Details != (Object)null)
		{
			Devil.DestroyOwned(s.Details);
		}
		s.Details = null;
		s.Breath = null;
	}

	private static void Dispose(State s)
	{
		State s2 = s;
		foreach (AvatarSkinLease value in s2.Skins.Values)
		{
			value.Dispose();
		}
		s2.Skins.Clear();
		s2.Demon?.Dispose();
		ClearDetails(s2);
		if (s2.AgentChanged && (Object)(object)s2.Agent != (Object)null && Mathf.Approximately(s2.Agent.speed, s2.LastSpeed))
		{
			s2.Agent.speed = s2.BaseSpeed;
		}
		KeyValuePair<int, State>[] array = states.Where<KeyValuePair<int, State>>((KeyValuePair<int, State> p) => p.Value == s2).ToArray();
		foreach (KeyValuePair<int, State> keyValuePair in array)
		{
			states.Remove(keyValuePair.Key);
		}
	}

	internal static void ClearAll()
	{
		State[] array = states.Values.ToArray();
		for (int i = 0; i < array.Length; i++)
		{
			Dispose(array[i]);
		}
		states.Clear();
		expired.Clear();
		ActiveConsumption.ClearAll();
		GameplayEffects.Clear();
	}
}
