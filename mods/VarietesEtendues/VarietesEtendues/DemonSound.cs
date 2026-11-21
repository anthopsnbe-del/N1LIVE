using System;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using UnityEngine;

namespace VarietesEtendues;

internal static class DemonSound
{
	private static AudioClip? clip;

	internal static AudioClip Clip
	{
		get
		{
			if ((Object)(object)clip != (Object)null)
			{
				return clip;
			}
			float[] array = DemonWave.Generate(22050, 2.4f);
			clip = AudioClip.Create("VX_DemonGrowl", array.Length, 1, 22050, false);
			if (!clip.SetData(new Il2CppStructArray<float>(array), 0))
			{
				throw new InvalidOperationException("Données audio refusées");
			}
			Object.DontDestroyOnLoad((Object)(object)clip);
			return clip;
		}
	}
}
