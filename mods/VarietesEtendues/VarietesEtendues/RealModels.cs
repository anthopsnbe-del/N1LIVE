using System;
using System.Collections.Generic;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using UnityEngine;

namespace VarietesEtendues;

internal static class RealModels
{
	private static readonly Color Cream = new Color(0.94f, 0.9f, 0.77f);

	private static readonly Color Green = new Color(0.19f, 0.42f, 0.055f);

	private static readonly Color Red = new Color(0.55f, 0.012f, 0.025f);

	private static readonly Dictionary<string, Texture2D> skins = new Dictionary<string, Texture2D>();

	private static void Ring(Transform parent, string name, float radius, float y, float thickness, Color color)
	{
		//IL_0012: Unknown result type (might be due to invalid IL or missing references)
		//IL_0017: Unknown result type (might be due to invalid IL or missing references)
		//IL_0023: Unknown result type (might be due to invalid IL or missing references)
		//IL_0028: Unknown result type (might be due to invalid IL or missing references)
		//IL_0034: Unknown result type (might be due to invalid IL or missing references)
		//IL_0039: Unknown result type (might be due to invalid IL or missing references)
		//IL_0045: Unknown result type (might be due to invalid IL or missing references)
		//IL_004a: Unknown result type (might be due to invalid IL or missing references)
		//IL_0059: Unknown result type (might be due to invalid IL or missing references)
		//IL_005e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0063: Unknown result type (might be due to invalid IL or missing references)
		Lathe(parent, name, (Vector2[])(object)new Vector2[5]
		{
			new Vector2(radius - thickness, y - thickness),
			new Vector2(radius, y - thickness),
			new Vector2(radius + thickness, y),
			new Vector2(radius, y + thickness),
			new Vector2(radius - thickness, y + thickness)
		}, color, 48);
	}

	private static void Finish(GameObject g, float smoothness, float metal = 0f)
	{
		foreach (Renderer componentsInChild in g.GetComponentsInChildren<Renderer>(true))
		{
			componentsInChild.material.SetFloat("_Smoothness", smoothness);
			componentsInChild.material.SetFloat("_Glossiness", smoothness);
			componentsInChild.material.SetFloat("_Metallic", metal);
		}
	}

	internal static GameObject Lathe(Transform parent, string name, Vector2[] profile, Color color, int sides = 40)
	{
		//IL_0068: Unknown result type (might be due to invalid IL or missing references)
		//IL_0083: Unknown result type (might be due to invalid IL or missing references)
		//IL_0114: Unknown result type (might be due to invalid IL or missing references)
		List<Vector3> list = new List<Vector3>();
		List<Vector2> list2 = new List<Vector2>();
		List<int> list3 = new List<int>();
		for (int i = 0; i < profile.Length; i++)
		{
			for (int j = 0; j <= sides; j++)
			{
				float num = (float)j * (float)Math.PI * 2f / (float)sides;
				list.Add(new Vector3(Mathf.Cos(num) * profile[i].x, profile[i].y, Mathf.Sin(num) * profile[i].x));
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

	private static GameObject MeshObject(Transform p, string name, List<Vector3> v, List<int> t, Color c, List<Vector2>? uv = null)
	{
		//IL_0001: Unknown result type (might be due to invalid IL or missing references)
		//IL_0006: Unknown result type (might be due to invalid IL or missing references)
		//IL_0013: Unknown result type (might be due to invalid IL or missing references)
		//IL_0018: Unknown result type (might be due to invalid IL or missing references)
		//IL_0029: Unknown result type (might be due to invalid IL or missing references)
		//IL_003a: Unknown result type (might be due to invalid IL or missing references)
		//IL_004c: Expected O, but got Unknown
		//IL_006e: Unknown result type (might be due to invalid IL or missing references)
		//IL_007a: Unknown result type (might be due to invalid IL or missing references)
		//IL_008e: Unknown result type (might be due to invalid IL or missing references)
		//IL_009b: Expected O, but got Unknown
		GameObject val = new GameObject(name);
		val.transform.SetParent(p, false);
		Mesh val2 = new Mesh
		{
			name = "VX_Procedural_" + name,
			vertices = Il2CppStructArray<Vector3>.op_Implicit(v.ToArray()),
			triangles = Il2CppStructArray<int>.op_Implicit(t.ToArray())
		};
		if (uv != null)
		{
			val2.uv = Il2CppStructArray<Vector2>.op_Implicit(uv.ToArray());
		}
		val2.RecalculateNormals();
		val2.RecalculateBounds();
		val.AddComponent<MeshFilter>().mesh = val2;
		Material val3 = Models.Material(c);
		val3.SetInt("_Cull", 0);
		((Renderer)val.AddComponent<MeshRenderer>()).material = val3;
		return val;
	}

	private static GameObject Fruit(Transform p, string key, Color color, Vector3 scale, Vector3 pos)
	{
		//IL_0241: Unknown result type (might be due to invalid IL or missing references)
		//IL_0519: Unknown result type (might be due to invalid IL or missing references)
		//IL_0267: Unknown result type (might be due to invalid IL or missing references)
		//IL_026e: Expected O, but got Unknown
		//IL_0180: Unknown result type (might be due to invalid IL or missing references)
		//IL_0188: Unknown result type (might be due to invalid IL or missing references)
		//IL_018d: Unknown result type (might be due to invalid IL or missing references)
		//IL_018e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0193: Unknown result type (might be due to invalid IL or missing references)
		//IL_01b0: Unknown result type (might be due to invalid IL or missing references)
		//IL_02c0: Unknown result type (might be due to invalid IL or missing references)
		//IL_02c1: Unknown result type (might be due to invalid IL or missing references)
		//IL_0322: Unknown result type (might be due to invalid IL or missing references)
		//IL_0336: Unknown result type (might be due to invalid IL or missing references)
		//IL_033d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0342: Unknown result type (might be due to invalid IL or missing references)
		//IL_03cb: Unknown result type (might be due to invalid IL or missing references)
		//IL_03dc: Unknown result type (might be due to invalid IL or missing references)
		//IL_0413: Unknown result type (might be due to invalid IL or missing references)
		//IL_0418: Unknown result type (might be due to invalid IL or missing references)
		//IL_0474: Unknown result type (might be due to invalid IL or missing references)
		//IL_0483: Unknown result type (might be due to invalid IL or missing references)
		//IL_0498: Unknown result type (might be due to invalid IL or missing references)
		//IL_04b2: Unknown result type (might be due to invalid IL or missing references)
		//IL_04b7: Unknown result type (might be due to invalid IL or missing references)
		//IL_0427: Unknown result type (might be due to invalid IL or missing references)
		//IL_0438: Unknown result type (might be due to invalid IL or missing references)
		//IL_0460: Unknown result type (might be due to invalid IL or missing references)
		//IL_0465: Unknown result type (might be due to invalid IL or missing references)
		List<Vector3> list = new List<Vector3>();
		List<int> list2 = new List<int>();
		List<Vector2> list3 = new List<Vector2>();
		for (int i = 0; i <= 24; i++)
		{
			for (int j = 0; j <= 40; j++)
			{
				float num = (float)j * (float)Math.PI * 2f / 40f;
				float num2 = (float)i * (float)Math.PI / 24f;
				float num3 = Mathf.Cos(num2);
				float num4 = Mathf.Sin(num2);
				float num5 = ((key == "prune") ? (1f + 0.07f * Mathf.Sin(num * 9f + num2 * 7f) * Mathf.Sin(num * 5f - num2 * 8f)) : 1f);
				float num6 = Mathf.Cos(num) * num4 * num5;
				float num7 = Mathf.Sin(num) * num4 * num5;
				if (key == "mango")
				{
					num6 *= 1f - 0.18f * num3;
					num6 += 0.18f * num3 * num3;
					num7 *= 1f - 0.11f * num3;
				}
				if (key == "lemon")
				{
					num4 = Mathf.Max(0f, num4) * (1f - 0.18f * Mathf.Pow(Mathf.Abs(num3), 6f));
					num6 = Mathf.Cos(num) * num4;
					num7 = Mathf.Sin(num) * num4;
					num3 *= 1f + 0.12f * Mathf.Pow(Mathf.Abs(num3), 8f);
				}
				list.Add(pos + Vector3.Scale(new Vector3(num6, num3, num7), scale));
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
		GameObject val = MeshObject(p, key, list, list2, color, list3);
		if (!skins.TryGetValue(key, out Texture2D value))
		{
			value = new Texture2D(256, 128, (TextureFormat)4, true);
			Color[] array = (Color[])(object)new Color[32768];
			for (int m = 0; m < 128; m++)
			{
				for (int n = 0; n < 256; n++)
				{
					float num10 = (Mathf.Repeat(Mathf.Sin((float)n * 12.9898f + (float)m * 78.233f) * 43758.547f, 1f) - 0.5f) * 0.1f;
					Color val2 = color;
					if (key == "mango")
					{
						float num11 = Mathf.Clamp01((Mathf.Cos((float)n / 255f * (float)Math.PI * 2f) + 0.25f) * 0.58f + (1f - (float)m / 127f) * 0.35f);
						val2 = Color.Lerp(new Color(1f, 0.67f, 0.08f), new Color(0.78f, 0.055f, 0.025f), num11);
					}
					if (key == "prune")
					{
						num10 += Mathf.Sin((float)n * 0.65f + Mathf.Sin((float)m * 0.4f) * 3f) * 0.035f;
					}
					if (key == "lemon")
					{
						num10 += Mathf.Sin((float)n * 1.7f + (float)m * 0.8f) * Mathf.Sin((float)m * 2.1f) * 0.018f;
					}
					if (key == "dragon")
					{
						val2 = Color.Lerp(val2, new Color(0.98f, 0.18f, 0.43f), Mathf.Pow(Mathf.Abs(Mathf.Sin((float)n * 0.075f + Mathf.Sin((float)m * 0.09f))), 10f) * 0.5f);
					}
					if (key == "durian")
					{
						val2 = Color.Lerp(val2, new Color(0.25f, 0.31f, 0.065f), Mathf.Pow(Mathf.Abs(Mathf.Sin((float)n * 0.12f)), 16f) * 0.35f);
					}
					array[m * 256 + n] = new Color(Mathf.Clamp01(val2.r + num10), Mathf.Clamp01(val2.g + num10 * 0.8f), Mathf.Clamp01(val2.b + num10 * 0.55f), 1f);
				}
			}
			value.SetPixels(Il2CppStructArray<Color>.op_Implicit(array));
			value.Apply();
			((Texture)value).wrapMode = (TextureWrapMode)0;
			skins[key] = value;
		}
		Material material = val.GetComponent<Renderer>().material;
		material.color = Color.white;
		material.mainTexture = (Texture)((key == "lemon") ? ((object)Models.Texture("LEMON SKIN")) : ((object)value));
		if (key == "lemon")
		{
			material.mainTexture.wrapMode = (TextureWrapMode)0;
		}
		Finish(val, (key == "prune") ? 0.26f : 0.3f);
		return val;
	}

	private static void Leaf(Transform p, Vector3 origin, Vector3 direction, float width, Color color)
	{
		//IL_0002: Unknown result type (might be due to invalid IL or missing references)
		//IL_0007: Unknown result type (might be due to invalid IL or missing references)
		//IL_000c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0011: Unknown result type (might be due to invalid IL or missing references)
		//IL_0026: Unknown result type (might be due to invalid IL or missing references)
		//IL_0027: Unknown result type (might be due to invalid IL or missing references)
		//IL_002d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0032: Unknown result type (might be due to invalid IL or missing references)
		//IL_0037: Unknown result type (might be due to invalid IL or missing references)
		//IL_0038: Unknown result type (might be due to invalid IL or missing references)
		//IL_0039: Unknown result type (might be due to invalid IL or missing references)
		//IL_003a: Unknown result type (might be due to invalid IL or missing references)
		//IL_003f: Unknown result type (might be due to invalid IL or missing references)
		//IL_004c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0053: Unknown result type (might be due to invalid IL or missing references)
		//IL_0054: Unknown result type (might be due to invalid IL or missing references)
		//IL_0056: Unknown result type (might be due to invalid IL or missing references)
		//IL_005b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0066: Unknown result type (might be due to invalid IL or missing references)
		//IL_006d: Unknown result type (might be due to invalid IL or missing references)
		//IL_006e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0070: Unknown result type (might be due to invalid IL or missing references)
		//IL_0075: Unknown result type (might be due to invalid IL or missing references)
		//IL_0080: Unknown result type (might be due to invalid IL or missing references)
		//IL_0081: Unknown result type (might be due to invalid IL or missing references)
		//IL_008b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0090: Unknown result type (might be due to invalid IL or missing references)
		//IL_00f3: Unknown result type (might be due to invalid IL or missing references)
		//IL_0020: Unknown result type (might be due to invalid IL or missing references)
		//IL_0025: Unknown result type (might be due to invalid IL or missing references)
		Vector3 val = Vector3.Cross(((Vector3)(ref direction)).normalized, Vector3.forward);
		if (((Vector3)(ref val)).sqrMagnitude < 0.1f)
		{
			val = Vector3.right;
		}
		Vector3 val2 = origin + direction * 0.5f;
		Vector3 item = origin + direction;
		MeshObject(p, "Feuille", new List<Vector3>
		{
			origin,
			val2 + val * width,
			item,
			val2 - val * width,
			val2 + Vector3.forward * 0.008f
		}, new List<int>
		{
			0, 4, 1, 1, 4, 2, 2, 4, 3, 3,
			4, 0
		}, color);
	}

	private static void Spike(Transform p, Vector3 pos, Vector3 direction, float radius, float length, Color c)
	{
		//IL_0018: Unknown result type (might be due to invalid IL or missing references)
		//IL_001d: Unknown result type (might be due to invalid IL or missing references)
		//IL_002a: Unknown result type (might be due to invalid IL or missing references)
		//IL_002f: Unknown result type (might be due to invalid IL or missing references)
		//IL_003d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0042: Unknown result type (might be due to invalid IL or missing references)
		//IL_0047: Unknown result type (might be due to invalid IL or missing references)
		//IL_0055: Unknown result type (might be due to invalid IL or missing references)
		//IL_0060: Unknown result type (might be due to invalid IL or missing references)
		//IL_0065: Unknown result type (might be due to invalid IL or missing references)
		//IL_0066: Unknown result type (might be due to invalid IL or missing references)
		GameObject obj = Lathe(p, "Pointe", (Vector2[])(object)new Vector2[3]
		{
			new Vector2(0f, 0f),
			new Vector2(radius, 0.003f),
			new Vector2(0f, length)
		}, c, 8);
		obj.transform.localPosition = pos;
		obj.transform.localRotation = Quaternion.FromToRotation(Vector3.up, direction);
	}

	private static void Cap(Transform p, float y, float radius, Color c, float height = 0.018f)
	{
		//IL_0014: Unknown result type (might be due to invalid IL or missing references)
		//IL_0019: Unknown result type (might be due to invalid IL or missing references)
		//IL_0022: Unknown result type (might be due to invalid IL or missing references)
		//IL_0027: Unknown result type (might be due to invalid IL or missing references)
		//IL_0033: Unknown result type (might be due to invalid IL or missing references)
		//IL_0038: Unknown result type (might be due to invalid IL or missing references)
		//IL_0048: Unknown result type (might be due to invalid IL or missing references)
		//IL_004d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0052: Unknown result type (might be due to invalid IL or missing references)
		//IL_0090: Unknown result type (might be due to invalid IL or missing references)
		//IL_00a1: Unknown result type (might be due to invalid IL or missing references)
		//IL_00a6: Unknown result type (might be due to invalid IL or missing references)
		Lathe(p, "Bouchon", (Vector2[])(object)new Vector2[4]
		{
			new Vector2(0f, y),
			new Vector2(radius, y),
			new Vector2(radius, y + height),
			new Vector2(0f, y + height)
		}, c);
		for (int i = 0; i < 32; i++)
		{
			float num = (float)i * (float)Math.PI * 2f / 32f;
			Models.Part(p, (PrimitiveType)3, new Vector3(Mathf.Cos(num) * radius, y + height * 0.5f, Mathf.Sin(num) * radius), new Vector3(0.002f, height, 0.002f), c);
		}
	}

	private static void Label(Transform p, string text, float y, float z, float size = 0.004f)
	{
		//IL_0009: Unknown result type (might be due to invalid IL or missing references)
		//IL_0010: Unknown result type (might be due to invalid IL or missing references)
		Models.Label(p, text, new Vector3(0f, y, z), size, Color.black);
	}

	private static void BottleLabel(Transform p, string text, float y, float radius, float height)
	{
		//IL_001f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0024: Unknown result type (might be due to invalid IL or missing references)
		//IL_003c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0041: Unknown result type (might be due to invalid IL or missing references)
		//IL_0046: Unknown result type (might be due to invalid IL or missing references)
		//IL_00c6: Unknown result type (might be due to invalid IL or missing references)
		//IL_00d6: Unknown result type (might be due to invalid IL or missing references)
		//IL_0156: Unknown result type (might be due to invalid IL or missing references)
		//IL_0161: Unknown result type (might be due to invalid IL or missing references)
		Lathe(p, "Papier_étiquette", (Vector2[])(object)new Vector2[2]
		{
			new Vector2(radius + 0.0006f, y - height * 0.5f),
			new Vector2(radius + 0.0006f, y + height * 0.5f)
		}, Cream);
		List<Vector3> list = new List<Vector3>();
		List<int> list2 = new List<int>();
		List<Vector2> list3 = new List<Vector2>();
		for (int i = 0; i < 2; i++)
		{
			for (int j = 0; j <= 32; j++)
			{
				float num = (float)j / 32f;
				float num2 = (num - 0.5f) * (float)Math.PI * 0.94f;
				list.Add(new Vector3(Mathf.Sin(num2) * (radius + 0.001f), y + ((float)i - 0.5f) * height, (0f - Mathf.Cos(num2)) * (radius + 0.001f)));
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
		GameObject obj = MeshObject(p, "Impression_" + text, list, list2, Color.white, list3);
		Material val = Models.Material(Color.white);
		val.mainTexture = (Texture)(object)Models.Texture(text);
		val.SetFloat("_Smoothness", 0.12f);
		val.SetFloat("_Glossiness", 0.12f);
		obj.GetComponent<Renderer>().material = val;
	}

	private static void MelonSector(Transform p, string name, float radius, float inner, float depth, Color color)
	{
		//IL_01b8: Unknown result type (might be due to invalid IL or missing references)
		//IL_0067: Unknown result type (might be due to invalid IL or missing references)
		List<Vector3> list = new List<Vector3>();
		List<int> list2 = new List<int>();
		for (int i = 0; i < 2; i++)
		{
			for (int j = 0; j < 2; j++)
			{
				for (int k = 0; k <= 32; k++)
				{
					float num = -0.68f + (float)k * 1.36f / 32f;
					float num2 = ((j == 0) ? inner : radius);
					list.Add(new Vector3(Mathf.Sin(num) * num2, 0.195f - Mathf.Cos(num) * num2, (float)((i != 0) ? 1 : (-1)) * depth * 0.5f));
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

	internal static GameObject Make(string key, string name)
	{
		//IL_000b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0011: Expected O, but got Unknown
		//IL_003a: Unknown result type (might be due to invalid IL or missing references)
		//IL_004e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0053: Unknown result type (might be due to invalid IL or missing references)
		//IL_006f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0083: Unknown result type (might be due to invalid IL or missing references)
		//IL_0097: Unknown result type (might be due to invalid IL or missing references)
		//IL_00b8: Unknown result type (might be due to invalid IL or missing references)
		//IL_00c2: Unknown result type (might be due to invalid IL or missing references)
		//IL_00d1: Unknown result type (might be due to invalid IL or missing references)
		//IL_013d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0144: Expected O, but got Unknown
		//IL_0168: Unknown result type (might be due to invalid IL or missing references)
		//IL_0188: Unknown result type (might be due to invalid IL or missing references)
		//IL_01b0: Unknown result type (might be due to invalid IL or missing references)
		//IL_01b5: Unknown result type (might be due to invalid IL or missing references)
		//IL_01c6: Unknown result type (might be due to invalid IL or missing references)
		//IL_01cb: Unknown result type (might be due to invalid IL or missing references)
		//IL_01dc: Unknown result type (might be due to invalid IL or missing references)
		//IL_01e1: Unknown result type (might be due to invalid IL or missing references)
		//IL_01f2: Unknown result type (might be due to invalid IL or missing references)
		//IL_01f7: Unknown result type (might be due to invalid IL or missing references)
		//IL_0208: Unknown result type (might be due to invalid IL or missing references)
		//IL_020d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0212: Unknown result type (might be due to invalid IL or missing references)
		//IL_023d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0242: Unknown result type (might be due to invalid IL or missing references)
		//IL_0253: Unknown result type (might be due to invalid IL or missing references)
		//IL_0258: Unknown result type (might be due to invalid IL or missing references)
		//IL_0269: Unknown result type (might be due to invalid IL or missing references)
		//IL_026e: Unknown result type (might be due to invalid IL or missing references)
		//IL_027f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0284: Unknown result type (might be due to invalid IL or missing references)
		//IL_0295: Unknown result type (might be due to invalid IL or missing references)
		//IL_029a: Unknown result type (might be due to invalid IL or missing references)
		//IL_029f: Unknown result type (might be due to invalid IL or missing references)
		//IL_02c1: Unknown result type (might be due to invalid IL or missing references)
		//IL_02cb: Unknown result type (might be due to invalid IL or missing references)
		//IL_02da: Unknown result type (might be due to invalid IL or missing references)
		//IL_02ff: Unknown result type (might be due to invalid IL or missing references)
		//IL_0309: Unknown result type (might be due to invalid IL or missing references)
		//IL_0318: Unknown result type (might be due to invalid IL or missing references)
		//IL_033b: Unknown result type (might be due to invalid IL or missing references)
		//IL_035a: Unknown result type (might be due to invalid IL or missing references)
		//IL_0364: Unknown result type (might be due to invalid IL or missing references)
		//IL_0373: Unknown result type (might be due to invalid IL or missing references)
		//IL_0396: Unknown result type (might be due to invalid IL or missing references)
		//IL_03bb: Unknown result type (might be due to invalid IL or missing references)
		//IL_03c2: Expected O, but got Unknown
		//IL_03e6: Unknown result type (might be due to invalid IL or missing references)
		//IL_0406: Unknown result type (might be due to invalid IL or missing references)
		//IL_042b: Unknown result type (might be due to invalid IL or missing references)
		//IL_043f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0444: Unknown result type (might be due to invalid IL or missing references)
		//IL_046d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0472: Unknown result type (might be due to invalid IL or missing references)
		//IL_0483: Unknown result type (might be due to invalid IL or missing references)
		//IL_0488: Unknown result type (might be due to invalid IL or missing references)
		//IL_049c: Unknown result type (might be due to invalid IL or missing references)
		//IL_04bf: Unknown result type (might be due to invalid IL or missing references)
		//IL_04d3: Unknown result type (might be due to invalid IL or missing references)
		//IL_04ec: Unknown result type (might be due to invalid IL or missing references)
		//IL_17e3: Unknown result type (might be due to invalid IL or missing references)
		//IL_08c7: Unknown result type (might be due to invalid IL or missing references)
		//IL_08f5: Unknown result type (might be due to invalid IL or missing references)
		//IL_0923: Unknown result type (might be due to invalid IL or missing references)
		//IL_0548: Unknown result type (might be due to invalid IL or missing references)
		//IL_054d: Unknown result type (might be due to invalid IL or missing references)
		//IL_09d7: Unknown result type (might be due to invalid IL or missing references)
		//IL_09eb: Unknown result type (might be due to invalid IL or missing references)
		//IL_09ff: Unknown result type (might be due to invalid IL or missing references)
		//IL_059b: Unknown result type (might be due to invalid IL or missing references)
		//IL_05ac: Unknown result type (might be due to invalid IL or missing references)
		//IL_05c0: Unknown result type (might be due to invalid IL or missing references)
		//IL_05e3: Unknown result type (might be due to invalid IL or missing references)
		//IL_05e8: Unknown result type (might be due to invalid IL or missing references)
		//IL_05f9: Unknown result type (might be due to invalid IL or missing references)
		//IL_05fe: Unknown result type (might be due to invalid IL or missing references)
		//IL_0612: Unknown result type (might be due to invalid IL or missing references)
		//IL_0560: Unknown result type (might be due to invalid IL or missing references)
		//IL_0565: Unknown result type (might be due to invalid IL or missing references)
		//IL_0578: Unknown result type (might be due to invalid IL or missing references)
		//IL_057d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0a27: Unknown result type (might be due to invalid IL or missing references)
		//IL_0a3b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0a4f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0a6f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0a83: Unknown result type (might be due to invalid IL or missing references)
		//IL_0a97: Unknown result type (might be due to invalid IL or missing references)
		//IL_0590: Unknown result type (might be due to invalid IL or missing references)
		//IL_0595: Unknown result type (might be due to invalid IL or missing references)
		//IL_0974: Unknown result type (might be due to invalid IL or missing references)
		//IL_0988: Unknown result type (might be due to invalid IL or missing references)
		//IL_099c: Unknown result type (might be due to invalid IL or missing references)
		//IL_064f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0663: Unknown result type (might be due to invalid IL or missing references)
		//IL_066d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0aca: Unknown result type (might be due to invalid IL or missing references)
		//IL_0ade: Unknown result type (might be due to invalid IL or missing references)
		//IL_0b0a: Unknown result type (might be due to invalid IL or missing references)
		//IL_0df7: Unknown result type (might be due to invalid IL or missing references)
		//IL_0dfc: Unknown result type (might be due to invalid IL or missing references)
		//IL_0e0d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0e12: Unknown result type (might be due to invalid IL or missing references)
		//IL_0e23: Unknown result type (might be due to invalid IL or missing references)
		//IL_0e28: Unknown result type (might be due to invalid IL or missing references)
		//IL_0e39: Unknown result type (might be due to invalid IL or missing references)
		//IL_0e3e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0e52: Unknown result type (might be due to invalid IL or missing references)
		//IL_0e79: Unknown result type (might be due to invalid IL or missing references)
		//IL_0ea2: Unknown result type (might be due to invalid IL or missing references)
		//IL_0ec2: Unknown result type (might be due to invalid IL or missing references)
		//IL_0ed6: Unknown result type (might be due to invalid IL or missing references)
		//IL_0eea: Unknown result type (might be due to invalid IL or missing references)
		//IL_0b66: Unknown result type (might be due to invalid IL or missing references)
		//IL_0b50: Unknown result type (might be due to invalid IL or missing references)
		//IL_0699: Unknown result type (might be due to invalid IL or missing references)
		//IL_06ad: Unknown result type (might be due to invalid IL or missing references)
		//IL_06c1: Unknown result type (might be due to invalid IL or missing references)
		//IL_0b6b: Unknown result type (might be due to invalid IL or missing references)
		//IL_0b7e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0ba6: Unknown result type (might be due to invalid IL or missing references)
		//IL_0bab: Unknown result type (might be due to invalid IL or missing references)
		//IL_0715: Unknown result type (might be due to invalid IL or missing references)
		//IL_071f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0724: Unknown result type (might be due to invalid IL or missing references)
		//IL_0743: Unknown result type (might be due to invalid IL or missing references)
		//IL_0748: Unknown result type (might be due to invalid IL or missing references)
		//IL_075e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0f4c: Unknown result type (might be due to invalid IL or missing references)
		//IL_0bdc: Unknown result type (might be due to invalid IL or missing references)
		//IL_0bf0: Unknown result type (might be due to invalid IL or missing references)
		//IL_0c18: Unknown result type (might be due to invalid IL or missing references)
		//IL_0c11: Unknown result type (might be due to invalid IL or missing references)
		//IL_07f6: Unknown result type (might be due to invalid IL or missing references)
		//IL_0808: Unknown result type (might be due to invalid IL or missing references)
		//IL_0816: Unknown result type (might be due to invalid IL or missing references)
		//IL_0823: Unknown result type (might be due to invalid IL or missing references)
		//IL_0828: Unknown result type (might be due to invalid IL or missing references)
		//IL_0843: Unknown result type (might be due to invalid IL or missing references)
		//IL_1176: Unknown result type (might be due to invalid IL or missing references)
		//IL_117b: Unknown result type (might be due to invalid IL or missing references)
		//IL_118f: Unknown result type (might be due to invalid IL or missing references)
		//IL_1194: Unknown result type (might be due to invalid IL or missing references)
		//IL_11a2: Unknown result type (might be due to invalid IL or missing references)
		//IL_11a7: Unknown result type (might be due to invalid IL or missing references)
		//IL_11b8: Unknown result type (might be due to invalid IL or missing references)
		//IL_11bd: Unknown result type (might be due to invalid IL or missing references)
		//IL_11ce: Unknown result type (might be due to invalid IL or missing references)
		//IL_11d3: Unknown result type (might be due to invalid IL or missing references)
		//IL_11e1: Unknown result type (might be due to invalid IL or missing references)
		//IL_11e6: Unknown result type (might be due to invalid IL or missing references)
		//IL_12bf: Unknown result type (might be due to invalid IL or missing references)
		//IL_1202: Unknown result type (might be due to invalid IL or missing references)
		//IL_1207: Unknown result type (might be due to invalid IL or missing references)
		//IL_121b: Unknown result type (might be due to invalid IL or missing references)
		//IL_1220: Unknown result type (might be due to invalid IL or missing references)
		//IL_122e: Unknown result type (might be due to invalid IL or missing references)
		//IL_1233: Unknown result type (might be due to invalid IL or missing references)
		//IL_1244: Unknown result type (might be due to invalid IL or missing references)
		//IL_1249: Unknown result type (might be due to invalid IL or missing references)
		//IL_1260: Unknown result type (might be due to invalid IL or missing references)
		//IL_1265: Unknown result type (might be due to invalid IL or missing references)
		//IL_127c: Unknown result type (might be due to invalid IL or missing references)
		//IL_1281: Unknown result type (might be due to invalid IL or missing references)
		//IL_1292: Unknown result type (might be due to invalid IL or missing references)
		//IL_1297: Unknown result type (might be due to invalid IL or missing references)
		//IL_12a5: Unknown result type (might be due to invalid IL or missing references)
		//IL_12aa: Unknown result type (might be due to invalid IL or missing references)
		//IL_0c4f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0c59: Unknown result type (might be due to invalid IL or missing references)
		//IL_0c68: Unknown result type (might be due to invalid IL or missing references)
		//IL_1145: Unknown result type (might be due to invalid IL or missing references)
		//IL_112f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0c99: Unknown result type (might be due to invalid IL or missing references)
		//IL_0cad: Unknown result type (might be due to invalid IL or missing references)
		//IL_0cb2: Unknown result type (might be due to invalid IL or missing references)
		//IL_0cce: Unknown result type (might be due to invalid IL or missing references)
		//IL_0ce2: Unknown result type (might be due to invalid IL or missing references)
		//IL_0cf6: Unknown result type (might be due to invalid IL or missing references)
		//IL_0d12: Unknown result type (might be due to invalid IL or missing references)
		//IL_0d26: Unknown result type (might be due to invalid IL or missing references)
		//IL_0d3a: Unknown result type (might be due to invalid IL or missing references)
		//IL_114a: Unknown result type (might be due to invalid IL or missing references)
		//IL_1333: Unknown result type (might be due to invalid IL or missing references)
		//IL_1311: Unknown result type (might be due to invalid IL or missing references)
		//IL_1313: Unknown result type (might be due to invalid IL or missing references)
		//IL_131d: Unknown result type (might be due to invalid IL or missing references)
		//IL_0d80: Unknown result type (might be due to invalid IL or missing references)
		//IL_0d94: Unknown result type (might be due to invalid IL or missing references)
		//IL_0da8: Unknown result type (might be due to invalid IL or missing references)
		//IL_13b5: Unknown result type (might be due to invalid IL or missing references)
		//IL_13ba: Unknown result type (might be due to invalid IL or missing references)
		//IL_136f: Unknown result type (might be due to invalid IL or missing references)
		//IL_13e9: Unknown result type (might be due to invalid IL or missing references)
		//IL_13d3: Unknown result type (might be due to invalid IL or missing references)
		//IL_13f4: Unknown result type (might be due to invalid IL or missing references)
		//IL_13ee: Unknown result type (might be due to invalid IL or missing references)
		//IL_1421: Unknown result type (might be due to invalid IL or missing references)
		//IL_1426: Unknown result type (might be due to invalid IL or missing references)
		//IL_143a: Unknown result type (might be due to invalid IL or missing references)
		//IL_143f: Unknown result type (might be due to invalid IL or missing references)
		//IL_1453: Unknown result type (might be due to invalid IL or missing references)
		//IL_1458: Unknown result type (might be due to invalid IL or missing references)
		//IL_146c: Unknown result type (might be due to invalid IL or missing references)
		//IL_1471: Unknown result type (might be due to invalid IL or missing references)
		//IL_1476: Unknown result type (might be due to invalid IL or missing references)
		//IL_14b8: Unknown result type (might be due to invalid IL or missing references)
		//IL_14bd: Unknown result type (might be due to invalid IL or missing references)
		//IL_14d1: Unknown result type (might be due to invalid IL or missing references)
		//IL_14d6: Unknown result type (might be due to invalid IL or missing references)
		//IL_14ea: Unknown result type (might be due to invalid IL or missing references)
		//IL_14ef: Unknown result type (might be due to invalid IL or missing references)
		//IL_1503: Unknown result type (might be due to invalid IL or missing references)
		//IL_1508: Unknown result type (might be due to invalid IL or missing references)
		//IL_150d: Unknown result type (might be due to invalid IL or missing references)
		//IL_16c5: Unknown result type (might be due to invalid IL or missing references)
		//IL_16f4: Unknown result type (might be due to invalid IL or missing references)
		//IL_172d: Unknown result type (might be due to invalid IL or missing references)
		//IL_1761: Unknown result type (might be due to invalid IL or missing references)
		//IL_177f: Unknown result type (might be due to invalid IL or missing references)
		//IL_1793: Unknown result type (might be due to invalid IL or missing references)
		//IL_17a7: Unknown result type (might be due to invalid IL or missing references)
		GameObject val = new GameObject("VX_Modele3D_" + key);
		Transform transform = val.transform;
		if (key.StartsWith("seed_", StringComparison.Ordinal))
		{
			Models.Part(transform, (PrimitiveType)3, new Vector3(0f, 0.065f, 0f), new Vector3(0.088f, 0.13f, 0.008f), Cream);
			Models.Part(transform, (PrimitiveType)3, new Vector3(0f, 0.122f, -0.005f), new Vector3(0.087f, 0.012f, 0.001f), new Color(0.75f, 0.7f, 0.58f));
			Models.Label(transform, name.ToUpperInvariant(), new Vector3(0f, 0.062f, -0.0046f), 0.004f, Color.white, (Vector2?)new Vector2(0.082f, 0.109f));
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
			Color val3 = default(Color);
			Vector2[] profile;
			float y;
			float radius;
			Color c;
			float height;
			Transform p;
			switch (key)
			{
			case "pill666":
			{
				GameObject val5 = new GameObject("Capsule_bicolore");
				val5.transform.SetParent(transform, false);
				val5.transform.localRotation = Quaternion.Euler(0f, 0f, 90f);
				val5.transform.localPosition = new Vector3(0.047f, 0.026f, 0f);
				Lathe(val5.transform, "Moitié_rouge", (Vector2[])(object)new Vector2[5]
				{
					new Vector2(0f, 0f),
					new Vector2(0.008f, 0.003f),
					new Vector2(0.012f, 0.009f),
					new Vector2(0.013f, 0.016f),
					new Vector2(0.013f, 0.047f)
				}, Red);
				Lathe(val5.transform, "Moitié_ivoire", (Vector2[])(object)new Vector2[5]
				{
					new Vector2(0.0133f, 0.047f),
					new Vector2(0.0133f, 0.078f),
					new Vector2(0.012f, 0.086f),
					new Vector2(0.008f, 0.091f),
					new Vector2(0f, 0.094f)
				}, Cream);
				Models.Label(transform, "666", new Vector3(-0.023f, 0.026f, -0.0136f), 0.005f, Color.black, (Vector2?)new Vector2(0.043f, 0.019f));
				Models.Label(transform, "666", new Vector3(-0.023f, 0.026f, 0.0136f), 0.005f, Color.black, (Vector2?)new Vector2(0.043f, 0.019f)).transform.localRotation = Quaternion.Euler(0f, 180f, 0f);
				Models.Label(transform, "666", new Vector3(-0.023f, 0.0395f, 0f), 0.005f, Color.black, (Vector2?)new Vector2(0.043f, 0.019f)).transform.localRotation = Quaternion.Euler(90f, 0f, 0f);
				Finish(val5, 0.58f);
				break;
			}
			case "lemon":
			{
				GameObject val4 = new GameObject("Citron");
				val4.transform.SetParent(transform, false);
				val4.transform.localPosition = new Vector3(0f, 0.055f, 0f);
				val4.transform.localRotation = Quaternion.Euler(0f, 0f, 82f);
				Fruit(val4.transform, "lemon", new Color(1f, 0.81f, 0.025f), new Vector3(0.044f, 0.069f, 0.043f), Vector3.zero);
				Lathe(val4.transform, "Pédoncule", (Vector2[])(object)new Vector2[2]
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
					Color color = ((key == "dragon") ? new Color(0.82f, 0.035f, 0.32f) : ((key == "lemon") ? new Color(1f, 0.81f, 0.025f) : ((!(key == "mango")) ? new Color(0.47f, 0.49f, 0.12f) : new Color(1f, 0.48f, 0.035f))));
					Fruit(p, key2, color, new Vector3(0.065f, 0.085f, 0.056f), new Vector3(0f, 0.09f, 0f));
					Lathe(transform, "Tige", (Vector2[])(object)new Vector2[2]
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
							Vector3 direction = new Vector3(Mathf.Cos(num), 0.55f, Mathf.Sin(num)) * 0.045f;
							Leaf(transform, new Vector3(Mathf.Cos(num) * 0.047f, num2, Mathf.Sin(num) * 0.045f), direction, 0.009f, new Color(0.36f, 0.62f, 0.08f));
						}
					}
					if (!(key == "durian"))
					{
						break;
					}
					Vector3 val2 = default(Vector3);
					for (int j = 1; j < 10; j++)
					{
						for (int k = 0; k < 15; k++)
						{
							float num3 = (float)k * (float)Math.PI * 2f / 15f + (float)j * 0.4f;
							float num4 = (float)j * (float)Math.PI / 10f;
							((Vector3)(ref val2))._002Ector(Mathf.Cos(num3) * Mathf.Sin(num4), Mathf.Cos(num4), Mathf.Sin(num3) * Mathf.Sin(num4));
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
							Models.Part(transform, (PrimitiveType)0, new Vector3(num14, num13, (float)num11 * 0.028f), new Vector3(0.007f, 0.012f, 0.002f), new Color(0.045f, 0.018f, 0.012f));
						}
					}
					break;
				}
				case "prune":
				{
					Models.Part(transform, (PrimitiveType)3, new Vector3(0f, 0.012f, 0f), new Vector3(0.16f, 0.02f, 0.11f), new Color(0.58f, 0.42f, 0.23f));
					for (int n = -1; n <= 1; n += 2)
					{
						Models.Part(transform, (PrimitiveType)3, new Vector3((float)n * 0.077f, 0.027f, 0f), new Vector3(0.008f, 0.034f, 0.11f), new Color(0.62f, 0.45f, 0.25f));
						Models.Part(transform, (PrimitiveType)3, new Vector3(0f, 0.027f, (float)n * 0.052f), new Vector3(0.16f, 0.034f, 0.008f), new Color(0.62f, 0.45f, 0.25f));
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
						Color color2 = ((key == "appetix") ? new Color(0.18f, 0.55f, 0.28f) : new Color(0.98f, 0.12f, 0.48f));
						Models.Part(transform, (PrimitiveType)3, new Vector3(0f, 0.08f, 0f), new Vector3(0.115f, 0.16f, (key == "appetix") ? 0.045f : 0.018f), color2);
						float num5 = ((key == "appetix") ? (-0.024f) : (-0.011f));
						Models.Part(transform, (PrimitiveType)3, new Vector3(0f, 0.09f, num5), new Vector3(0.106f, 0.055f, 0.001f), (Color)((key == "appetix") ? Cream : new Color(1f, 0.83f, 0.12f)));
						Models.Label(transform, (key == "appetix") ? "APPÉTIX" : "POP!", new Vector3(0f, 0.08f, num5 - 0.002f), 0.005f, Color.white, (Vector2?)new Vector2(0.106f, 0.14f));
						if (key == "appetix")
						{
							Models.Part(transform, (PrimitiveType)3, new Vector3(0f, 0.159f, 0f), new Vector3(0.112f, 0.002f, 0.043f), Cream);
							Models.Part(transform, (PrimitiveType)3, new Vector3(0.055f, 0.08f, 0f), new Vector3(0.002f, 0.154f, 0.043f), new Color(0.11f, 0.39f, 0.2f));
							Models.Part(transform, (PrimitiveType)3, new Vector3(0f, 0.005f, 0f), new Vector3(0.111f, 0.003f, 0.043f), new Color(0.12f, 0.4f, 0.2f));
						}
						if (!(key == "poprocks"))
						{
							break;
						}
						for (int l = 0; l < 13; l++)
						{
							for (int m = -1; m <= 1; m += 2)
							{
								Models.Part(transform, (PrimitiveType)3, new Vector3((float)(l - 6) * 0.008f, 0.08f + (float)m * 0.08f, 0f), new Vector3(0.004f, 0.01f, 0.02f), new Color(0.8f, 0.07f, 0.33f));
							}
						}
						break;
					}
					if (key == "glow")
					{
						Lathe(transform, "Tube_lumineux", (Vector2[])(object)new Vector2[4]
						{
							new Vector2(0f, 0f),
							new Vector2(0.009f, 0.01f),
							new Vector2(0.009f, 0.22f),
							new Vector2(0f, 0.23f)
						}, new Color(0.27f, 1f, 0.035f));
						Cap(transform, 0.22f, 0.012f, new Color(0.08f, 0.09f, 0.08f), 0.014f);
						Cap(transform, 0.002f, 0.0105f, new Color(0.08f, 0.09f, 0.08f), 0.009f);
						Models.Part(transform, (PrimitiveType)3, new Vector3(0f, 0.241f, 0f), new Vector3(0.009f, 0.018f, 0.004f), new Color(0.07f, 0.09f, 0.07f));
						foreach (Renderer componentsInChild in val.GetComponentsInChildren<Renderer>())
						{
							if (((Object)componentsInChild).name == "Tube_lumineux")
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
						((Color)(ref val3))._002Ector(0.28f, 0.09f, 0.025f);
					}
					else if (flag5)
					{
						((Color)(ref val3))._002Ector(0.055f, 0.3f, 0.07f);
					}
					else if (key == "focalix")
					{
						((Color)(ref val3))._002Ector(0.91f, 0.36f, 0.025f);
					}
					else if (key == "paranox")
					{
						((Color)(ref val3))._002Ector(0.7f, 0.025f, 0.04f);
					}
					else if (flag2)
					{
						((Color)(ref val3))._002Ector(0.46f, 0.25f, 0.09f);
					}
					else
					{
						val3 = (flag3 ? new Color(0.04f, 0.07f, 0.1f) : new Color(0.65f, 0.77f, 0.83f));
					}
					profile = (Vector2[])(object)((flag2 || flag3 || flag6 || key == "focalix") ? new Vector2[6]
					{
						new Vector2(0f, 0f),
						new Vector2(num8 * 0.94f, 0.003f),
						new Vector2(num8, 0.009f),
						new Vector2(num8, num9 - 0.005f),
						new Vector2(num8 * 0.94f, num9),
						new Vector2(0f, num9)
					} : new Vector2[8]
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
					Ring(transform, "Joint_de_fond", num8 * 0.96f, 0.008f, 0.0015f, (Color)((flag2 || flag4) ? new Color(0.64f, 0.67f, 0.69f) : Color.Lerp(val3, Color.black, 0.18f)));
					if (!flag2 && !flag3 && !flag6 && key != "focalix")
					{
						Ring(transform, "Bague_de_col", num8 * 0.4f, num9 - 0.002f, 0.002f, val3);
					}
					y = num9;
					radius = num8 * ((flag2 || flag3 || flag6) ? 1.03f : ((key == "focalix") ? 1f : 0.5f));
					c = (Color)((!(key == "focalix")) ? ((flag3 || flag6) ? new Color(0.1f, 0.4f, 0.75f) : new Color(0.25f, 0.27f, 0.28f)) : Cream);
					Cap(transform, y, radius, c);
					if (flag3 || flag4)
					{
						Lathe(transform, "Borne", (Vector2[])(object)new Vector2[4]
						{
							new Vector2(0f, num9 + 0.018f),
							new Vector2(0.009f, num9 + 0.018f),
							new Vector2(0.009f, num9 + 0.027f),
							new Vector2(0f, num9 + 0.027f)
						}, Color.gray);
					}
					if (key == "cyclo" || key == "paranox")
					{
						Lathe(transform, "Embout", (Vector2[])(object)new Vector2[4]
						{
							new Vector2(0.018f, num9),
							new Vector2(0.014f, num9 + 0.035f),
							new Vector2(0.004f, num9 + 0.065f),
							new Vector2(0f, num9 + 0.065f)
						}, Color.white);
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
						Cap(transform, 0.002f, num8 * 1.02f, Color.gray, 0.004f);
						Cap(transform, num9 + 0.018f, num8 * 0.98f, new Color(0.66f, 0.68f, 0.7f), 0.002f);
						Ring(transform, "Sertissage_supérieur", num8 * 1.02f, num9 + 0.02f, 0.002f, new Color(0.72f, 0.74f, 0.76f));
						Ring(transform, "Rainure_du_couvercle", num8 * 0.79f, num9 + 0.0205f, 0.0007f, new Color(0.32f, 0.34f, 0.36f));
						Finish(Models.Part(transform, (PrimitiveType)3, new Vector3(0f, num9 + 0.023f, 0f), new Vector3(0.018f, 0.003f, 0.032f), new Color(0.68f, 0.69f, 0.71f)), 0.65f, 0.8f);
					}
					break;
				}
				break;
			}
		}
		val.transform.localRotation = Quaternion.Euler(0f, 180f, (float)((key == "glow") ? 35 : 0));
		return val;
	}
}
