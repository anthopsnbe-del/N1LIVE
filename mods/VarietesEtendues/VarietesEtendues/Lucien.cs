using System;
using S1API.Entities;
using S1API.Entities.Relation;
using S1API.Entities.Supplier;
using UnityEngine;

namespace VarietesEtendues;

public sealed class Lucien : S1API.Entities.NPC
{
	public static string NPCId => "vx_lucien";

	public override bool IsSupplier => true;

	public override bool IsPhysical => true;

	protected override void ConfigurePrefab(S1API.Entities.NPCPrefabBuilder builder)
	{
		builder.WithIdentity(NPCId, "Lucien", "").WithAppearanceDefaults((System.Action<S1API.Entities.NPCPrefabBuilder.AvatarDefaultsBuilder>)delegate(S1API.Entities.NPCPrefabBuilder.AvatarDefaultsBuilder a)
		{
			a.SkinColor = new Color32((byte)213, (byte)168, (byte)126, byte.MaxValue);
			a.LeftEyeLidColor = (new Color32((byte)213, (byte)168, (byte)126, byte.MaxValue));
			a.RightEyeLidColor = (new Color32((byte)213, (byte)168, (byte)126, byte.MaxValue));
			a.HairPath = "Avatar/Hair/longslicked/LongSlicked";
			a.HairColor = new Color(0.16f, 0.1f, 0.055f);
			a.LeftEye = (0.62f, 0.5f);
			a.RightEye = (0.62f, 0.5f);
			a.EyebrowThickness = 0.8f;
			a.Weight = 0.43f;
			a.WithFaceLayer("Avatar/Layers/Face/Face_SlightSmile", UnityEngine.Color.white).WithFaceLayer("Avatar/Layers/Face/FacialHair_Stubble", new Color(0.24f, 0.16f, 0.09f)).WithBodyLayer("Avatar/Layers/Top/Buttonup", new Color(0.16f, 0.33f, 0.25f))
				.WithBodyLayer("Avatar/Layers/Bottom/Jeans", new Color(0.12f, 0.18f, 0.25f));
		}).WithRelationshipDefaults((System.Action<S1API.Entities.Relation.NPCRelationshipDataBuilder>)delegate(S1API.Entities.Relation.NPCRelationshipDataBuilder r)
		{
			r.SetUnlocked(true).WithDelta(5f);
		})
			.WithSupplierDefaults((System.Action<S1API.Entities.Supplier.SupplierDataBuilder>)delegate(S1API.Entities.Supplier.SupplierDataBuilder s)
			{
				s.WithPersistentId("vx_lucien_supplier").WithOrderLimits(2000f, 10000f).WithUnlockHint("The Comptoir des Variétés is available by phone.");
				Entry[] catalogue = Mod.Catalogue;
				foreach (Entry entry in catalogue)
				{
					s.WithDeliveryItem("vx_" + entry.Key);
				}
				Crops.Variety[] varieties = Crops.Varieties;
				foreach (Crops.Variety v in varieties)
				{
					s.WithDeliveryItem(Crops.SeedId(v));
				}
			});
	}
}
