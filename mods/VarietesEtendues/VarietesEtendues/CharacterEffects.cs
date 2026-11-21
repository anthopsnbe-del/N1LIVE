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
		internal UnityEngine.Transform Actor;

		internal readonly System.Collections.Generic.HashSet<string> Ids = new System.Collections.Generic.HashSet<string>(System.StringComparer.Ordinal);

		/// <summary>PNJ : heure d'application de chaque effet (pour l'arrêt après EffectDuration).</summary>
		internal readonly System.Collections.Generic.Dictionary<string, float> Since = new System.Collections.Generic.Dictionary<string, float>(System.StringComparer.Ordinal);

		/// <summary>Effets permanents (ex. Lucien, toujours en Diable) : jamais expirés.</summary>
		internal readonly System.Collections.Generic.HashSet<string> Permanent = new System.Collections.Generic.HashSet<string>(System.StringComparer.Ordinal);

		internal readonly System.Collections.Generic.Dictionary<int, AvatarSkinLease> Skins = new System.Collections.Generic.Dictionary<int, AvatarSkinLease>();

		internal DemonVisual? Demon;

		internal UnityEngine.GameObject? Details;

		internal UnityEngine.ParticleSystem? Breath;

		internal string DetailKind = "";

		internal UnityEngine.AI.NavMeshAgent? Agent;

		internal float BaseSpeed;

		internal float LastSpeed;

		internal bool AgentChanged;

		internal float RetryAt;

		internal int Failures;
	}

	private static readonly System.Collections.Generic.Dictionary<int, State> states = new System.Collections.Generic.Dictionary<int, State>();

	/// <summary>
	/// PNJ dont l'effet a expiré : on ignore les ré-applications du même effet
	/// jusqu'à ce que le jeu/S1API appelle le « clear » correspondant.
	/// </summary>
	private static readonly System.Collections.Generic.Dictionary<int, System.Collections.Generic.HashSet<string>> expired = new System.Collections.Generic.Dictionary<int, System.Collections.Generic.HashSet<string>>();

	internal static string[] Ids(UnityEngine.Transform actor)
	{
		if (!ActiveConsumption.IsPlayer(actor, out Il2CppScheduleOne.PlayerScripts.Player player))
		{
			if (!((UnityEngine.Object)(object)actor != (UnityEngine.Object)null) || !states.TryGetValue(((UnityEngine.Object)actor).GetInstanceID(), out State value))
			{
				return System.Array.Empty<string>();
			}
			return value.Ids.ToArray();
		}
		return ActiveConsumption.PlayerIds(player);
	}

	internal static bool Has(UnityEngine.Transform actor, string id)
	{
		return ((System.ReadOnlySpan<string>)Ids(actor)).Contains(id);
	}

	internal static bool DemonReady(UnityEngine.Transform actor)
	{
		if ((UnityEngine.Object)(object)actor != (UnityEngine.Object)null && states.TryGetValue(((UnityEngine.Object)actor).GetInstanceID(), out State value) && value.Demon != null)
		{
			return value.Skins.Count > 0;
		}
		return false;
	}

	internal static EffectTuning Tuning(UnityEngine.Transform actor, bool sprint = false)
	{
		return EffectRules.Combine(Ids(actor), sprint, GameplayEffects.DevilSprint);
	}

	internal static void Add(UnityEngine.Transform actor, string id, bool permanent = false)
	{
		if ((UnityEngine.Object)(object)actor == (UnityEngine.Object)null)
		{
			return;
		}
		bool flag = ActiveConsumption.IsPlayer(actor, out Il2CppScheduleOne.PlayerScripts.Player player);
		if (flag && !((System.ReadOnlySpan<string>)ActiveConsumption.PlayerIds(player)).Contains(id))
		{
			return;
		}
		int instanceID = ((UnityEngine.Object)actor).GetInstanceID();
		if (!flag && !permanent && expired.TryGetValue(instanceID, out System.Collections.Generic.HashSet<string> value2) && value2.Contains(id))
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
			value.Since[id] = UnityEngine.Time.time;
		}
		if (permanent)
		{
			value.Permanent.Add(id);
		}
	}

	internal static void Remove(UnityEngine.Transform actor, string id)
	{
		if ((UnityEngine.Object)(object)actor != (UnityEngine.Object)null && expired.TryGetValue(((UnityEngine.Object)actor).GetInstanceID(), out System.Collections.Generic.HashSet<string> value2))
		{
			value2.Remove(id);
		}
		if (!((UnityEngine.Object)(object)actor == (UnityEngine.Object)null) && states.TryGetValue(((UnityEngine.Object)actor).GetInstanceID(), out State value))
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

	internal static void Reconcile(UnityEngine.Transform actor, System.Collections.Generic.IEnumerable<string> desired)
	{
		if ((UnityEngine.Object)(object)actor == (UnityEngine.Object)null)
		{
			return;
		}
		System.Collections.Generic.HashSet<string> hashSet = new System.Collections.Generic.HashSet<string>(desired, System.StringComparer.Ordinal);
		if (hashSet.Count == 0)
		{
			if (states.TryGetValue(((UnityEngine.Object)actor).GetInstanceID(), out State value))
			{
				Dispose(value);
			}
			return;
		}
		foreach (string item in hashSet)
		{
			Add(actor, item);
		}
		if (states.TryGetValue(((UnityEngine.Object)actor).GetInstanceID(), out State value2))
		{
			value2.Ids.IntersectWith(hashSet);
		}
	}

	private static void BindAvatar(State state, Il2CppScheduleOne.AvatarFramework.Avatar? avatar)
	{
		if (!((UnityEngine.Object)(object)avatar == (UnityEngine.Object)null))
		{
			int instanceID = ((UnityEngine.Object)avatar).GetInstanceID();
			if (!state.Skins.ContainsKey(instanceID))
			{
				state.Skins[instanceID] = new AvatarSkinLease(avatar);
			}
		}
	}

	internal static void Tick()
	{
		State[] array = states.Values.ToArray();
		foreach (State state in array)
		{
			if ((UnityEngine.Object)(object)state.Actor == (UnityEngine.Object)null)
			{
				Dispose(state);
				continue;
			}
			if (ActiveConsumption.IsPlayer(state.Actor, out Il2CppScheduleOne.PlayerScripts.Player player))
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
					if (!state.Permanent.Contains(item2) && state.Since.TryGetValue(item2, out float value3) && UnityEngine.Time.time - value3 >= Settings.EffectDuration)
					{
						state.Ids.Remove(item2);
						state.Since.Remove(item2);
						int instanceID = ((UnityEngine.Object)state.Actor).GetInstanceID();
						if (!expired.TryGetValue(instanceID, out System.Collections.Generic.HashSet<string> value4))
						{
							value4 = (expired[instanceID] = new System.Collections.Generic.HashSet<string>(System.StringComparer.Ordinal));
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
			if (UnityEngine.Time.unscaledTime < state.RetryAt)
			{
				continue;
			}
			try
			{
				AvatarFrame.Frame frame = AvatarFrame.Read(state.Actor);
				BindAvatar(state, frame.Avatar);
				Il2CppScheduleOne.AvatarFramework.Avatar? avatar = frame.Avatar;
				object obj;
				if (avatar == null)
				{
					obj = null;
				}
				else
				{
					Il2CppScheduleOne.AvatarFramework.AvatarEffects effects = avatar.Effects;
					obj = ((effects != null) ? effects.MirrorEffectsTo : null);
				}
				if (obj != null)
				{
					foreach (Il2CppScheduleOne.AvatarFramework.AvatarEffects item in (Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<Il2CppScheduleOne.AvatarFramework.AvatarEffects>)(object)frame.Avatar.Effects.MirrorEffectsTo)
					{
						if ((UnityEngine.Object)(object)item != (UnityEngine.Object)null)
						{
							BindAvatar(state, item.Avatar);
						}
					}
				}
				foreach (AvatarSkinLease value in state.Skins.Values)
				{
					value.Apply(state.Ids, UnityEngine.Time.time);
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
				if ((UnityEngine.Object)(object)state.Details != (UnityEngine.Object)null)
				{
					UnityEngine.Vector3 val;
					if (!(text == "spines"))
					{
						val = frame.Eyes - frame.Up * 0.035f + frame.Forward * 0.1f;
					}
					else
					{
						Il2CppScheduleOne.AvatarFramework.Avatar? avatar2 = frame.Avatar;
						UnityEngine.Vector3? obj2;
						if (avatar2 == null)
						{
							obj2 = null;
						}
						else
						{
							UnityEngine.Transform middleSpine = avatar2.MiddleSpine;
							obj2 = ((middleSpine != null) ? new UnityEngine.Vector3?(middleSpine.position) : null);
						}
						val = ((UnityEngine.Vector3?)obj2) ?? (frame.Eyes - frame.Up * 0.4f);
					}
					UnityEngine.Vector3 position = (UnityEngine.Vector3)val;
					AvatarFrame.Place(state.Details.transform, position, frame.Rotation);
				}
				ApplyNpcSpeed(state);
			}
			catch (System.Exception ex)
			{
				state.RetryAt = UnityEngine.Time.unscaledTime + 2f;
				if (state.Failures++ == 0)
				{
					MelonLoader.MelonLogger.Warning("Effet visuel en attente : " + ex.Message);
				}
			}
		}
		GameplayEffects.Tick();
	}

	private static void ApplyNpcSpeed(State s)
	{
		if ((UnityEngine.Object)(object)((UnityEngine.Component)s.Actor).GetComponent<Il2CppScheduleOne.PlayerScripts.Player>() != (UnityEngine.Object)null)
		{
			return;
		}
		Il2CppScheduleOne.NPCs.NPC component = ((UnityEngine.Component)s.Actor).GetComponent<Il2CppScheduleOne.NPCs.NPC>();
		if ((UnityEngine.Object)(object)component == (UnityEngine.Object)null || !((Il2CppFishNet.Object.NetworkBehaviour)component).IsServerInitialized)
		{
			return;
		}
		if (s.Agent == null)
		{
			s.Agent = ((UnityEngine.Component)s.Actor).GetComponentInChildren<UnityEngine.AI.NavMeshAgent>(true);
		}
		if (!((UnityEngine.Object)(object)s.Agent == (UnityEngine.Object)null) && ((UnityEngine.Behaviour)s.Agent).enabled)
		{
			float speed = Tuning(s.Actor).Speed;
			if (!s.AgentChanged)
			{
				s.BaseSpeed = s.Agent.speed;
				s.LastSpeed = s.BaseSpeed;
				s.AgentChanged = true;
			}
			else if (!UnityEngine.Mathf.Approximately(s.Agent.speed, s.LastSpeed))
			{
				s.BaseSpeed = s.Agent.speed;
			}
			s.Agent.speed = (s.LastSpeed = s.BaseSpeed * speed);
		}
	}

	private static void BuildDetails(State s, AvatarFrame.Frame frame, string kind)
	{
		s.Details = new GameObject("VX_BodyDetail_" + kind);
		s.Details.transform.SetParent(s.Actor, false);
		if (kind == "spines")
		{
			for (int i = 0; i < 3; i++)
			{
				for (int j = -1; j <= 1; j += 2)
				{
					UnityEngine.GameObject obj = RealModels.Lathe(s.Details.transform, "VX_BackSpine", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[3]
					{
						new Vector2(0.009f, 0f),
						new Vector2(0.006f, 0.018f),
						new Vector2(0f, 0.038f)
					}, new Color(0.22f, 0.26f, 0.11f), 10);
					obj.transform.localPosition = new Vector3((float)j * 0.08f, 0.12f - (float)i * 0.09f, -0.13f);
					obj.transform.localRotation = UnityEngine.Quaternion.Euler(-80f, 0f, (float)(-j * 12));
				}
			}
		}
		if (kind == "frost")
		{
			UnityEngine.ParticleSystem val = s.Details.AddComponent<UnityEngine.ParticleSystem>();
			val.Stop(true, (UnityEngine.ParticleSystemStopBehavior)0);
			UnityEngine.ParticleSystem.MainModule main = val.main;
			main.startColor = (new Color(0.75f, 0.9f, 1f, 0.12f));
			main.startLifetime = (0.65f);
			main.startSpeed = (0.07f);
			main.startSize = (0.035f);
			main.maxParticles = 8;
			val.emission.rateOverTime = (3f);
			UnityEngine.ParticleSystem.ShapeModule shape = val.shape;
			shape.shapeType = (UnityEngine.ParticleSystemShapeType)4;
			shape.radius = 0.012f;
			shape.angle = 8f;
			UnityEngine.Material val2 = new Material(UnityEngine.Shader.Find("Sprites/Default"));
			val2.mainTexture = (UnityEngine.Texture)(object)Devil.SmokeTexture;
			((UnityEngine.Renderer)s.Details.GetComponent<UnityEngine.ParticleSystemRenderer>()).sharedMaterial = val2;
			val.Play();
			s.Breath = val;
		}
	}

	private static void ClearDetails(State s)
	{
		if ((UnityEngine.Object)(object)s.Details != (UnityEngine.Object)null)
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
		if (s2.AgentChanged && (UnityEngine.Object)(object)s2.Agent != (UnityEngine.Object)null && UnityEngine.Mathf.Approximately(s2.Agent.speed, s2.LastSpeed))
		{
			s2.Agent.speed = s2.BaseSpeed;
		}
		System.Collections.Generic.KeyValuePair<int, State>[] array = states.Where<System.Collections.Generic.KeyValuePair<int, State>>((System.Collections.Generic.KeyValuePair<int, State> p) => p.Value == s2).ToArray();
		foreach (System.Collections.Generic.KeyValuePair<int, State> keyValuePair in array)
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
