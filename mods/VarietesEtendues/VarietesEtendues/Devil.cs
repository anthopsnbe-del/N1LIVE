using System.Collections.Generic;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using UnityEngine;

namespace VarietesEtendues;

internal static class Devil
{
	private static Texture2D? smoke;

	internal static Texture2D SmokeTexture
	{
		get
		{
			//IL_0019: Unknown result type (might be due to invalid IL or missing references)
			//IL_0023: Expected O, but got Unknown
			//IL_0046: Unknown result type (might be due to invalid IL or missing references)
			//IL_0055: Unknown result type (might be due to invalid IL or missing references)
			//IL_0095: Unknown result type (might be due to invalid IL or missing references)
			//IL_009a: Unknown result type (might be due to invalid IL or missing references)
			if ((Object)(object)smoke != (Object)null)
			{
				return smoke;
			}
			smoke = new Texture2D(64, 64, (TextureFormat)4, false);
			Color[] array = (Color[])(object)new Color[4096];
			for (int i = 0; i < 64; i++)
			{
				for (int j = 0; j < 64; j++)
				{
					float num = Vector2.Distance(new Vector2((float)j / 63f, (float)i / 63f), new Vector2(0.5f, 0.5f)) * 2f;
					float num2 = Mathf.Clamp01(1f - num);
					array[i * 64 + j] = new Color(1f, 1f, 1f, num2 * num2 * 0.65f);
				}
			}
			smoke.SetPixels(Il2CppStructArray<Color>.op_Implicit(array));
			smoke.Apply();
			Object.DontDestroyOnLoad((Object)(object)smoke);
			return smoke;
		}
	}

	internal static void Apply(Transform actor)
	{
		CharacterEffects.Add(actor, "vx_diable");
	}

	internal static void Clear(Transform actor)
	{
		CharacterEffects.Remove(actor, "vx_diable");
	}

	internal static void Tick()
	{
		CharacterEffects.Tick();
	}

	internal static void ClearAll()
	{
		CharacterEffects.ClearAll();
	}

	internal static void DestroyOwned(GameObject? root)
	{
		if ((Object)(object)root == (Object)null)
		{
			return;
		}
		HashSet<int> hashSet = new HashSet<int>();
		foreach (Renderer componentsInChild in root.GetComponentsInChildren<Renderer>(true))
		{
			foreach (Material item in (Il2CppArrayBase<Material>)(object)componentsInChild.sharedMaterials)
			{
				if ((Object)(object)item != (Object)null && hashSet.Add(((Object)item).GetInstanceID()))
				{
					Object.Destroy((Object)(object)item);
				}
			}
		}
		foreach (MeshFilter componentsInChild2 in root.GetComponentsInChildren<MeshFilter>(true))
		{
			if ((Object)(object)componentsInChild2.sharedMesh != (Object)null && ((Object)componentsInChild2.sharedMesh).name.StartsWith("VX_Procedural"))
			{
				Object.Destroy((Object)(object)componentsInChild2.sharedMesh);
			}
		}
		Object.Destroy((Object)(object)root);
	}
}
