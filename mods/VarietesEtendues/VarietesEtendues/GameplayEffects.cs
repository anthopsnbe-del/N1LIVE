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

	private static Il2CppScheduleOne.PlayerScripts.PlayerMovement? owner;

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
			try
			{
				int result;
				if (!inputFailed && (UnityEngine.Object)(object)Il2CppScheduleOne.PlayerScripts.Player.Local != (UnityEngine.Object)null && CharacterEffects.Has(((UnityEngine.Component)Il2CppScheduleOne.PlayerScripts.Player.Local).transform, "vx_diable"))
				{
					Il2CppScheduleOne.PlayerScripts.PlayerMovement? obj = owner;
					if (obj != null && obj.CanMove && UnityEngine.Input.GetKey((UnityEngine.KeyCode)308))
					{
						result = ((UnityEngine.Mathf.Abs(UnityEngine.Input.mouseScrollDelta.y) > 0.01f) ? 1 : 0);
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
		Il2CppScheduleOne.PlayerScripts.Player local = Il2CppScheduleOne.PlayerScripts.Player.Local;
		if (!((UnityEngine.Object)(object)local == (UnityEngine.Object)null))
		{
			UnityEngine.Transform transform = ((UnityEngine.Component)local).transform;
			Il2CppScheduleOne.PlayerScripts.PlayerMovement? obj = owner;
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
		Il2CppScheduleOne.PlayerScripts.PlayerMovement val = (((UnityEngine.Object)(object)Il2CppScheduleOne.PlayerScripts.Player.Local != (UnityEngine.Object)null) ? ((UnityEngine.Component)Il2CppScheduleOne.PlayerScripts.Player.Local).GetComponent<Il2CppScheduleOne.PlayerScripts.PlayerMovement>() : null);
		if (val == null)
		{
			val = Il2CppScheduleOne.DevUtilities.PlayerSingleton<Il2CppScheduleOne.PlayerScripts.PlayerMovement>.Instance;
		}
		if ((UnityEngine.Object)(object)owner != (UnityEngine.Object)(object)val)
		{
			RemoveSpeed();
			owner = val;
		}
		EffectTuning effectTuning = LocalTuning();
		if ((UnityEngine.Object)(object)owner != (UnityEngine.Object)null)
		{
			if (UnityEngine.Mathf.Approximately(effectTuning.Speed, 1f))
			{
				Il2CppScheduleOne.Tools.FloatStack.StackEntry val2 = default(Il2CppScheduleOne.Tools.FloatStack.StackEntry);
				if (owner.MoveSpeedMultiplierStack != null && owner.MoveSpeedMultiplierStack.TryGetEntry("VX_666_Movement", ref val2))
				{
					owner.MoveSpeedMultiplierStack.Remove("VX_666_Movement");
				}
				speed = 1f;
			}
			else if (!UnityEngine.Mathf.Approximately(effectTuning.Speed, speed))
			{
				Il2CppScheduleOne.Tools.FloatStack moveSpeedMultiplierStack = owner.MoveSpeedMultiplierStack;
				if (moveSpeedMultiplierStack != null)
				{
					moveSpeedMultiplierStack.Remove("VX_666_Movement");
				}
				if (!UnityEngine.Mathf.Approximately(effectTuning.Speed, 1f))
				{
					Il2CppScheduleOne.Tools.FloatStack moveSpeedMultiplierStack2 = owner.MoveSpeedMultiplierStack;
					if (moveSpeedMultiplierStack2 != null)
					{
						moveSpeedMultiplierStack2.Add(new Il2CppScheduleOne.Tools.FloatStack.StackEntry("VX_666_Movement", effectTuning.Speed, (Il2CppScheduleOne.Tools.FloatStack.EStackMode)2, 100));
					}
				}
				speed = effectTuning.Speed;
			}
			if (!owner.IsGrounded)
			{
				lastAirborne = UnityEngine.Time.unscaledTime;
			}
			else if (!wasGrounded && UnityEngine.Time.unscaledTime - lastAirborne < 0.15f)
			{
				lastLand = UnityEngine.Time.unscaledTime;
			}
			wasGrounded = owner.IsGrounded;
			if (effectTuning.God && owner.CanMove)
			{
				AdjustSpeed();
			}
		}
		string[] array = (((UnityEngine.Object)(object)Il2CppScheduleOne.PlayerScripts.Player.Local != (UnityEngine.Object)null) ? CharacterEffects.Ids(((UnityEngine.Component)Il2CppScheduleOne.PlayerScripts.Player.Local).transform) : System.Array.Empty<string>());
		Il2CppScheduleOne.PlayerScripts.PlayerMovement? obj = owner;
		VisionEffects.Apply((obj != null && obj.CanMove) ? array : System.Array.Empty<string>());
		Il2CppScheduleOne.PlayerScripts.PlayerMovement? obj2 = owner;
		if (obj2 == null || !obj2.CanMove)
		{
			return;
		}
		string text = string.Join(",", array) + ";" + speed + ";" + Il2CppScheduleOne.PlayerScripts.PlayerMovement.WalkSpeed + ";" + Il2CppScheduleOne.PlayerScripts.PlayerMovement.StaticMoveSpeedMultiplier + ";" + Il2CppScheduleOne.PlayerScripts.PlayerMovement.SprintMultiplier;
		if (text != diagnostic)
		{
			diagnostic = text;
			System.Runtime.CompilerServices.DefaultInterpolatedStringHandler defaultInterpolatedStringHandler = new DefaultInterpolatedStringHandler(132, 7);
			defaultInterpolatedStringHandler.AppendLiteral("MOUVEMENT : effets actifs=[");
			defaultInterpolatedStringHandler.AppendFormatted(string.Join(",", array));
			defaultInterpolatedStringHandler.AppendLiteral("] ; bonus Comptoir=");
			defaultInterpolatedStringHandler.AppendFormatted(speed, "0.00");
			defaultInterpolatedStringHandler.AppendLiteral(" ; marche native=");
			defaultInterpolatedStringHandler.AppendFormatted(Il2CppScheduleOne.PlayerScripts.PlayerMovement.WalkSpeed, "0.00");
			defaultInterpolatedStringHandler.AppendLiteral(" ; multiplicateur global=");
			defaultInterpolatedStringHandler.AppendFormatted(Il2CppScheduleOne.PlayerScripts.PlayerMovement.StaticMoveSpeedMultiplier, "0.00");
			defaultInterpolatedStringHandler.AppendLiteral(" ; sprint natif=");
			defaultInterpolatedStringHandler.AppendFormatted(Il2CppScheduleOne.PlayerScripts.PlayerMovement.SprintMultiplier, "0.00");
			defaultInterpolatedStringHandler.AppendLiteral(" ; pile=");
			Il2CppScheduleOne.Tools.FloatStack moveSpeedMultiplierStack3 = owner.MoveSpeedMultiplierStack;
			defaultInterpolatedStringHandler.AppendFormatted((moveSpeedMultiplierStack3 != null) ? new float?(moveSpeedMultiplierStack3.Value) : null, "0.00");
			defaultInterpolatedStringHandler.AppendLiteral(" ; produit mémorisé=");
			Il2CppScheduleOne.PlayerScripts.Player local = Il2CppScheduleOne.PlayerScripts.Player.Local;
			object obj3;
			if (local == null)
			{
				obj3 = null;
			}
			else
			{
				Il2CppScheduleOne.Product.ProductItemInstance consumedProduct = local.ConsumedProduct;
				obj3 = ((consumedProduct != null) ? ((Il2CppScheduleOne.Core.Items.Framework.BaseItemInstance)consumedProduct).ID : null);
			}
			if (obj3 == null)
			{
				obj3 = "aucun";
			}
			defaultInterpolatedStringHandler.AppendFormatted((string?)obj3);
			MelonLoader.MelonLogger.Msg(defaultInterpolatedStringHandler.ToStringAndClear());
		}
	}

	private static void RemoveSpeed()
	{
		if ((UnityEngine.Object)(object)owner != (UnityEngine.Object)null)
		{
			Il2CppScheduleOne.Tools.FloatStack moveSpeedMultiplierStack = owner.MoveSpeedMultiplierStack;
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
		if (IsAdjusting)
		{
			DevilSprint = UnityEngine.Mathf.Clamp(DevilSprint + UnityEngine.Mathf.Sign(UnityEngine.Input.mouseScrollDelta.y) * 0.1f, 1f, 2f);
		}
	}

	internal static void DrawControls()
	{
		try
		{
			if (!((UnityEngine.Object)(object)Il2CppScheduleOne.PlayerScripts.Player.Local == (UnityEngine.Object)null) && CharacterEffects.Has(((UnityEngine.Component)Il2CppScheduleOne.PlayerScripts.Player.Local).transform, "vx_diable"))
			{
				Il2CppScheduleOne.PlayerScripts.PlayerMovement? obj = owner;
				if (obj != null && obj.CanMove && UnityEngine.Input.GetKey((UnityEngine.KeyCode)308))
				{
					UnityEngine.GUI.Box(new Rect(20f, (float)(UnityEngine.Screen.height - 155), 405f, 66f), Localization.Text("DIABLE — vitesse de sprint ×", "DEVIL — sprint speed ×", "TEUFEL — Sprinttempo ×", "DIABLO — velocidad de sprint ×") + DevilSprint.ToString("0.0") + "\n" + Localization.Text("Alt + molette : régler de ×1 à ×2", "Alt + wheel: adjust from ×1 to ×2", "Alt + Mausrad: von ×1 bis ×2 einstellen", "Alt + rueda: ajustar de ×1 a ×2"));
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
