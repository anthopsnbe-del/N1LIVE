using System.Collections.Generic;
using System.IO;
using System.Linq;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using UnityEngine;

namespace VarietesEtendues;

internal static class Models
{
	private static readonly Dictionary<string, Texture2D> textures = new Dictionary<string, Texture2D>();

	internal static Material Material(Color color)
	{
		//IL_0018: Unknown result type (might be due to invalid IL or missing references)
		//IL_001d: Unknown result type (might be due to invalid IL or missing references)
		//IL_001e: Unknown result type (might be due to invalid IL or missing references)
		//IL_0025: Expected O, but got Unknown
		return new Material(Shader.Find("Universal Render Pipeline/Lit") ?? Shader.Find("Standard"))
		{
			color = color
		};
	}

	internal static GameObject Part(Transform parent, PrimitiveType type, Vector3 pos, Vector3 scale, Color color)
	{
		//IL_0000: Unknown result type (might be due to invalid IL or missing references)
		//IL_0019: Unknown result type (might be due to invalid IL or missing references)
		//IL_0025: Unknown result type (might be due to invalid IL or missing references)
		//IL_0031: Unknown result type (might be due to invalid IL or missing references)
		GameObject obj = GameObject.CreatePrimitive(type);
		obj.transform.SetParent(parent, false);
		obj.transform.localPosition = pos;
		obj.transform.localScale = scale;
		obj.GetComponent<Renderer>().material = Material(color);
		Collider component = obj.GetComponent<Collider>();
		if ((Object)(object)component != (Object)null)
		{
			Object.DestroyImmediate((Object)(object)component);
		}
		return obj;
	}

	internal static Texture2D Texture(string text)
	{
		//IL_0062: Unknown result type (might be due to invalid IL or missing references)
		//IL_0069: Expected O, but got Unknown
		if (textures.TryGetValue(text, out Texture2D value))
		{
			return value;
		}
		string text2 = "VarietesEtendues.Textures." + TextureKey(text) + ".png";
		using Stream stream = typeof(Models).Assembly.GetManifestResourceStream(text2);
		if (stream == null)
		{
			throw new InvalidDataException("Texture manquante : " + text2);
		}
		using MemoryStream memoryStream = new MemoryStream();
		stream.CopyTo(memoryStream);
		Texture2D val = new Texture2D(2, 2, (TextureFormat)4, false);
		ImageConversion.LoadImage(val, (memoryStream.ToArray()));
		((Texture)val).filterMode = (FilterMode)1;
		((Texture)val).wrapMode = (TextureWrapMode)1;
		textures[text] = val;
		return val;
	}

	internal static GameObject Label(Transform parent, string text, Vector3 pos, float size, Color color, Vector2? dimensions = null)
	{
		//IL_002f: Unknown result type (might be due to invalid IL or missing references)
		//IL_006f: Unknown result type (might be due to invalid IL or missing references)
		//IL_0095: Unknown result type (might be due to invalid IL or missing references)
		//IL_00a4: Unknown result type (might be due to invalid IL or missing references)
		//IL_00bb: Unknown result type (might be due to invalid IL or missing references)
		//IL_00df: Unknown result type (might be due to invalid IL or missing references)
		//IL_00f2: Unknown result type (might be due to invalid IL or missing references)
		GameObject obj = GameObject.CreatePrimitive((PrimitiveType)5);
		((Object)obj).name = "Etiquette";
		Object.DestroyImmediate((Object)(object)obj.GetComponent<Collider>());
		obj.transform.SetParent(parent, false);
		obj.transform.localPosition = pos;
		float num = dimensions?.x ?? ((text == "666") ? 0.032f : Mathf.Min(0.075f, size * (float)text.Length * 9f));
		obj.transform.localScale = new Vector3(num, dimensions?.y ?? (num * 0.5f), 1f);
		Material val = (Material)((!(text == "666")) ? ((object)Material(Color.white)) : ((object)new Material(Shader.Find("Unlit/Transparent") ?? Shader.Find("Sprites/Default"))));
		val.mainTexture = (Texture)(object)Texture(text);
		val.color = Color.white;
		obj.GetComponent<Renderer>().material = val;
		return obj;
	}

	internal static string TextureKey(string s)
	{
		return string.Concat(from c in s.ToLowerInvariant()
			select char.IsLetterOrDigit(c) ? c : '_');
	}

	internal static GameObject Make(string key, string name)
	{
		return RealModels.Make(key, name);
	}
}
