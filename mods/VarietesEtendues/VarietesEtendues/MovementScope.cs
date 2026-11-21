using System;
using Il2CppScheduleOne.PlayerScripts;
using UnityEngine;

namespace VarietesEtendues;

internal static class MovementScope
{
	[ThreadStatic]
	private static int depth;

	private static float jump;

	private static float gravity;

	private static float drain;

	private static float regen;

	private static float crouch;

	private static float acceleration;

	internal static bool Enter(Il2CppScheduleOne.PlayerScripts.PlayerMovement movement)
	{
		if ((UnityEngine.Object)(object)movement == (UnityEngine.Object)null || (UnityEngine.Object)(object)movement.Player == (UnityEngine.Object)null || !movement.Player.IsLocalPlayer)
		{
			return false;
		}
		EffectTuning effectTuning = CharacterEffects.Tuning(((UnityEngine.Component)movement.Player).transform, movement.IsSprinting);
		if (depth == 0 && effectTuning == EffectTuning.Neutral)
		{
			return false;
		}
		if (depth++ > 0)
		{
			return true;
		}
		jump = Il2CppScheduleOne.PlayerScripts.PlayerMovement.JumpMultiplier;
		gravity = Il2CppScheduleOne.PlayerScripts.PlayerMovement.GravityMultiplier;
		drain = Il2CppScheduleOne.PlayerScripts.PlayerMovement.StaminaDrainRate;
		regen = Il2CppScheduleOne.PlayerScripts.PlayerMovement.StaminaRestoreRate;
		crouch = Il2CppScheduleOne.PlayerScripts.PlayerMovement.CrouchSpeedMultipler;
		acceleration = Il2CppScheduleOne.PlayerScripts.PlayerMovement.SprintChangeRate;
		Il2CppScheduleOne.PlayerScripts.PlayerMovement.JumpMultiplier = jump * effectTuning.Jump;
		Il2CppScheduleOne.PlayerScripts.PlayerMovement.GravityMultiplier = gravity * effectTuning.Gravity;
		Il2CppScheduleOne.PlayerScripts.PlayerMovement.StaminaDrainRate = drain * effectTuning.Drain;
		Il2CppScheduleOne.PlayerScripts.PlayerMovement.StaminaRestoreRate = regen * effectTuning.Regen;
		Il2CppScheduleOne.PlayerScripts.PlayerMovement.CrouchSpeedMultipler = crouch * effectTuning.Crouch;
		Il2CppScheduleOne.PlayerScripts.PlayerMovement.SprintChangeRate = acceleration * effectTuning.Acceleration;
		return true;
	}

	internal static void Leave(bool entered)
	{
		if (entered && --depth <= 0)
		{
			Il2CppScheduleOne.PlayerScripts.PlayerMovement.JumpMultiplier = jump;
			Il2CppScheduleOne.PlayerScripts.PlayerMovement.GravityMultiplier = gravity;
			Il2CppScheduleOne.PlayerScripts.PlayerMovement.StaminaDrainRate = drain;
			Il2CppScheduleOne.PlayerScripts.PlayerMovement.StaminaRestoreRate = regen;
			Il2CppScheduleOne.PlayerScripts.PlayerMovement.CrouchSpeedMultipler = crouch;
			Il2CppScheduleOne.PlayerScripts.PlayerMovement.SprintChangeRate = acceleration;
		}
	}
}
