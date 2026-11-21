using System;
using System.Runtime.CompilerServices;
using Il2CppScheduleOne.Core.Items.Framework;
using Il2CppScheduleOne.DevUtilities;
using Il2CppScheduleOne.PlayerScripts;
using Il2CppScheduleOne.Product;
using Il2CppScheduleOne.Tools;
using MelonLoader;
using UnityEngine;

namespace VarietesEtendues;

internal static class GameplayEffects
{
	internal static float DevilSprint = 1.3f;

	private static bool devilSprintLoaded;

	private static PlayerMovement? owner;

	private static float speed = 1f;

	private const string Label = "VX_666_Movement";

	private static float lastAirborne = -100f;

	private static float lastLand = -100f;

	private static bool wasGrounded = true;

	private static bool inputFailed;

	private static string diagnostic = "";

	internal static bool LandingWindow => Time.unscaledTime - lastLand < 0.12f;

	internal static bool IsAdjusting
	{
		get
		{
			//IL_0049: Unknown result type (might be due to invalid IL or missing references)
			try
			{
				int result;
				if (!inputFailed && (Object)(object)Player.Local != (Object)null && CharacterEffects.Has(((Component)Player.Local).transform, "vx_diable"))
				{
					PlayerMovement? obj = owner;
					if (obj != null && obj.CanMove && Input.GetKey((KeyCode)308))
					{
						result = ((Mathf.Abs(Input.mouseScrollDelta.y) > 0.01f) ? 1 : 0);
						goto IL_0062;
					}
				}
				result = 0;
				goto IL_0062;
				IL_0062:
				return (byte)result != 0;
			}
			catch
			{
				inputFailed = true;
				return false;
			}
		}
	}

	internal static EffectTuning LocalTuning()
	{
		Player local = Player.Local;
		if (!((Object)(object)local == (Object)null))
		{
			Transform transform = ((Component)local).transform;
			PlayerMovement? obj = owner;
			return CharacterEffects.Tuning(transform, obj != null && obj.IsSprinting);
		}
		return EffectTuning.Neutral;
	}

	internal static void Tick()
	{
		if (!devilSprintLoaded)
		{
			devilSprintLoaded = true;
			DevilSprint = Settings.DevilSprint;
		}
		//IL_0108: Unknown result type (might be due to invalid IL or missing references)
		//IL_0112: Expected O, but got Unknown
		PlayerMovement val = (((Object)(object)Player.Local != (Object)null) ? ((Component)Player.Local).GetComponent<PlayerMovement>() : null);
		if (val == null)
		{
			val = PlayerSingleton<PlayerMovement>.Instance;
		}
		if ((Object)(object)owner != (Object)(object)val)
		{
			RemoveSpeed();
			owner = val;
		}
		EffectTuning effectTuning = LocalTuning();
		if ((Object)(object)owner != (Object)null)
		{
			if (Mathf.Approximately(effectTuning.Speed, 1f))
			{
				StackEntry val2 = default(StackEntry);
				if (owner.MoveSpeedMultiplierStack != null && owner.MoveSpeedMultiplierStack.TryGetEntry("VX_666_Movement", ref val2))
				{
					owner.MoveSpeedMultiplierStack.Remove("VX_666_Movement");
				}
				speed = 1f;
			}
			else if (!Mathf.Approximately(effectTuning.Speed, speed))
			{
				FloatStack moveSpeedMultiplierStack = owner.MoveSpeedMultiplierStack;
				if (moveSpeedMultiplierStack != null)
				{
					moveSpeedMultiplierStack.Remove("VX_666_Movement");
				}
				if (!Mathf.Approximately(effectTuning.Speed, 1f))
				{
					FloatStack moveSpeedMultiplierStack2 = owner.MoveSpeedMultiplierStack;
					if (moveSpeedMultiplierStack2 != null)
					{
						moveSpeedMultiplierStack2.Add(new StackEntry("VX_666_Movement", effectTuning.Speed, (EStackMode)2, 100));
					}
				}
				speed = effectTuning.Speed;
			}
			if (!owner.IsGrounded)
			{
				lastAirborne = Time.unscaledTime;
			}
			else if (!wasGrounded && Time.unscaledTime - lastAirborne < 0.15f)
			{
				lastLand = Time.unscaledTime;
			}
			wasGrounded = owner.IsGrounded;
			if (effectTuning.God && owner.CanMove)
			{
				AdjustSpeed();
			}
		}
		string[] array = (((Object)(object)Player.Local != (Object)null) ? CharacterEffects.Ids(((Component)Player.Local).transform) : Array.Empty<string>());
		PlayerMovement? obj = owner;
		VisionEffects.Apply((obj != null && obj.CanMove) ? array : Array.Empty<string>());
		PlayerMovement? obj2 = owner;
		if (obj2 == null || !obj2.CanMove)
		{
			return;
		}
		string text = string.Join(",", array) + ";" + speed + ";" + PlayerMovement.WalkSpeed + ";" + PlayerMovement.StaticMoveSpeedMultiplier + ";" + PlayerMovement.SprintMultiplier;
		if (text != diagnostic)
		{
			diagnostic = text;
			DefaultInterpolatedStringHandler defaultInterpolatedStringHandler = new DefaultInterpolatedStringHandler(132, 7);
			defaultInterpolatedStringHandler.AppendLiteral("MOUVEMENT : effets actifs=[");
			defaultInterpolatedStringHandler.AppendFormatted(string.Join(",", array));
			defaultInterpolatedStringHandler.AppendLiteral("] ; bonus Comptoir=");
			defaultInterpolatedStringHandler.AppendFormatted(speed, "0.00");
			defaultInterpolatedStringHandler.AppendLiteral(" ; marche native=");
			defaultInterpolatedStringHandler.AppendFormatted(PlayerMovement.WalkSpeed, "0.00");
			defaultInterpolatedStringHandler.AppendLiteral(" ; multiplicateur global=");
			defaultInterpolatedStringHandler.AppendFormatted(PlayerMovement.StaticMoveSpeedMultiplier, "0.00");
			defaultInterpolatedStringHandler.AppendLiteral(" ; sprint natif=");
			defaultInterpolatedStringHandler.AppendFormatted(PlayerMovement.SprintMultiplier, "0.00");
			defaultInterpolatedStringHandler.AppendLiteral(" ; pile=");
			FloatStack moveSpeedMultiplierStack3 = owner.MoveSpeedMultiplierStack;
			defaultInterpolatedStringHandler.AppendFormatted((moveSpeedMultiplierStack3 != null) ? new float?(moveSpeedMultiplierStack3.Value) : null, "0.00");
			defaultInterpolatedStringHandler.AppendLiteral(" ; produit mémorisé=");
			Player local = Player.Local;
			object obj3;
			if (local == null)
			{
				obj3 = null;
			}
			else
			{
				ProductItemInstance consumedProduct = local.ConsumedProduct;
				obj3 = ((consumedProduct != null) ? ((BaseItemInstance)consumedProduct).ID : null);
			}
			if (obj3 == null)
			{
				obj3 = "aucun";
			}
			defaultInterpolatedStringHandler.AppendFormatted((string?)obj3);
			MelonLogger.Msg(defaultInterpolatedStringHandler.ToStringAndClear());
		}
	}

	private static void RemoveSpeed()
	{
		if ((Object)(object)owner != (Object)null)
		{
			FloatStack moveSpeedMultiplierStack = owner.MoveSpeedMultiplierStack;
			if (moveSpeedMultiplierStack != null)
			{
				moveSpeedMultiplierStack.Remove("VX_666_Movement");
			}
		}
		owner = null;
		speed = 1f;
	}

	private static void AdjustSpeed()
	{
		//IL_000c: Unknown result type (might be due to invalid IL or missing references)
		if (IsAdjusting)
		{
			DevilSprint = Mathf.Clamp(DevilSprint + Mathf.Sign(Input.mouseScrollDelta.y) * 0.1f, 1f, 2f);
		}
	}

	internal static void DrawControls()
	{
		//IL_0062: Unknown result type (might be due to invalid IL or missing references)
		try
		{
			if (!((Object)(object)Player.Local == (Object)null) && CharacterEffects.Has(((Component)Player.Local).transform, "vx_diable"))
			{
				PlayerMovement? obj = owner;
				if (obj != null && obj.CanMove && Input.GetKey((KeyCode)308))
				{
					GUI.Box(new Rect(20f, (float)(Screen.height - 155), 405f, 66f), Localization.Text("DIABLE — vitesse de sprint ×", "DEVIL — sprint speed ×", "TEUFEL — Sprinttempo ×", "DIABLO — velocidad de sprint ×") + DevilSprint.ToString("0.0") + "\n" + Localization.Text("Alt + molette : régler de ×1 à ×2", "Alt + wheel: adjust from ×1 to ×2", "Alt + Mausrad: von ×1 bis ×2 einstellen", "Alt + rueda: ajustar de ×1 a ×2"));
				}
			}
		}
		catch
		{
			inputFailed = true;
		}
	}

	internal static void Clear()
	{
		RemoveSpeed();
		VisionEffects.Clear();
		lastAirborne = (lastLand = -100f);
		diagnostic = "";
	}
}
