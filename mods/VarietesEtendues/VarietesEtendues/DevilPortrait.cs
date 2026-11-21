using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using MelonLoader;
using MelonLoader.Utils;
using S1API.Entities;
using UnityEngine;

namespace VarietesEtendues;

internal static class DevilPortrait
{
	internal static Sprite? Image;

	private static float retry;

	private static int failures;

	internal static void Capture(Lucien lucien)
	{
		//IL_00fc: Unknown result type (might be due to invalid IL or missing references)
		//IL_0102: Expected O, but got Unknown
		//IL_0107: Unknown result type (might be due to invalid IL or missing references)
		//IL_010d: Expected O, but got Unknown
		//IL_0144: Unknown result type (might be due to invalid IL or missing references)
		//IL_0174: Unknown result type (might be due to invalid IL or missing references)
		//IL_017b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0187: Unknown result type (might be due to invalid IL or missing references)
		//IL_0191: Unknown result type (might be due to invalid IL or missing references)
		//IL_0196: Unknown result type (might be due to invalid IL or missing references)
		//IL_019b: Unknown result type (might be due to invalid IL or missing references)
		//IL_01a4: Unknown result type (might be due to invalid IL or missing references)
		//IL_01a8: Unknown result type (might be due to invalid IL or missing references)
		//IL_01b4: Unknown result type (might be due to invalid IL or missing references)
		//IL_01be: Unknown result type (might be due to invalid IL or missing references)
		//IL_01c3: Unknown result type (might be due to invalid IL or missing references)
		//IL_01d4: Unknown result type (might be due to invalid IL or missing references)
		//IL_01d8: Unknown result type (might be due to invalid IL or missing references)
		//IL_021e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0225: Unknown result type (might be due to invalid IL or missing references)
		//IL_022f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0234: Unknown result type (might be due to invalid IL or missing references)
		//IL_024a: Unknown result type (might be due to invalid IL or missing references)
		//IL_0251: Expected O, but got Unknown
		//IL_027c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0283: Expected O, but got Unknown
		//IL_0299: Unknown result type (might be due to invalid IL or missing references)
		//IL_02c2: Unknown result type (might be due to invalid IL or missing references)
		//IL_02d1: Unknown result type (might be due to invalid IL or missing references)
		if ((Object)(object)Image != (Object)null || !((NPC)lucien).IsVisible || Time.unscaledTime < retry || !CharacterEffects.DemonReady(((NPC)lucien).Transform))
		{
			return;
		}
		retry = Time.unscaledTime + 15f;
		AvatarFrame.Frame frame = AvatarFrame.Read(((NPC)lucien).Transform);
		if ((Object)(object)frame.Avatar == (Object)null || (Object)(object)frame.Avatar.Eyes == (Object)null)
		{
			return;
		}
		(GameObject, int)[] array = ((IEnumerable<Renderer>)((Component)((NPC)lucien).Transform).GetComponentsInChildren<Renderer>(true)).Select((Renderer r) => (gameObject: ((Component)r).gameObject, layer: ((Component)r).gameObject.layer)).Distinct().ToArray();
		GameObject val = null;
		GameObject val2 = null;
		RenderTexture val3 = null;
		RenderTexture active = RenderTexture.active;
		try
		{
			int num = LayerMask.NameToLayer("IconGeneration");
			if (num >= 0)
			{
				(GameObject, int)[] array2 = array;
				for (int i = 0; i < array2.Length; i++)
				{
					array2[i].Item1.layer = num;
				}
				val = new GameObject("VX_LucienPortraitCamera");
				val2 = new GameObject("VX_LucienPortraitLight");
				Camera val4 = val.AddComponent<Camera>();
				((Behaviour)val4).enabled = false;
				val4.cullingMask = 1 << num;
				val4.clearFlags = (CameraClearFlags)2;
				val4.backgroundColor = new Color(0.08f, 0.025f, 0.04f);
				val4.fieldOfView = 32f;
				val4.nearClipPlane = 0.01f;
				val4.farClipPlane = 4f;
				Vector3 val5 = frame.Eyes + frame.Up * frame.Size * 0.12f;
				((Component)val4).transform.position = val5 + frame.Forward * frame.Size * 3.4f;
				((Component)val4).transform.LookAt(val5, frame.Up);
				Light obj = val2.AddComponent<Light>();
				obj.type = (LightType)2;
				obj.range = 4f;
				obj.intensity = 1.7f;
				obj.cullingMask = 1 << num;
				((Component)obj).transform.position = ((Component)val4).transform.position + frame.Up * 0.25f;
				val3 = new RenderTexture(384, 384, 24);
				val3.Create();
				val4.targetTexture = val3;
				val4.Render();
				RenderTexture.active = val3;
				Texture2D val6 = new Texture2D(384, 384, (TextureFormat)4, false);
				val6.ReadPixels(new Rect(0f, 0f, 384f, 384f), 0, 0);
				val6.Apply();
				Image = Sprite.Create(val6, new Rect(0f, 0f, 384f, 384f), new Vector2(0.5f, 0.5f));
				Object.DontDestroyOnLoad((Object)(object)val6);
				Object.DontDestroyOnLoad((Object)(object)Image);
				((NPC)lucien).Icon = Image;
				string text = Path.Combine(MelonEnvironment.UserDataDirectory, "VarietesEtendues", "Apercus");
				Directory.CreateDirectory(text);
				File.WriteAllBytes(Path.Combine(text, "lucien_666.png"), ((Il2CppArrayBase<byte>)(object)ImageConversion.EncodeToPNG(val6)));
				if ((Object)(object)SupplierUI.App != (Object)null)
				{
					SupplierUI.Refresh(SupplierUI.App);
				}
				MelonLogger.Msg("Portrait 666 pris sur Lucien, sans cloner son squelette.");
			}
		}
		catch (Exception ex)
		{
			if (++failures <= 3)
			{
				MelonLogger.Warning("Portrait de Lucien en attente : " + ex.Message);
			}
		}
		finally
		{
			RenderTexture.active = active;
			(GameObject, int)[] array3 = array;
			for (int j = 0; j < array3.Length; j++)
			{
				(GameObject, int) tuple = array3[j];
				if ((Object)(object)tuple.Item1 != (Object)null)
				{
					tuple.Item1.layer = tuple.Item2;
				}
			}
			if ((Object)(object)val != (Object)null)
			{
				Object.Destroy((Object)(object)val);
			}
			if ((Object)(object)val2 != (Object)null)
			{
				Object.Destroy((Object)(object)val2);
			}
			if ((Object)(object)val3 != (Object)null)
			{
				val3.Release();
				Object.Destroy((Object)(object)val3);
			}
		}
	}
}
