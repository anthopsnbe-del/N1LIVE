using System;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using UnityEngine;

namespace VarietesEtendues;

internal static class DemonSound
{
	private static UnityEngine.AudioClip? clip;

	internal static UnityEngine.AudioClip Clip
	{
		get
		{
			if ((UnityEngine.Object)(object)clip != (UnityEngine.Object)null)
			{
				return clip;
			}
			float[] array = DemonWave.Generate(22050, 2.4f);
			clip = UnityEngine.AudioClip.Create("VX_DemonGrowl", array.Length, 1, 22050, false);
			if (!clip.SetData(new Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppStructArray<float>(array), 0))
			{
				throw new InvalidOperationException("Données audio refusées");
			}
			UnityEngine.Object.DontDestroyOnLoad((UnityEngine.Object)(object)clip);
			return clip;
		}
	}
}
