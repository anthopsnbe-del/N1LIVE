using System;

namespace VarietesEtendues;

public sealed class Entry
{
	public string Key { get; set; } = "";


	public string Name { get; set; } = "";


	public string EnglishName { get; set; } = "";


	public string GermanName { get; set; } = "";


	public string SpanishName { get; set; } = "";


	public string DisplayName => Localization.Text(Name, EnglishName, Or(GermanName, EnglishName), Or(SpanishName, EnglishName));

	private static string Or(string value, string fallback)
	{
		return string.IsNullOrWhiteSpace(value) ? fallback : value;
	}

	public string Effect { get; set; } = "";


	public string Rank { get; set; } = "";


	public int Price { get; set; }

	public string[][] Rules { get; set; } = Array.Empty<string[]>();

}
