namespace VarietesEtendues;

internal readonly record struct EffectTuning(float Speed = 1f, float Jump = 1f, float Gravity = 1f, float Drain = 1f, float Regen = 1f, float Acceleration = 1f, float Crouch = 1f, float DamageTaken = 1f, float Strength = 1f, float Fall = 1f, float Animation = 1f, bool God = false)
{
	internal static EffectTuning Neutral => new EffectTuning(1f, 1f, 1f, 1f, 1f, 1f, 1f, 1f, 1f, 1f, 1f, God: false);
}
