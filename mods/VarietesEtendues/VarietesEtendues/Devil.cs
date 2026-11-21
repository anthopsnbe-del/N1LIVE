using System.Collections.Generic;
using Il2CppInterop.Runtime.InteropTypes.Arrays;
using UnityEngine;

namespace VarietesEtendues;

internal static class Devil
{
	private static UnityEngine.Texture2D? smoke;

	internal static UnityEngine.Texture2D SmokeTexture
	{
		get
		{
			if ((UnityEngine.Object)(object)smoke != (UnityEngine.Object)null)
			{
				return smoke;
			}
			smoke = new Texture2D(64, 64, (UnityEngine.TextureFormat)4, false);
			UnityEngine.Color[] array = (UnityEngine.Color[])(object)new UnityEngine.Color[4096];
			for (int i = 0; i < 64; i++)
			{
				for (int j = 0; j < 64; j++)
				{
					float num = UnityEngine.Vector2.Distance(new Vector2((float)j / 63f, (float)i / 63f), new Vector2(0.5f, 0.5f)) * 2f;
					float num2 = UnityEngine.Mathf.Clamp01(1f - num);
					array[i * 64 + j] = new Color(1f, 1f, 1f, num2 * num2 * 0.65f);
				}
			}
			smoke.SetPixels((array));
			smoke.Apply();
			UnityEngine.Object.DontDestroyOnLoad((UnityEngine.Object)(object)smoke);
			return smoke;
		}
	}

	internal static void Apply(UnityEngine.Transform actor)
	{
		CharacterEffects.Add(actor, "vx_diable");
	}

	internal static void Clear(UnityEngine.Transform actor)
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

	internal static void DestroyOwned(UnityEngine.GameObject? root)
	{
		if ((UnityEngine.Object)(object)root == (UnityEngine.Object)null)
		{
			return;
		}
		System.Collections.Generic.HashSet<int> hashSet = new System.Collections.Generic.HashSet<int>();
		foreach (UnityEngine.Renderer componentsInChild in root.GetComponentsInChildren<UnityEngine.Renderer>(true))
		{
			foreach (UnityEngine.Material item in (Il2CppInterop.Runtime.InteropTypes.Arrays.Il2CppArrayBase<UnityEngine.Material>)(object)componentsInChild.sharedMaterials)
			{
				if ((UnityEngine.Object)(object)item != (UnityEngine.Object)null && hashSet.Add(((UnityEngine.Object)item).GetInstanceID()))
				{
					UnityEngine.Object.Destroy((UnityEngine.Object)(object)item);
				}
			}
		}
		foreach (UnityEngine.MeshFilter componentsInChild2 in root.GetComponentsInChildren<UnityEngine.MeshFilter>(true))
		{
			if ((UnityEngine.Object)(object)componentsInChild2.sharedMesh != (UnityEngine.Object)null && ((UnityEngine.Object)componentsInChild2.sharedMesh).name.StartsWith("VX_Procedural"))
			{
				UnityEngine.Object.Destroy((UnityEngine.Object)(object)componentsInChild2.sharedMesh);
			}
		}
		UnityEngine.Object.Destroy((UnityEngine.Object)(object)root);
	}
}
