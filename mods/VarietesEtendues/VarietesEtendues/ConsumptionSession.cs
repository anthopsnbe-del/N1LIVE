using System;
using System.Collections.Generic;

namespace VarietesEtendues;

internal sealed class ConsumptionSession
{
	private readonly Dictionary<int, string> products = new Dictionary<int, string>();

	internal void Consumed(int player, string? product)
	{
		if (string.IsNullOrEmpty(product))
		{
			products.Remove(player);
		}
		else
		{
			products[player] = product;
		}
	}

	internal bool Allows(int player, string? activeProduct)
	{
		if (activeProduct != null && products.TryGetValue(player, out string value))
		{
			return string.Equals(value, activeProduct, StringComparison.Ordinal);
		}
		return false;
	}

	internal void Clear(int player)
	{
		products.Remove(player);
	}

	internal void ClearAll()
	{
		products.Clear();
	}
}
