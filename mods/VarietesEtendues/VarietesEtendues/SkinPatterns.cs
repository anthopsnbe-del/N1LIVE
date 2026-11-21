using System.Collections.Generic;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using UnityEngine;

namespace VarietesEtendues;

internal static class SkinPatterns
{
	private static readonly System.Collections.Generic.Dictionary<int, UnityEngine.Texture2D> textures = new System.Collections.Generic.Dictionary<int, UnityEngine.Texture2D>();

	internal static UnityEngine.Texture2D Get(int kind)
	{
		if (textures.TryGetValue(kind, out UnityEngine.Texture2D value) && (UnityEngine.Object)(object)value != (UnityEngine.Object)null)
		{
			return value;
		}
		value = new Texture2D(128, 128, (UnityEngine.TextureFormat)4, true);
		UnityEngine.Color[] array = (UnityEngine.Color[])(object)new UnityEngine.Color[16384];
		for (int i = 0; i < 128; i++)
		{
			for (int j = 0; j < 128; j++)
			{
				float num = (float)j / 128f;
				float num2 = (float)i / 128f;
				float num6;
				if (kind == 0)
				{
					float num3 = UnityEngine.Mathf.Floor(num2 * 12f);
					float num4 = UnityEngine.Mathf.Repeat(num * 12f + num3 * 0.5f, 1f) - 0.5f;
					float num5 = UnityEngine.Mathf.Repeat(num2 * 12f, 1f);
					num6 = UnityEngine.Mathf.Clamp01(1f - UnityEngine.Mathf.Abs(UnityEngine.Mathf.Sqrt(num4 * num4 + num5 * num5) - 0.48f) * 45f) * 0.14f;
				}
				else
				{
					num6 = UnityEngine.Mathf.Pow(UnityEngine.Mathf.Max(0f, UnityEngine.Mathf.Sin(num * 32f + UnityEngine.Mathf.Sin(num2 * 18f) * 1.4f)), 18f) * 0.15f;
				}
				array[i * 128 + j] = (UnityEngine.Color)(kind switch
				{
					1 => new Color(0.5f, 0.5f + num6, 0.5f, 1f), 
					0 => new Color(0.5f - num6, 0.5f - num6, 0.5f - num6, 1f), 
					_ => new Color(0.5f, 0.5f, 0.5f + num6, 1f), 
				});
			}
		}
		value.SetPixels((array));
		value.Apply();
		((UnityEngine.Texture)value).wrapMode = (UnityEngine.TextureWrapMode)0;
		UnityEngine.Object.DontDestroyOnLoad((UnityEngine.Object)(object)value);
		textures[kind] = value;
		return value;
	}
}
