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
	internal static UnityEngine.Sprite? Image;

	private static float retry;

	private static int failures;

	internal static void Capture(Lucien lucien)
	{
		if ((UnityEngine.Object)(object)Image != (UnityEngine.Object)null || !((S1API.Entities.NPC)lucien).IsVisible || UnityEngine.Time.unscaledTime < retry || !CharacterEffects.DemonReady(((S1API.Entities.NPC)lucien).Transform))
		{
			return;
		}
		retry = UnityEngine.Time.unscaledTime + 15f;
		AvatarFrame.Frame frame = AvatarFrame.Read(((S1API.Entities.NPC)lucien).Transform);
		if ((UnityEngine.Object)(object)frame.Avatar == (UnityEngine.Object)null || (UnityEngine.Object)(object)frame.Avatar.Eyes == (UnityEngine.Object)null)
		{
			return;
		}
		(UnityEngine.GameObject, int)[] array = ((System.Collections.Generic.IEnumerable<UnityEngine.Renderer>)((UnityEngine.Component)((S1API.Entities.NPC)lucien).Transform).GetComponentsInChildren<UnityEngine.Renderer>(true)).Select((UnityEngine.Renderer r) => (gameObject: ((UnityEngine.Component)r).gameObject, layer: ((UnityEngine.Component)r).gameObject.layer)).Distinct().ToArray();
		UnityEngine.GameObject val = null;
		UnityEngine.GameObject val2 = null;
		UnityEngine.RenderTexture val3 = null;
		UnityEngine.RenderTexture active = UnityEngine.RenderTexture.active;
		try
		{
			int num = UnityEngine.LayerMask.NameToLayer("IconGeneration");
			if (num >= 0)
			{
				(UnityEngine.GameObject, int)[] array2 = array;
				for (int i = 0; i < array2.Length; i++)
				{
					array2[i].Item1.layer = num;
				}
				val = new GameObject("VX_LucienPortraitCamera");
				val2 = new GameObject("VX_LucienPortraitLight");
				UnityEngine.Camera val4 = val.AddComponent<UnityEngine.Camera>();
				((UnityEngine.Behaviour)val4).enabled = false;
				val4.cullingMask = 1 << num;
				val4.clearFlags = (UnityEngine.CameraClearFlags)2;
				val4.backgroundColor = new Color(0.08f, 0.025f, 0.04f);
				val4.fieldOfView = 32f;
				val4.nearClipPlane = 0.01f;
				val4.farClipPlane = 4f;
				UnityEngine.Vector3 val5 = frame.Eyes + frame.Up * frame.Size * 0.12f;
				((UnityEngine.Component)val4).transform.position = val5 + frame.Forward * frame.Size * 3.4f;
				((UnityEngine.Component)val4).transform.LookAt(val5, frame.Up);
				UnityEngine.Light obj = val2.AddComponent<UnityEngine.Light>();
				obj.type = (UnityEngine.LightType)2;
				obj.range = 4f;
				obj.intensity = 1.7f;
				obj.cullingMask = 1 << num;
				((UnityEngine.Component)obj).transform.position = ((UnityEngine.Component)val4).transform.position + frame.Up * 0.25f;
				val3 = new RenderTexture(384, 384, 24);
				val3.Create();
				val4.targetTexture = val3;
				val4.Render();
				UnityEngine.RenderTexture.active = val3;
				UnityEngine.Texture2D val6 = new Texture2D(384, 384, (UnityEngine.TextureFormat)4, false);
				val6.ReadPixels(new Rect(0f, 0f, 384f, 384f), 0, 0);
				val6.Apply();
				Image = UnityEngine.Sprite.Create(val6, new Rect(0f, 0f, 384f, 384f), new Vector2(0.5f, 0.5f));
				UnityEngine.Object.DontDestroyOnLoad((UnityEngine.Object)(object)val6);
				UnityEngine.Object.DontDestroyOnLoad((UnityEngine.Object)(object)Image);
				((S1API.Entities.NPC)lucien).Icon = Image;
				string text = System.IO.Path.Combine(MelonLoader.Utils.MelonEnvironment.UserDataDirectory, "VarietesEtendues", "Apercus");
				System.IO.Directory.CreateDirectory(text);
				System.IO.File.WriteAllBytes(System.IO.Path.Combine(text, "lucien_666.png"), ((Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<byte>)(object)UnityEngine.ImageConversion.EncodeToPNG(val6)));
				if ((UnityEngine.Object)(object)SupplierUI.App != (UnityEngine.Object)null)
				{
					SupplierUI.Refresh(SupplierUI.App);
				}
				MelonLoader.MelonLogger.Msg("Portrait 666 pris sur Lucien, sans cloner son squelette.");
			}
		}
		catch (System.Exception ex)
		{
			if (++failures <= 3)
			{
				MelonLoader.MelonLogger.Warning("Portrait de Lucien en attente : " + ex.Message);
			}
		}
		finally
		{
			UnityEngine.RenderTexture.active = active;
			(UnityEngine.GameObject, int)[] array3 = array;
			for (int j = 0; j < array3.Length; j++)
			{
				(UnityEngine.GameObject, int) tuple = array3[j];
				if ((UnityEngine.Object)(object)tuple.Item1 != (UnityEngine.Object)null)
				{
					tuple.Item1.layer = tuple.Item2;
				}
			}
			if ((UnityEngine.Object)(object)val != (UnityEngine.Object)null)
			{
				UnityEngine.Object.Destroy((UnityEngine.Object)(object)val);
			}
			if ((UnityEngine.Object)(object)val2 != (UnityEngine.Object)null)
			{
				UnityEngine.Object.Destroy((UnityEngine.Object)(object)val2);
			}
			if ((UnityEngine.Object)(object)val3 != (UnityEngine.Object)null)
			{
				val3.Release();
				UnityEngine.Object.Destroy((UnityEngine.Object)(object)val3);
			}
		}
	}
}
