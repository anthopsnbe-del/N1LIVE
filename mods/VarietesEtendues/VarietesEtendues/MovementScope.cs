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

	internal static bool Enter(PlayerMovement movement)
	{
		if ((Object)(object)movement == (Object)null || (Object)(object)movement.Player == (Object)null || !movement.Player.IsLocalPlayer)
		{
			return false;
		}
		EffectTuning effectTuning = CharacterEffects.Tuning(((Component)movement.Player).transform, movement.IsSprinting);
		if (depth == 0 && effectTuning == EffectTuning.Neutral)
		{
			return false;
		}
		if (depth++ > 0)
		{
			return true;
		}
		jump = PlayerMovement.JumpMultiplier;
		gravity = PlayerMovement.GravityMultiplier;
		drain = PlayerMovement.StaminaDrainRate;
		regen = PlayerMovement.StaminaRestoreRate;
		crouch = PlayerMovement.CrouchSpeedMultipler;
		acceleration = PlayerMovement.SprintChangeRate;
		PlayerMovement.JumpMultiplier = jump * effectTuning.Jump;
		PlayerMovement.GravityMultiplier = gravity * effectTuning.Gravity;
		PlayerMovement.StaminaDrainRate = drain * effectTuning.Drain;
		PlayerMovement.StaminaRestoreRate = regen * effectTuning.Regen;
		PlayerMovement.CrouchSpeedMultipler = crouch * effectTuning.Crouch;
		PlayerMovement.SprintChangeRate = acceleration * effectTuning.Acceleration;
		return true;
	}

	internal static void Leave(bool entered)
	{
		if (entered && --depth <= 0)
		{
			PlayerMovement.JumpMultiplier = jump;
			PlayerMovement.GravityMultiplier = gravity;
			PlayerMovement.StaminaDrainRate = drain;
			PlayerMovement.StaminaRestoreRate = regen;
			PlayerMovement.CrouchSpeedMultipler = crouch;
			PlayerMovement.SprintChangeRate = acceleration;
		}
	}
}
