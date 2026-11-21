using System.Collections.Generic;
using System.IO;
using System.Linq;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using UnityEngine;

namespace VarietesEtendues;

internal static class Models
{
	private static readonly System.Collections.Generic.Dictionary<string, UnityEngine.Texture2D> textures = new System.Collections.Generic.Dictionary<string, UnityEngine.Texture2D>();

	internal static UnityEngine.Material Material(UnityEngine.Color color)
	{
		return new Material(UnityEngine.Shader.Find("Universal Render Pipeline/Lit") ?? Shader.Find("Standard"))
		{
			color = color
		};
	}

	internal static UnityEngine.GameObject Part(UnityEngine.Transform parent, UnityEngine.PrimitiveType type, UnityEngine.Vector3 pos, UnityEngine.Vector3 scale, UnityEngine.Color color)
	{
		UnityEngine.GameObject obj = UnityEngine.GameObject.CreatePrimitive(type);
		obj.transform.SetParent(parent, false);
		obj.transform.localPosition = pos;
		obj.transform.localScale = scale;
		obj.GetComponent<UnityEngine.Renderer>().material = Material(color);
		UnityEngine.Collider component = obj.GetComponent<UnityEngine.Collider>();
		if ((UnityEngine.Object)(object)component != (UnityEngine.Object)null)
		{
			UnityEngine.Object.DestroyImmediate((UnityEngine.Object)(object)component);
		}
		return obj;
	}

	internal static UnityEngine.Texture2D Texture(string text)
	{
		if (textures.TryGetValue(text, out UnityEngine.Texture2D value))
		{
			return value;
		}
		string text2 = "VarietesEtendues.Textures." + TextureKey(text) + ".png";
		using System.IO.Stream stream = typeof(Models).Assembly.GetManifestResourceStream(text2);
		if (stream == null)
		{
			throw new InvalidDataException("UnityEngine.Texture manquante : " + text2);
		}
		using System.IO.MemoryStream memoryStream = new MemoryStream();
		stream.CopyTo(memoryStream);
		UnityEngine.Texture2D val = new Texture2D(2, 2, (UnityEngine.TextureFormat)4, false);
		UnityEngine.ImageConversion.LoadImage(val, (memoryStream.ToArray()));
		((UnityEngine.Texture)val).filterMode = (UnityEngine.FilterMode)1;
		((UnityEngine.Texture)val).wrapMode = (UnityEngine.TextureWrapMode)1;
		textures[text] = val;
		return val;
	}

	internal static UnityEngine.GameObject Label(UnityEngine.Transform parent, string text, UnityEngine.Vector3 pos, float size, UnityEngine.Color color, UnityEngine.Vector2? dimensions = null)
	{
		UnityEngine.GameObject obj = UnityEngine.GameObject.CreatePrimitive((UnityEngine.PrimitiveType)5);
		((UnityEngine.Object)obj).name = "Etiquette";
		UnityEngine.Object.DestroyImmediate((UnityEngine.Object)(object)obj.GetComponent<UnityEngine.Collider>());
		obj.transform.SetParent(parent, false);
		obj.transform.localPosition = pos;
		float num = dimensions?.x ?? ((text == "666") ? 0.032f : UnityEngine.Mathf.Min(0.075f, size * (float)text.Length * 9f));
		obj.transform.localScale = new Vector3(num, dimensions?.y ?? (num * 0.5f), 1f);
		UnityEngine.Material val = (UnityEngine.Material)((!(text == "666")) ? ((object)Material(UnityEngine.Color.white)) : ((object)new Material(UnityEngine.Shader.Find("Unlit/Transparent") ?? Shader.Find("Sprites/Default"))));
		val.mainTexture = (UnityEngine.Texture)(object)Texture(text);
		val.color = UnityEngine.Color.white;
		obj.GetComponent<UnityEngine.Renderer>().material = val;
		return obj;
	}

	internal static string TextureKey(string s)
	{
		return string.Concat(from c in s.ToLowerInvariant()
			select char.IsLetterOrDigit(c) ? c : '_');
	}

	internal static UnityEngine.GameObject Make(string key, string name)
	{
		return RealModels.Make(key, name);
	}
}
