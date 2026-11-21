using System;
using System.Collections.Generic;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using UnityEngine;

namespace VarietesEtendues;

internal static class RealModels
{
	private static readonly UnityEngine.Color Cream = new Color(0.94f, 0.9f, 0.77f);

	private static readonly UnityEngine.Color Green = new Color(0.19f, 0.42f, 0.055f);

	private static readonly UnityEngine.Color Red = new Color(0.55f, 0.012f, 0.025f);

	private static readonly System.Collections.Generic.Dictionary<string, UnityEngine.Texture2D> skins = new System.Collections.Generic.Dictionary<string, UnityEngine.Texture2D>();

	private static void Ring(UnityEngine.Transform parent, string name, float radius, float y, float thickness, UnityEngine.Color color)
	{
		Lathe(parent, name, (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[5]
		{
			new Vector2(radius - thickness, y - thickness),
			new Vector2(radius, y - thickness),
			new Vector2(radius + thickness, y),
			new Vector2(radius, y + thickness),
			new Vector2(radius - thickness, y + thickness)
		}, color, 48);
	}

	private static void Finish(UnityEngine.GameObject g, float smoothness, float metal = 0f)
	{
		foreach (UnityEngine.Renderer componentsInChild in g.GetComponentsInChildren<UnityEngine.Renderer>(true))
		{
			componentsInChild.material.SetFloat("_Smoothness", smoothness);
			componentsInChild.material.SetFloat("_Glossiness", smoothness);
			componentsInChild.material.SetFloat("_Metallic", metal);
		}
	}

	internal static UnityEngine.GameObject Lathe(UnityEngine.Transform parent, string name, UnityEngine.Vector2[] profile, UnityEngine.Color color, int sides = 40)
	{
		System.Collections.Generic.List<UnityEngine.Vector3> list = new System.Collections.Generic.List<UnityEngine.Vector3>();
		System.Collections.Generic.List<UnityEngine.Vector2> list2 = new System.Collections.Generic.List<UnityEngine.Vector2>();
		System.Collections.Generic.List<int> list3 = new System.Collections.Generic.List<int>();
		for (int i = 0; i < profile.Length; i++)
		{
			for (int j = 0; j <= sides; j++)
			{
				float num = (float)j * (float)System.Math.PI * 2f / (float)sides;
				list.Add(new Vector3(UnityEngine.Mathf.Cos(num) * profile[i].x, profile[i].y, UnityEngine.Mathf.Sin(num) * profile[i].x));
				list2.Add(new Vector2((float)j / (float)sides, (float)i / (float)(profile.Length - 1)));
			}
		}
		for (int k = 0; k < profile.Length - 1; k++)
		{
			for (int l = 0; l < sides; l++)
			{
				int num2 = k * (sides + 1) + l;
				int num3 = num2 + sides + 1;
				list3.AddRange(new int[6]
				{
					num2,
					num3,
					num2 + 1,
					num2 + 1,
					num3,
					num3 + 1
				});
			}
		}
		return MeshObject(parent, name, list, list3, color, list2);
	}

	private static UnityEngine.GameObject MeshObject(UnityEngine.Transform p, string name, System.Collections.Generic.List<UnityEngine.Vector3> v, System.Collections.Generic.List<int> t, UnityEngine.Color c, System.Collections.Generic.List<UnityEngine.Vector2>? uv = null)
	{
		UnityEngine.GameObject val = new GameObject(name);
		val.transform.SetParent(p, false);
		UnityEngine.Mesh val2 = new Mesh
		{
			name = "VX_Procedural_" + name,
			vertices = (v.ToArray()),
			triangles = (t.ToArray())
		};
		if (uv != null)
		{
			val2.uv = (uv.ToArray());
		}
		val2.RecalculateNormals();
		val2.RecalculateBounds();
		val.AddComponent<UnityEngine.MeshFilter>().mesh = val2;
		UnityEngine.Material val3 = Models.Material(c);
		val3.SetInt("_Cull", 0);
		((UnityEngine.Renderer)val.AddComponent<UnityEngine.MeshRenderer>()).material = val3;
		return val;
	}

	private static UnityEngine.GameObject Fruit(UnityEngine.Transform p, string key, UnityEngine.Color color, UnityEngine.Vector3 scale, UnityEngine.Vector3 pos)
	{
		System.Collections.Generic.List<UnityEngine.Vector3> list = new System.Collections.Generic.List<UnityEngine.Vector3>();
		System.Collections.Generic.List<int> list2 = new System.Collections.Generic.List<int>();
		System.Collections.Generic.List<UnityEngine.Vector2> list3 = new System.Collections.Generic.List<UnityEngine.Vector2>();
		for (int i = 0; i <= 24; i++)
		{
			for (int j = 0; j <= 40; j++)
			{
				float num = (float)j * (float)System.Math.PI * 2f / 40f;
				float num2 = (float)i * (float)System.Math.PI / 24f;
				float num3 = UnityEngine.Mathf.Cos(num2);
				float num4 = UnityEngine.Mathf.Sin(num2);
				float num5 = ((key == "prune") ? (1f + 0.07f * UnityEngine.Mathf.Sin(num * 9f + num2 * 7f) * UnityEngine.Mathf.Sin(num * 5f - num2 * 8f)) : 1f);
				float num6 = UnityEngine.Mathf.Cos(num) * num4 * num5;
				float num7 = UnityEngine.Mathf.Sin(num) * num4 * num5;
				if (key == "mango")
				{
					num6 *= 1f - 0.18f * num3;
					num6 += 0.18f * num3 * num3;
					num7 *= 1f - 0.11f * num3;
				}
				if (key == "lemon")
				{
					num4 = UnityEngine.Mathf.Max(0f, num4) * (1f - 0.18f * UnityEngine.Mathf.Pow(UnityEngine.Mathf.Abs(num3), 6f));
					num6 = UnityEngine.Mathf.Cos(num) * num4;
					num7 = UnityEngine.Mathf.Sin(num) * num4;
					num3 *= 1f + 0.12f * UnityEngine.Mathf.Pow(UnityEngine.Mathf.Abs(num3), 8f);
				}
				list.Add(pos + UnityEngine.Vector3.Scale(new Vector3(num6, num3, num7), scale));
				list3.Add(new Vector2((float)j / 40f, (float)i / 24f));
			}
		}
		for (int k = 0; k < 24; k++)
		{
			for (int l = 0; l < 40; l++)
			{
				int num8 = k * 41 + l;
				int num9 = num8 + 40 + 1;
				list2.AddRange(new int[6]
				{
					num8,
					num8 + 1,
					num9,
					num8 + 1,
					num9 + 1,
					num9
				});
			}
		}
		UnityEngine.GameObject val = MeshObject(p, key, list, list2, color, list3);
		if (!skins.TryGetValue(key, out UnityEngine.Texture2D value))
		{
			value = new Texture2D(256, 128, (UnityEngine.TextureFormat)4, true);
			UnityEngine.Color[] array = (UnityEngine.Color[])(object)new UnityEngine.Color[32768];
			for (int m = 0; m < 128; m++)
			{
				for (int n = 0; n < 256; n++)
				{
					float num10 = (UnityEngine.Mathf.Repeat(UnityEngine.Mathf.Sin((float)n * 12.9898f + (float)m * 78.233f) * 43758.547f, 1f) - 0.5f) * 0.1f;
					UnityEngine.Color val2 = color;
					if (key == "mango")
					{
						float num11 = UnityEngine.Mathf.Clamp01((UnityEngine.Mathf.Cos((float)n / 255f * (float)System.Math.PI * 2f) + 0.25f) * 0.58f + (1f - (float)m / 127f) * 0.35f);
						val2 = UnityEngine.Color.Lerp(new Color(1f, 0.67f, 0.08f), new Color(0.78f, 0.055f, 0.025f), num11);
					}
					if (key == "prune")
					{
						num10 += UnityEngine.Mathf.Sin((float)n * 0.65f + UnityEngine.Mathf.Sin((float)m * 0.4f) * 3f) * 0.035f;
					}
					if (key == "lemon")
					{
						num10 += UnityEngine.Mathf.Sin((float)n * 1.7f + (float)m * 0.8f) * UnityEngine.Mathf.Sin((float)m * 2.1f) * 0.018f;
					}
					if (key == "dragon")
					{
						val2 = UnityEngine.Color.Lerp(val2, new Color(0.98f, 0.18f, 0.43f), UnityEngine.Mathf.Pow(UnityEngine.Mathf.Abs(UnityEngine.Mathf.Sin((float)n * 0.075f + UnityEngine.Mathf.Sin((float)m * 0.09f))), 10f) * 0.5f);
					}
					if (key == "durian")
					{
						val2 = UnityEngine.Color.Lerp(val2, new Color(0.25f, 0.31f, 0.065f), UnityEngine.Mathf.Pow(UnityEngine.Mathf.Abs(UnityEngine.Mathf.Sin((float)n * 0.12f)), 16f) * 0.35f);
					}
					array[m * 256 + n] = new Color(UnityEngine.Mathf.Clamp01(val2.r + num10), UnityEngine.Mathf.Clamp01(val2.g + num10 * 0.8f), UnityEngine.Mathf.Clamp01(val2.b + num10 * 0.55f), 1f);
				}
			}
			value.SetPixels((array));
			value.Apply();
			((UnityEngine.Texture)value).wrapMode = (UnityEngine.TextureWrapMode)0;
			skins[key] = value;
		}
		UnityEngine.Material material = val.GetComponent<UnityEngine.Renderer>().material;
		material.color = UnityEngine.Color.white;
		material.mainTexture = (UnityEngine.Texture)((key == "lemon") ? ((object)Models.Texture("LEMON SKIN")) : ((object)value));
		if (key == "lemon")
		{
			material.mainTexture.wrapMode = (UnityEngine.TextureWrapMode)0;
		}
		Finish(val, (key == "prune") ? 0.26f : 0.3f);
		return val;
	}

	private static void Leaf(UnityEngine.Transform p, UnityEngine.Vector3 origin, UnityEngine.Vector3 direction, float width, UnityEngine.Color color)
	{
		UnityEngine.Vector3 val = UnityEngine.Vector3.Cross(direction.normalized, UnityEngine.Vector3.forward);
		if (val.sqrMagnitude < 0.1f)
		{
			val = UnityEngine.Vector3.right;
		}
		UnityEngine.Vector3 val2 = origin + direction * 0.5f;
		UnityEngine.Vector3 item = origin + direction;
		MeshObject(p, "Feuille", new System.Collections.Generic.List<UnityEngine.Vector3>
		{
			origin,
			val2 + val * width,
			item,
			val2 - val * width,
			val2 + UnityEngine.Vector3.forward * 0.008f
		}, new System.Collections.Generic.List<int>
		{
			0, 4, 1, 1, 4, 2, 2, 4, 3, 3,
			4, 0
		}, color);
	}

	private static void Spike(UnityEngine.Transform p, UnityEngine.Vector3 pos, UnityEngine.Vector3 direction, float radius, float length, UnityEngine.Color c)
	{
		UnityEngine.GameObject obj = Lathe(p, "Pointe", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[3]
		{
			new Vector2(0f, 0f),
			new Vector2(radius, 0.003f),
			new Vector2(0f, length)
		}, c, 8);
		obj.transform.localPosition = pos;
		obj.transform.localRotation = UnityEngine.Quaternion.FromToRotation(UnityEngine.Vector3.up, direction);
	}

	private static void Cap(UnityEngine.Transform p, float y, float radius, UnityEngine.Color c, float height = 0.018f)
	{
		Lathe(p, "Bouchon", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[4]
		{
			new Vector2(0f, y),
			new Vector2(radius, y),
			new Vector2(radius, y + height),
			new Vector2(0f, y + height)
		}, c);
		for (int i = 0; i < 32; i++)
		{
			float num = (float)i * (float)System.Math.PI * 2f / 32f;
			Models.Part(p, (UnityEngine.PrimitiveType)3, new Vector3(UnityEngine.Mathf.Cos(num) * radius, y + height * 0.5f, UnityEngine.Mathf.Sin(num) * radius), new Vector3(0.002f, height, 0.002f), c);
		}
	}

	private static void Label(UnityEngine.Transform p, string text, float y, float z, float size = 0.004f)
	{
		Models.Label(p, text, new Vector3(0f, y, z), size, UnityEngine.Color.black);
	}

	private static void BottleLabel(UnityEngine.Transform p, string text, float y, float radius, float height)
	{
		Lathe(p, "Papier_étiquette", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[2]
		{
			new Vector2(radius + 0.0006f, y - height * 0.5f),
			new Vector2(radius + 0.0006f, y + height * 0.5f)
		}, Cream);
		System.Collections.Generic.List<UnityEngine.Vector3> list = new System.Collections.Generic.List<UnityEngine.Vector3>();
		System.Collections.Generic.List<int> list2 = new System.Collections.Generic.List<int>();
		System.Collections.Generic.List<UnityEngine.Vector2> list3 = new System.Collections.Generic.List<UnityEngine.Vector2>();
		for (int i = 0; i < 2; i++)
		{
			for (int j = 0; j <= 32; j++)
			{
				float num = (float)j / 32f;
				float num2 = (num - 0.5f) * (float)System.Math.PI * 0.94f;
				list.Add(new Vector3(UnityEngine.Mathf.Sin(num2) * (radius + 0.001f), y + ((float)i - 0.5f) * height, (0f - UnityEngine.Mathf.Cos(num2)) * (radius + 0.001f)));
				list3.Add(new Vector2(num, (float)i));
			}
		}
		for (int k = 0; k < 32; k++)
		{
			list2.AddRange(new int[6]
			{
				k,
				k + 32 + 1,
				k + 1,
				k + 1,
				k + 32 + 1,
				k + 32 + 2
			});
		}
		UnityEngine.GameObject obj = MeshObject(p, "Impression_" + text, list, list2, UnityEngine.Color.white, list3);
		UnityEngine.Material val = Models.Material(UnityEngine.Color.white);
		val.mainTexture = (UnityEngine.Texture)(object)Models.Texture(text);
		val.SetFloat("_Smoothness", 0.12f);
		val.SetFloat("_Glossiness", 0.12f);
		obj.GetComponent<UnityEngine.Renderer>().material = val;
	}

	private static void MelonSector(UnityEngine.Transform p, string name, float radius, float inner, float depth, UnityEngine.Color color)
	{
		System.Collections.Generic.List<UnityEngine.Vector3> list = new System.Collections.Generic.List<UnityEngine.Vector3>();
		System.Collections.Generic.List<int> list2 = new System.Collections.Generic.List<int>();
		for (int i = 0; i < 2; i++)
		{
			for (int j = 0; j < 2; j++)
			{
				for (int k = 0; k <= 32; k++)
				{
					float num = -0.68f + (float)k * 1.36f / 32f;
					float num2 = ((j == 0) ? inner : radius);
					list.Add(new Vector3(UnityEngine.Mathf.Sin(num) * num2, 0.195f - UnityEngine.Mathf.Cos(num) * num2, (float)((i != 0) ? 1 : (-1)) * depth * 0.5f));
				}
			}
		}
		int num3 = 33;
		for (int l = 0; l < 32; l++)
		{
			list2.AddRange(new int[12]
			{
				l,
				num3 + l,
				l + 1,
				l + 1,
				num3 + l,
				num3 + l + 1,
				2 * num3 + l,
				2 * num3 + l + 1,
				3 * num3 + l,
				2 * num3 + l + 1,
				3 * num3 + l + 1,
				3 * num3 + l
			});
			list2.AddRange(new int[6]
			{
				num3 + l,
				3 * num3 + l,
				num3 + l + 1,
				num3 + l + 1,
				3 * num3 + l,
				3 * num3 + l + 1
			});
		}
		list2.AddRange(new int[12]
		{
			0,
			2 * num3,
			3 * num3,
			0,
			3 * num3,
			num3,
			32,
			num3 + 32,
			3 * num3 + 32,
			32,
			3 * num3 + 32,
			2 * num3 + 32
		});
		MeshObject(p, name, list, list2, color);
	}

	internal static UnityEngine.GameObject Make(string key, string name)
	{
		UnityEngine.GameObject val = new GameObject("VX_Modele3D_" + key);
		UnityEngine.Transform transform = val.transform;
		if (key.StartsWith("seed_", System.StringComparison.Ordinal))
		{
			Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0f, 0.065f, 0f), new Vector3(0.088f, 0.13f, 0.008f), Cream);
			Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0f, 0.122f, -0.005f), new Vector3(0.087f, 0.012f, 0.001f), new Color(0.75f, 0.7f, 0.58f));
			Models.Label(transform, name.ToUpperInvariant(), new Vector3(0f, 0.062f, -0.0046f), 0.004f, UnityEngine.Color.white, (UnityEngine.Vector2?)new Vector2(0.082f, 0.109f));
		}
		else
		{
			bool flag;
			bool flag2;
			bool flag3;
			bool flag4;
			bool flag5;
			bool flag6;
			float num6;
			float num8;
			float num9;
			UnityEngine.Color val3 = default(UnityEngine.Color);
			UnityEngine.Vector2[] profile;
			float y;
			float radius;
			UnityEngine.Color c;
			float height;
			UnityEngine.Transform p;
			switch (key)
			{
			case "pill666":
			{
				UnityEngine.GameObject val5 = new GameObject("Capsule_bicolore");
				val5.transform.SetParent(transform, false);
				val5.transform.localRotation = UnityEngine.Quaternion.Euler(0f, 0f, 90f);
				val5.transform.localPosition = new Vector3(0.047f, 0.026f, 0f);
				Lathe(val5.transform, "Moitié_rouge", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[5]
				{
					new Vector2(0f, 0f),
					new Vector2(0.008f, 0.003f),
					new Vector2(0.012f, 0.009f),
					new Vector2(0.013f, 0.016f),
					new Vector2(0.013f, 0.047f)
				}, Red);
				Lathe(val5.transform, "Moitié_ivoire", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[5]
				{
					new Vector2(0.0133f, 0.047f),
					new Vector2(0.0133f, 0.078f),
					new Vector2(0.012f, 0.086f),
					new Vector2(0.008f, 0.091f),
					new Vector2(0f, 0.094f)
				}, Cream);
				Models.Label(transform, "666", new Vector3(-0.023f, 0.026f, -0.0136f), 0.005f, UnityEngine.Color.black, (UnityEngine.Vector2?)new Vector2(0.043f, 0.019f));
				Models.Label(transform, "666", new Vector3(-0.023f, 0.026f, 0.0136f), 0.005f, UnityEngine.Color.black, (UnityEngine.Vector2?)new Vector2(0.043f, 0.019f)).transform.localRotation = UnityEngine.Quaternion.Euler(0f, 180f, 0f);
				Models.Label(transform, "666", new Vector3(-0.023f, 0.0395f, 0f), 0.005f, UnityEngine.Color.black, (UnityEngine.Vector2?)new Vector2(0.043f, 0.019f)).transform.localRotation = UnityEngine.Quaternion.Euler(90f, 0f, 0f);
				Finish(val5, 0.58f);
				break;
			}
			case "lemon":
			{
				UnityEngine.GameObject val4 = new GameObject("Citron");
				val4.transform.SetParent(transform, false);
				val4.transform.localPosition = new Vector3(0f, 0.055f, 0f);
				val4.transform.localRotation = UnityEngine.Quaternion.Euler(0f, 0f, 82f);
				Fruit(val4.transform, "lemon", new Color(1f, 0.81f, 0.025f), new Vector3(0.044f, 0.069f, 0.043f), UnityEngine.Vector3.zero);
				Lathe(val4.transform, "Pédoncule", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[2]
				{
					new Vector2(0.004f, 0.074f),
					new Vector2(0.0025f, 0.083f)
				}, new Color(0.29f, 0.3f, 0.07f), 12);
				Leaf(val4.transform, new Vector3(0f, 0.078f, 0f), new Vector3(0.028f, 0.008f, 0.008f), 0.009f, new Color(0.2f, 0.39f, 0.07f));
				break;
			}
			case "mango":
			case "dragon":
			case "durian":
				flag = true;
				goto IL_0503;
			default:
				{
					flag = false;
					goto IL_0503;
				}
				IL_0503:
				if (flag)
				{
					p = transform;
					string key2 = key;
					UnityEngine.Color color = ((key == "dragon") ? new Color(0.82f, 0.035f, 0.32f) : ((key == "lemon") ? new Color(1f, 0.81f, 0.025f) : ((!(key == "mango")) ? new Color(0.47f, 0.49f, 0.12f) : new Color(1f, 0.48f, 0.035f))));
					Fruit(p, key2, color, new Vector3(0.065f, 0.085f, 0.056f), new Vector3(0f, 0.09f, 0f));
					Lathe(transform, "Tige", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[2]
					{
						new Vector2(0.006f, 0.169f),
						new Vector2(0.004f, 0.196f)
					}, new Color(0.25f, 0.12f, 0.025f), 10);
					if (key == "mango" || key == "lemon")
					{
						Leaf(transform, new Vector3(0f, 0.18f, 0f), new Vector3(0.07f, 0.012f, 0.01f), 0.018f, Green);
					}
					if (key == "lemon")
					{
						Fruit(transform, "lemon_tip", new Color(0.83f, 0.66f, 0.025f), new Vector3(0.011f, 0.009f, 0.01f), new Vector3(0f, 0.004f, 0f));
					}
					if (key == "dragon")
					{
						for (int i = 0; i < 18; i++)
						{
							float num = (float)i * 2.4f;
							float num2 = 0.035f + (float)(i % 4) * 0.035f;
							UnityEngine.Vector3 direction = new Vector3(UnityEngine.Mathf.Cos(num), 0.55f, UnityEngine.Mathf.Sin(num)) * 0.045f;
							Leaf(transform, new Vector3(UnityEngine.Mathf.Cos(num) * 0.047f, num2, UnityEngine.Mathf.Sin(num) * 0.045f), direction, 0.009f, new Color(0.36f, 0.62f, 0.08f));
						}
					}
					if (!(key == "durian"))
					{
						break;
					}
					UnityEngine.Vector3 val2 = default(UnityEngine.Vector3);
					for (int j = 1; j < 10; j++)
					{
						for (int k = 0; k < 15; k++)
						{
							float num3 = (float)k * (float)System.Math.PI * 2f / 15f + (float)j * 0.4f;
							float num4 = (float)j * (float)System.Math.PI / 10f;
							val2 = new Vector3(UnityEngine.Mathf.Cos(num3) * UnityEngine.Mathf.Sin(num4), UnityEngine.Mathf.Cos(num4), UnityEngine.Mathf.Sin(num3) * UnityEngine.Mathf.Sin(num4));
							Spike(transform, new Vector3(val2.x * 0.063f, 0.09f + val2.y * 0.084f, val2.z * 0.055f), val2, 0.008f, 0.017f, new Color(0.38f, 0.4f, 0.08f));
						}
					}
					break;
				}
				switch (key)
				{
				case "melon":
				{
					MelonSector(transform, "Peau_courbe", 0.195f, 0.183f, 0.06f, Green);
					MelonSector(transform, "Écorce_claire", 0.183f, 0.174f, 0.057f, new Color(0.85f, 0.94f, 0.69f));
					MelonSector(transform, "Chair_pastèque", 0.174f, 0f, 0.054f, new Color(0.92f, 0.065f, 0.135f));
					for (int num11 = -1; num11 <= 1; num11 += 2)
					{
						for (int num12 = 0; num12 < 9; num12++)
						{
							float num13 = 0.055f + (float)(num12 / 3) * 0.035f;
							float num14 = (float)(num12 % 3 - 1) * (0.07f - num13 * 0.38f);
							Models.Part(transform, (UnityEngine.PrimitiveType)0, new Vector3(num14, num13, (float)num11 * 0.028f), new Vector3(0.007f, 0.012f, 0.002f), new Color(0.045f, 0.018f, 0.012f));
						}
					}
					break;
				}
				case "prune":
				{
					Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0f, 0.012f, 0f), new Vector3(0.16f, 0.02f, 0.11f), new Color(0.58f, 0.42f, 0.23f));
					for (int n = -1; n <= 1; n += 2)
					{
						Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3((float)n * 0.077f, 0.027f, 0f), new Vector3(0.008f, 0.034f, 0.11f), new Color(0.62f, 0.45f, 0.25f));
						Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0f, 0.027f, (float)n * 0.052f), new Vector3(0.16f, 0.034f, 0.008f), new Color(0.62f, 0.45f, 0.25f));
					}
					for (int num10 = 0; num10 < 8; num10++)
					{
						Fruit(transform, "prune", new Color(0.1f, 0.025f, 0.1f), new Vector3(0.023f, 0.017f, 0.019f), new Vector3(((float)(num10 % 4) - 1.5f) * 0.034f, 0.045f, ((float)(num10 / 4) - 0.5f) * 0.043f));
					}
					break;
				}
				case "appetix":
				case "poprocks":
					flag = true;
					goto IL_0b2d;
				default:
					{
						flag = false;
						goto IL_0b2d;
					}
					IL_0b2d:
					if (flag)
					{
						UnityEngine.Color color2 = ((key == "appetix") ? new Color(0.18f, 0.55f, 0.28f) : new Color(0.98f, 0.12f, 0.48f));
						Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0f, 0.08f, 0f), new Vector3(0.115f, 0.16f, (key == "appetix") ? 0.045f : 0.018f), color2);
						float num5 = ((key == "appetix") ? (-0.024f) : (-0.011f));
						Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0f, 0.09f, num5), new Vector3(0.106f, 0.055f, 0.001f), (UnityEngine.Color)((key == "appetix") ? Cream : new Color(1f, 0.83f, 0.12f)));
						Models.Label(transform, (key == "appetix") ? "APPÉTIX" : "POP!", new Vector3(0f, 0.08f, num5 - 0.002f), 0.005f, UnityEngine.Color.white, (UnityEngine.Vector2?)new Vector2(0.106f, 0.14f));
						if (key == "appetix")
						{
							Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0f, 0.159f, 0f), new Vector3(0.112f, 0.002f, 0.043f), Cream);
							Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0.055f, 0.08f, 0f), new Vector3(0.002f, 0.154f, 0.043f), new Color(0.11f, 0.39f, 0.2f));
							Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0f, 0.005f, 0f), new Vector3(0.111f, 0.003f, 0.043f), new Color(0.12f, 0.4f, 0.2f));
						}
						if (!(key == "poprocks"))
						{
							break;
						}
						for (int l = 0; l < 13; l++)
						{
							for (int m = -1; m <= 1; m += 2)
							{
								Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3((float)(l - 6) * 0.008f, 0.08f + (float)m * 0.08f, 0f), new Vector3(0.004f, 0.01f, 0.02f), new Color(0.8f, 0.07f, 0.33f));
							}
						}
						break;
					}
					if (key == "glow")
					{
						Lathe(transform, "Tube_lumineux", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[4]
						{
							new Vector2(0f, 0f),
							new Vector2(0.009f, 0.01f),
							new Vector2(0.009f, 0.22f),
							new Vector2(0f, 0.23f)
						}, new Color(0.27f, 1f, 0.035f));
						Cap(transform, 0.22f, 0.012f, new Color(0.08f, 0.09f, 0.08f), 0.014f);
						Cap(transform, 0.002f, 0.0105f, new Color(0.08f, 0.09f, 0.08f), 0.009f);
						Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0f, 0.241f, 0f), new Vector3(0.009f, 0.018f, 0.004f), new Color(0.07f, 0.09f, 0.07f));
						foreach (UnityEngine.Renderer componentsInChild in val.GetComponentsInChildren<UnityEngine.Renderer>())
						{
							if (((UnityEngine.Object)componentsInChild).name == "Tube_lumineux")
							{
								componentsInChild.material.EnableKeyword("_EMISSION");
								componentsInChild.material.SetColor("_EmissionColor", new Color(0.16f, 0.7f, 0.02f));
							}
						}
						break;
					}
					flag2 = key == "patee";
					flag3 = key == "lithium";
					flag4 = key == "nitro";
					flag5 = key == "absinthe";
					flag6 = key == "fizz";
					if (flag2)
					{
						num6 = 0.055f;
					}
					else
					{
						float num7 = (flag3 ? 0.021f : ((!(key == "cyclo") && !(key == "paranox")) ? (flag4 ? 0.028f : 0.037f) : 0.026f));
						num6 = num7;
					}
					num8 = num6;
					num9 = (flag2 ? 0.1f : (flag3 ? 0.14f : (flag5 ? 0.25f : ((key == "cyclo") ? 0.105f : ((!(key == "paranox")) ? (flag6 ? 0.19f : 0.17f) : 0.13f)))));
					if (key == "syrup")
					{
						val3 = new Color(0.28f, 0.09f, 0.025f);
					}
					else if (flag5)
					{
						val3 = new Color(0.055f, 0.3f, 0.07f);
					}
					else if (key == "focalix")
					{
						val3 = new Color(0.91f, 0.36f, 0.025f);
					}
					else if (key == "paranox")
					{
						val3 = new Color(0.7f, 0.025f, 0.04f);
					}
					else if (flag2)
					{
						val3 = new Color(0.46f, 0.25f, 0.09f);
					}
					else
					{
						val3 = (flag3 ? new Color(0.04f, 0.07f, 0.1f) : new Color(0.65f, 0.77f, 0.83f));
					}
					profile = (UnityEngine.Vector2[])(object)((flag2 || flag3 || flag6 || key == "focalix") ? new UnityEngine.Vector2[6]
					{
						new Vector2(0f, 0f),
						new Vector2(num8 * 0.94f, 0.003f),
						new Vector2(num8, 0.009f),
						new Vector2(num8, num9 - 0.005f),
						new Vector2(num8 * 0.94f, num9),
						new Vector2(0f, num9)
					} : new UnityEngine.Vector2[8]
					{
						new Vector2(0f, 0f),
						new Vector2(num8 * 0.85f, 0.003f),
						new Vector2(num8, 0.012f),
						new Vector2(num8, num9 * 0.65f),
						new Vector2(num8 * 0.9f, num9 * 0.76f),
						new Vector2(num8 * 0.38f, num9 * 0.88f),
						new Vector2(num8 * 0.38f, num9),
						new Vector2(0f, num9)
					});
					Finish(Lathe(transform, "Corps_" + key, profile, val3), (flag2 || flag3 || flag4) ? 0.52f : 0.34f, (flag2 || flag4) ? 0.65f : 0f);
					Ring(transform, "Joint_de_fond", num8 * 0.96f, 0.008f, 0.0015f, (UnityEngine.Color)((flag2 || flag4) ? new Color(0.64f, 0.67f, 0.69f) : UnityEngine.Color.Lerp(val3, UnityEngine.Color.black, 0.18f)));
					if (!flag2 && !flag3 && !flag6 && key != "focalix")
					{
						Ring(transform, "Bague_de_col", num8 * 0.4f, num9 - 0.002f, 0.002f, val3);
					}
					y = num9;
					radius = num8 * ((flag2 || flag3 || flag6) ? 1.03f : ((key == "focalix") ? 1f : 0.5f));
					c = (UnityEngine.Color)((!(key == "focalix")) ? ((flag3 || flag6) ? new Color(0.1f, 0.4f, 0.75f) : new Color(0.25f, 0.27f, 0.28f)) : Cream);
					Cap(transform, y, radius, c);
					if (flag3 || flag4)
					{
						Lathe(transform, "Borne", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[4]
						{
							new Vector2(0f, num9 + 0.018f),
							new Vector2(0.009f, num9 + 0.018f),
							new Vector2(0.009f, num9 + 0.027f),
							new Vector2(0f, num9 + 0.027f)
						}, UnityEngine.Color.gray);
					}
					if (key == "cyclo" || key == "paranox")
					{
						Lathe(transform, "Embout", (UnityEngine.Vector2[])(object)new UnityEngine.Vector2[4]
						{
							new Vector2(0.018f, num9),
							new Vector2(0.014f, num9 + 0.035f),
							new Vector2(0.004f, num9 + 0.065f),
							new Vector2(0f, num9 + 0.065f)
						}, UnityEngine.Color.white);
					}
					height = ((!flag2) ? (flag3 ? (num9 * 0.65f) : (num9 * 0.38f)) : 0.065f);
					p = transform;
					BottleLabel(p, key switch
					{
						"syrup" => "COFFIX", 
						"focalix" => "FOCALIX", 
						"fizz" => "BULLE-GAZ", 
						"cyclo" => "CYCLO-DROPS", 
						"paranox" => "PARANOX", 
						"lithium" => "Li", 
						"patee" => "DOG", 
						"nitro" => "N2", 
						_ => "ABSINTHE", 
					}, num9 * 0.43f, num8, height);
					if (flag2)
					{
						Cap(transform, 0.002f, num8 * 1.02f, UnityEngine.Color.gray, 0.004f);
						Cap(transform, num9 + 0.018f, num8 * 0.98f, new Color(0.66f, 0.68f, 0.7f), 0.002f);
						Ring(transform, "Sertissage_supérieur", num8 * 1.02f, num9 + 0.02f, 0.002f, new Color(0.72f, 0.74f, 0.76f));
						Ring(transform, "Rainure_du_couvercle", num8 * 0.79f, num9 + 0.0205f, 0.0007f, new Color(0.32f, 0.34f, 0.36f));
						Finish(Models.Part(transform, (UnityEngine.PrimitiveType)3, new Vector3(0f, num9 + 0.023f, 0f), new Vector3(0.018f, 0.003f, 0.032f), new Color(0.68f, 0.69f, 0.71f)), 0.65f, 0.8f);
					}
					break;
				}
				break;
			}
		}
		val.transform.localRotation = UnityEngine.Quaternion.Euler(0f, 180f, (float)((key == "glow") ? 35 : 0));
		return val;
	}
}
