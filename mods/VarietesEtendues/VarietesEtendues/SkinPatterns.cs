using System.Collections.Generic;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using UnityEngine;

namespace VarietesEtendues;

internal static class SkinPatterns
{
	private static readonly Dictionary<int, Texture2D> textures = new Dictionary<int, Texture2D>();

	internal static Texture2D Get(int kind)
	{
		//IL_0026: Unknown result type (might be due to invalid IL or missing references)
		//IL_002c: Expected O, but got Unknown
		//IL_017f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0184: Unknown result type (might be due to invalid IL or missing references)
		//IL_015b: Unknown result type (might be due to invalid IL or missing references)
		//IL_013d: Unknown result type (might be due to invalid IL or missing references)
		if (textures.TryGetValue(kind, out Texture2D value) && (Object)(object)value != (Object)null)
		{
			return value;
		}
		value = new Texture2D(128, 128, (TextureFormat)4, true);
		Color[] array = (Color[])(object)new Color[16384];
		for (int i = 0; i < 128; i++)
		{
			for (int j = 0; j < 128; j++)
			{
				float num = (float)j / 128f;
				float num2 = (float)i / 128f;
				float num6;
				if (kind == 0)
				{
					float num3 = Mathf.Floor(num2 * 12f);
					float num4 = Mathf.Repeat(num * 12f + num3 * 0.5f, 1f) - 0.5f;
					float num5 = Mathf.Repeat(num2 * 12f, 1f);
					num6 = Mathf.Clamp01(1f - Mathf.Abs(Mathf.Sqrt(num4 * num4 + num5 * num5) - 0.48f) * 45f) * 0.14f;
				}
				else
				{
					num6 = Mathf.Pow(Mathf.Max(0f, Mathf.Sin(num * 32f + Mathf.Sin(num2 * 18f) * 1.4f)), 18f) * 0.15f;
				}
				array[i * 128 + j] = (Color)(kind switch
				{
					1 => new Color(0.5f, 0.5f + num6, 0.5f, 1f), 
					0 => new Color(0.5f - num6, 0.5f - num6, 0.5f - num6, 1f), 
					_ => new Color(0.5f, 0.5f, 0.5f + num6, 1f), 
				});
			}
		}
		value.SetPixels(Il2CppStructArray<Color>.op_Implicit(array));
		value.Apply();
		((Texture)value).wrapMode = (TextureWrapMode)0;
		Object.DontDestroyOnLoad((Object)(object)value);
		textures[kind] = value;
		return value;
	}
}
