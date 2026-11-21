using System;

namespace VarietesEtendues;

internal static class DemonWave
{
	internal static float[] Generate(int rate, float duration)
	{
		float[] array = new float[(int)((float)rate * duration)];
		System.Random random = new Random(666);
		double num = 0.0;
		double num2 = 0.0;
		for (int i = 0; i < array.Length; i++)
		{
			double num3 = (double)i / (double)rate;
			double num4 = System.Math.Sin(System.Math.PI * num3 / (double)duration);
			num4 = num4 * num4 * System.Math.Min(1.0, num3 / 0.15) * System.Math.Min(1.0, ((double)duration - num3) / 0.25);
			double num5 = 48.0 + 7.0 * System.Math.Sin(num3 * 2.7) + 3.0 * System.Math.Sin(num3 * 17.0);
			num += System.Math.PI * 2.0 * num5 / (double)rate;
			num2 = 0.94 * num2 + 0.06 * (random.NextDouble() * 2.0 - 1.0);
			double num6 = System.Math.Sin(num) * 0.45 + System.Math.Sin(num * 2.01) * 0.2 + System.Math.Sin(num * 3.04) * 0.1 + num2 * 0.8;
			array[i] = (float)(System.Math.Tanh(num6 * 1.8) * 0.5 * num4 * (0.8 + 0.2 * System.Math.Sin(num3 * 41.0)));
		}
		return array;
	}
}
