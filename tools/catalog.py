"""Génère le catalogue complet : 50 fondatrices + 500 hybrides.

Contraintes tenues :
  - les 14 fondatrices d'origine gardent leur identifiant, leur nom, leurs
    statistiques et leur graine : les sauvegardes existantes restent valides ;
  - les 91 croisements d'origine figurent toujours parmi les 500 recettes ;
  - chaque variété porte 2 à 4 teintes, et aucune combinaison n'est répétée.

Usage :
  python3 tools/catalog.py            # rapport, n'écrit rien
  python3 tools/catalog.py --write    # met à jour assets/plantation/catalog.json
"""
import colorsys, itertools, json, math, os, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
CATALOG = os.path.join(ROOT, 'assets', 'plantation', 'catalog.json')

HYBRID_TARGET = 500
FOUNDER_TARGET = 50

# Paliers de rareté : indice, libellé, nombre de teintes, part des hybrides.
TIERS = [
    (0, 'Commune',    2, .46),
    (1, 'Rare',       3, .30),
    (2, 'Épique',     3, .17),
    (3, 'Légendaire', 4, .06),
    (4, 'Mythique',   4, .01),
]
TIER_NAME = {t[0]: t[1] for t in TIERS}
TIER_TONES = {t[0]: t[2] for t in TIERS}

# 36 nouvelles fondatrices. Préfixes et suffixes doivent rester uniques :
# le nom d'un hybride est « préfixe du parent A + suffixe du parent B ».
NEW_FOUNDERS = [
    ('safran',     'Safran Doré',          'Safran',     'Dorée',         1),
    ('cobalt',     'Cobalt Profond',       'Cobalt',     'Profonde',      1),
    ('jade',       'Jade Impérial',        'Jade',       'Impériale',     2),
    ('cerise',     'Cerise Noire',         'Cerise',     'Cerisée',       0),
    ('vanille',    'Vanille Blanche',      'Vanille',    'Vanillée',      0),
    ('corail',     'Corail Marin',         'Corail',     'Corallienne',   1),
    ('ivoire',     'Ivoire Ancien',        'Ivoire',     'Ivoirine',      0),
    ('basalte',    'Basalte Fumé',         'Basalte',    'Fumée',         1),
    ('lilas',      'Lilas Nordique',       'Lilas',      'Lilacée',       0),
    ('cuivre',     'Cuivre Rouge',         'Cuivre',     'Cuivrée',       1),
    ('opale',      'Opale Laiteuse',       'Opale',      'Opaline',       2),
    ('tempete',    'Tempête Grise',        'Tempête',    'Orageuse',      1),
    ('nectar',     'Nectar des Cimes',     'Nectar',     'Nectarine',     0),
    ('prisme',     'Prisme Éclaté',        'Prisme',     'Prismatique',   3),
    ('cendre',     'Cendre Volcanique',    'Cendre',     'Cendrée',       1),
    ('turquoise',  'Turquoise Abyssale',   'Turquoise',  'Abyssale',      2),
    ('grenat',     'Grenat Sombre',        'Grenat',     'Grenatée',      1),
    ('pollen',     "Pollen d'Été",         'Pollen',     'Estivale',      0),
    ('brise',      'Brise Saline',         'Brise',      'Saline',        0),
    ('saphir',     'Saphir Voilé',         'Saphir',     'Saphirine',     2),
    ('ecorce',     'Écorce Fauve',         'Écorce',     'Fauve',         0),
    ('rubis',      'Rubis Ardent',         'Rubis',      'Ardente',       2),
    ('muscat',     'Muscat Ambré',         'Muscat',     'Muscatée',      0),
    ('ombre',      'Ombre Douce',          'Ombre',      'Ombrée',        1),
    ('silex',      'Silex Blanc',          'Silex',      'Silicée',       1),
    ('papaye',     'Papaye Solaire',       'Papaye',     'Solaire',       0),
    ('indigo',     'Indigo Profond',       'Indigo',     'Indigotée',     2),
    ('resine',     "Résine d'Or",          'Résine',     'Résineuse',     3),
    ('brasier',    'Brasier Calme',        'Brasier',    'Incandescente', 2),
    ('eclipse',    'Éclipse Totale',       'Éclipse',    'Éclipsée',      3),
    ('nacre',      'Nacre Marine',         'Nacre',      'Nacrée',        1),
    ('absinthe',   'Absinthe Ancienne',    'Absinthe',   'Absinthée',     0),
    ('fougere',    'Fougère Sylvestre',    'Fougère',    'Fougueuse',     0),
    ('meteore',    'Météore Rouge',        'Météore',    'Météorique',    2),
    ('caramel',    'Caramel Brûlé',        'Caramel',    'Caramélisée',   0),
    ('lavande',    'Lavande Haute',        'Lavande',    'Lavandine',     1),
]

# Couleur exacte des 14 fondatrices d'origine : les joueurs les reconnaissent,
# elles ne bougent plus. Teinte, saturation et clarté.
LEGACY_HSL = {
    'emeraude':  (0.2782, 0.6186, 0.4216),
    'nebuleuse': (0.7550, 0.5200, 0.4412),
    'citron':    (0.1918, 0.7817, 0.5510),
    'velours':   (0.7991, 0.4979, 0.4608),
    'menthe':    (0.4345, 0.5234, 0.5804),
    'ambre':     (0.1073, 0.7224, 0.4804),
    'rose':      (0.9308, 0.5514, 0.5804),
    'givre':     (0.4770, 0.2028, 0.7196),
    'mangue':    (0.1284, 0.7469, 0.5196),
    'onyx':      (0.6583, 0.4225, 0.2784),
    'diesel':    (0.2343, 0.3988, 0.3392),
    'peche':     (0.0556, 0.6186, 0.6196),
    'pin':       (0.3739, 0.4483, 0.3412),
    'orchidee':  (0.8603, 0.4213, 0.4608),
}


def hex_of(h, s, l):
    r, g, b = colorsys.hls_to_rgb(h % 1.0, max(0., min(1., l)), max(0., min(1., s)))
    return '#%02x%02x%02x' % tuple(round(c * 255) for c in (r, g, b))


def lab(hexs):
    h = hexs.lstrip('#')
    r, g, b = [int(h[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    f = lambda c: c / 12.92 if c <= .04045 else ((c + .055) / 1.055) ** 2.4
    r, g, b = f(r), f(g), f(b)
    x, y, z = r * .4124 + g * .3576 + b * .1805, r * .2126 + g * .7152 + b * .0722, r * .0193 + g * .1192 + b * .9505
    x, y, z = x / .9505, y, z / 1.089
    g2 = lambda t: t ** (1 / 3) if t > .008856 else 7.787 * t + 16 / 116
    fx, fy, fz = g2(x), g2(y), g2(z)
    return (116 * fy - 16, 500 * (fx - fy), 200 * (fy - fz))


def stable(*parts):
    """Entier déterministe, indépendant du hachage aléatoire de Python."""
    value = 2166136261
    for part in parts:
        for ch in str(part):
            value = ((value ^ ord(ch)) * 16777619) & 0xFFFFFFFF
    return value


def mix_hue(a, b, weight_a=.5):
    d = ((b - a + .5) % 1.0) - .5
    return (a + d * (1 - weight_a)) % 1.0


# ---------------------------------------------------------------- fondatrices
legacy = {v['id']: v for v in json.load(open(CATALOG))}
founders = []

for vid, hsl in LEGACY_HSL.items():
    old = legacy[vid]
    founders.append({
        'id': vid, 'name': old['name'], 'prefix': old['prefix'], 'suffix': old['suffix'],
        'height': old['height'], 'width': old['width'], 'duration': old['duration'],
        'price': old['price'], 'reward': old['reward'], 'seed': old['seed'],
        'tier': 0, 'hsl_fixed': hsl,
    })

for index, (vid, name, prefix, suffix, tier) in enumerate(NEW_FOUNDERS):
    span = stable('founder', vid)
    founders.append({
        'id': vid, 'name': name, 'prefix': prefix, 'suffix': suffix,
        'height': round(.82 + (span % 70) / 100, 2),
        'width': round(.55 + (span // 70 % 45) / 100, 2),
        'duration': 130 + tier * 45 + (span % 9) * 10,
        'price': [30, 95, 190, 340][tier] + (span % 7) * 5,
        'reward': 48 + tier * 26 + (span % 11) * 2,
        'seed': 2500 + index * 127,
        'tier': tier,
        'hue': ((index * 0.6180339887) + 0.11) % 1.0,   # angle d'or : teintes bien réparties
    })

assert len(founders) == FOUNDER_TARGET, len(founders)
assert len({f['prefix'] for f in founders}) == FOUNDER_TARGET, 'préfixes en double'
assert len({f['suffix'] for f in founders}) == FOUNDER_TARGET, 'suffixes en double'

by_id = {f['id']: f for f in founders}

# ------------------------------------------------------------------- recettes
legacy_pairs = {tuple(sorted(v['parents'])) for v in legacy.values() if v['parents']}
pairs = sorted(legacy_pairs)
degree = {f['id']: 0 for f in founders}
for a, b in pairs:
    degree[a] += 1
    degree[b] += 1

remaining = [p for p in itertools.combinations(sorted(by_id), 2) if p not in legacy_pairs]
while len(pairs) < HYBRID_TARGET:
    # On complète en équilibrant : la fondatrice la moins servie passe en premier.
    remaining.sort(key=lambda p: (degree[p[0]] + degree[p[1]], p))
    a, b = remaining.pop(0)
    pairs.append((a, b))
    degree[a] += 1
    degree[b] += 1

assert len(pairs) == HYBRID_TARGET
assert legacy_pairs <= set(pairs), 'un croisement historique a disparu'

# ---------------------------------------------------------------- raretés
quota = {}
counted = 0
for tier, _, _, share in TIERS[:-1]:
    quota[tier] = round(HYBRID_TARGET * share)
    counted += quota[tier]
quota[TIERS[-1][0]] = HYBRID_TARGET - counted

ranked = sorted(pairs, key=lambda p: stable('tier', p[0], p[1]))
tier_of_pair = {}
cursor = 0
for tier in sorted(quota):
    for pair in ranked[cursor:cursor + quota[tier]]:
        tier_of_pair[pair] = tier
    cursor += quota[tier]

# ------------------------------------------------------------------ couleurs
# Chaque variété occupe une case distincte de la grille teinte × saturation ×
# clarté : deux variétés ne peuvent donc pas partager la même couleur dominante.
HUE_CELLS, SAT_CELLS, LIGHT_CELLS = 96, 9, 8
taken = set()


def claim(h, s, l):
    cell = (int(h * HUE_CELLS) % HUE_CELLS, min(SAT_CELLS - 1, int(s * SAT_CELLS)),
            min(LIGHT_CELLS - 1, int(l * LIGHT_CELLS)))
    if cell not in taken:
        taken.add(cell)
        return h, s, l
    for radius in range(1, 60):
        for dh in (0, 1, -1, 2, -2, 3, -3):
            for ds in (0, 1, -1):
                for dl in (0, 1, -1):
                    if abs(dh) + abs(ds) + abs(dl) != radius:
                        continue
                    probe = ((cell[0] + dh) % HUE_CELLS, cell[1] + ds, cell[2] + dl)
                    if not (0 <= probe[1] < SAT_CELLS and 0 <= probe[2] < LIGHT_CELLS):
                        continue
                    if probe in taken:
                        continue
                    taken.add(probe)
                    return ((probe[0] + .5) / HUE_CELLS, (probe[1] + .5) / SAT_CELLS,
                            (probe[2] + .5) / LIGHT_CELLS)
    raise RuntimeError('plus de place dans la grille de couleurs')


def tones_for(h, s, l, count, salt):
    """2 à 4 teintes qui restent parentes : dominante, nuance, ombre, accent."""
    span = stable('tones', salt)
    out = [hex_of(h, s, l)]
    shift = .045 + (span % 30) / 1000
    out.append(hex_of(h + shift * (1 if span % 2 else -1), min(.92, s * 1.1), max(.16, l * .72)))
    if count >= 3:
        out.append(hex_of(h - shift * 1.8, max(.2, s * .82), min(.78, l * 1.28)))
    if count >= 4:
        # Accent d'une vraie tête bigarrée : anthocyane pourpre ou rouille,
        # celui des deux qui tranche le plus avec la dominante.
        violet, rust = .845, .065
        to_violet = abs(((violet - h + .5) % 1.0) - .5)
        to_rust = abs(((rust - h + .5) % 1.0) - .5)
        accent = (violet if to_violet >= to_rust else rust) + ((span // 7 % 9) - 4) / 220
        out.append(hex_of(accent % 1.0, min(.7, s * .82), max(.24, min(.5, l * .78))))
    return out


rows = []
for founder in founders:
    span = stable('color', founder['id'])
    tier = founder['tier']
    if 'hsl_fixed' in founder:
        h, s, l = claim(*founder['hsl_fixed'])
    else:
        sat = .46 + (span % 26) / 100 + tier * .04
        light = .34 + (span // 26 % 22) / 100
        h, s, l = claim(founder['hue'], min(.86, sat), min(.62, light))
    founder['hsl'] = (h, s, l)
    rows.append({
        'id': founder['id'], 'name': founder['name'],
        'tones': tones_for(h, s, l, TIER_TONES[tier], founder['id']),
        'height': founder['height'], 'width': founder['width'], 'duration': founder['duration'],
        'price': founder['price'], 'reward': founder['reward'], 'seed': founder['seed'],
        'parents': [], 'tier': tier, 'rarity': TIER_NAME[tier],
        'prefix': founder['prefix'], 'suffix': founder['suffix'],
    })

for index, (a, b) in enumerate(sorted(pairs)):
    pa, pb = by_id[a], by_id[b]
    tier = tier_of_pair[(a, b)]
    span = stable('hybride', a, b)
    ha, sa, la = pa['hsl']
    hb, sb, lb = pb['hsl']
    jitter = (span % 1000) / 1000
    arc = abs(((hb - ha + .5) % 1.0) - .5)
    if arc > .34:
        lead, other = (pa['hsl'], pb['hsl']) if jitter < .5 else (pb['hsl'], pa['hsl'])
        h = mix_hue(lead[0], other[0], .74)
        s = lead[1] * .7 + other[1] * .3
        l = lead[2] * .6 + other[2] * .4
    else:
        h = mix_hue(ha, hb, .5) + (jitter - .5) * .05
        s = max(sa, sb) * .94
        l = (la + lb) / 2
    s = max(.24, min(.88, s * (.9 + jitter * .2) + tier * .03))
    l = max(.22, min(.66, l * (.92 + jitter * .16)))
    h, s, l = claim(h % 1.0, s, l)
    rows.append({
        'id': f'{a}--{b}', 'name': f"{pa['prefix']} {pb['suffix']}",
        'tones': tones_for(h, s, l, TIER_TONES[tier], f'{a}--{b}'),
        'height': round((pa['height'] + pb['height']) / 2, 3),
        'width': round((pa['width'] + pb['width']) / 2, 3),
        'duration': int((pa['duration'] + pb['duration']) / 2 * (1 + tier * .12)),
        'price': 0,
        'reward': int((pa['reward'] + pb['reward']) / 2 * (1 + tier * .3)) + 6,
        'seed': 9000 + index * 31,
        'parents': [a, b], 'tier': tier, 'rarity': TIER_NAME[tier],
    })

# -------------------------------------------------------- écartement perceptuel
# La grille garantit des cases distinctes, mais deux cases voisines dans des
# dimensions différentes peuvent rester indistinguables à l'œil : on relocalise
# les variétés fautives jusqu'à un écart minimum en espace Lab.
MIN_DELTA = 5.0
LEGACY_IDS = set(LEGACY_HSL)
index = {r['id']: r for r in rows}
hsl_of = {}
for r in rows:
    h, l, s_ = colorsys.rgb_to_hls(*[int(r['tones'][0][i:i + 2], 16) / 255 for i in (1, 3, 5)])
    hsl_of[r['id']] = (h, s_, l)


def cell_of(hsl):
    h, s_, l = hsl
    return (int(h * HUE_CELLS) % HUE_CELLS, min(SAT_CELLS - 1, int(s_ * SAT_CELLS)),
            min(LIGHT_CELLS - 1, int(l * LIGHT_CELLS)))


def cell_color(cell):
    return ((cell[0] + .5) / HUE_CELLS, (cell[1] + .5) / SAT_CELLS, (cell[2] + .5) / LIGHT_CELLS)


def relocate(target, others_lab):
    """Première case libre dont la couleur respecte l'écart minimum partout."""
    base = cell_of(hsl_of[target])
    taken.discard(base)
    for radius in range(1, 40):
        for dh in range(-radius, radius + 1):
            for ds in range(-radius, radius + 1):
                for dl in range(-radius, radius + 1):
                    if max(abs(dh), abs(ds), abs(dl)) != radius:
                        continue
                    probe = ((base[0] + dh) % HUE_CELLS, base[1] + ds, base[2] + dl)
                    if not (0 <= probe[1] < SAT_CELLS and 0 <= probe[2] < LIGHT_CELLS):
                        continue
                    if probe in taken:
                        continue
                    h, s_, l = cell_color(probe)
                    candidate = lab(hex_of(h, s_, l))
                    if all(math.dist(candidate, other) >= MIN_DELTA for other in others_lab):
                        taken.add(probe)
                        hsl_of[target] = (h, s_, l)
                        row = index[target]
                        row['tones'] = tones_for(h, s_, l, TIER_TONES[row['tier']], target)
                        return True
    taken.add(base)
    return False


moved = 0
for _ in range(600):
    labs_now = {r['id']: lab(r['tones'][0]) for r in rows}
    order = sorted(labs_now, key=lambda i: labs_now[i][0])
    worst_delta, worst_pair = None, None
    for i, vid in enumerate(order):
        for other in order[i + 1:]:
            if labs_now[other][0] - labs_now[vid][0] > MIN_DELTA:
                break
            d = math.dist(labs_now[vid], labs_now[other])
            if worst_delta is None or d < worst_delta:
                worst_delta, worst_pair = d, (vid, other)
    if worst_delta is None or worst_delta >= MIN_DELTA:
        break
    a, b = worst_pair
    # On ne bouge jamais une fondatrice historique : les joueurs la reconnaissent.
    target = b if a in LEGACY_IDS else a
    if target in LEGACY_IDS:
        target = b
    others = [labs_now[i] for i in labs_now if i != target]
    if not relocate(target, others):
        break
    moved += 1

# Champs dérivés attendus par le rendu 3D et l'interface.
for row in rows:
    tones = row['tones']
    row['bud'] = tones[0]
    h, l, s = colorsys.rgb_to_hls(*[int(tones[0][i:i + 2], 16) / 255 for i in (1, 3, 5)])
    offset = ((h - .265 + .5) % 1.0) - .5
    row['leaf'] = hex_of((.265 + max(-.06, min(.06, offset * .25))) % 1.0, .36 + s * .14, .25 + l * .14)
    warm = .55 < h < .95 and s > .4
    span = stable('pistil', row['id'])
    row['pistil'] = hex_of(((.93 if warm else .035) + (span % 55) / 900) % 1.0,
                           .48 + (span % 30) / 100, .62 + (span % 17) / 130)

# ------------------------------------------------------------------- contrôles
ids = [r['id'] for r in rows]
assert len(set(ids)) == len(ids), 'identifiants en double'
names = [r['name'] for r in rows]
assert len(set(names)) == len(names), 'noms en double : ' + str([n for n in names if names.count(n) > 1][:4])
assert len({tuple(r['tones']) for r in rows}) == len(rows), 'combinaisons de teintes en double'
for r in rows:
    for parent in r['parents']:
        assert parent in by_id, parent
for old_id, old in legacy.items():
    assert old_id in set(ids), f'variété disparue : {old_id}'

labs = {r['id']: lab(r['bud']) for r in rows}
worst = min((math.dist(labs[x], labs[y]), x, y) for x, y in itertools.combinations(ids, 2))
tone_counts = {}
for r in rows:
    tone_counts[len(r['tones'])] = tone_counts.get(len(r['tones']), 0) + 1
tiers = {}
for r in rows:
    tiers.setdefault(r['rarity'], []).append(r)

print(f"{len(rows)} variétés : {sum(1 for r in rows if not r['parents'])} fondatrices "
      f"+ {sum(1 for r in rows if r['parents'])} hybrides")
print(f"couleurs dominantes distinctes : {len({r['bud'] for r in rows})}")
print(f"ΔE minimum : {worst[0]:.1f}  ({worst[1]} / {worst[2]}) — {moved} variétés écartées")
print("teintes par variété :", {f'{k} couleurs': v for k, v in sorted(tone_counts.items())})
print("raretés :", {k: len(v) for k, v in sorted(tiers.items(), key=lambda kv: kv[1][0]['tier'])})
print(f"recettes par fondatrice : min {min(degree.values())}, max {max(degree.values())}")

if '--write' in sys.argv:
    json.dump(rows, open(CATALOG, 'w'), ensure_ascii=False, indent=1)
    print('catalog.json mis à jour')
