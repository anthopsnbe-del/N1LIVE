"""Génère une palette distincte pour les 105 variétés.
   Seuls bud / leaf / pistil sont réécrits : règles de jeu, prix, durées et
   parents restent intacts."""
import json, colorsys, math, itertools, os, sys

CATALOG = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'assets', 'plantation', 'catalog.json')

# Les 14 fondatrices sont choisies à la main : leur nom annonce leur couleur.
FOUNDERS = {
 'emeraude':  (0.278, 0.62, 0.42),   # vert émeraude franc
 'nebuleuse': (0.755, 0.52, 0.44),   # violet cosmique
 'citron':    (0.192, 0.78, 0.55),   # jaune-vert acide
 'velours':   (0.800, 0.50, 0.46),   # pourpre velours
 'menthe':    (0.435, 0.52, 0.58),   # menthe claire
 'ambre':     (0.108, 0.72, 0.48),   # ambre solaire
 'rose':      (0.930, 0.55, 0.58),   # rose profond
 'givre':     (0.480, 0.20, 0.72),   # givre argenté
 'mangue':    (0.128, 0.75, 0.52),   # mangue dorée
 'onyx':      (0.660, 0.42, 0.28),   # bleu-noir
 'diesel':    (0.235, 0.40, 0.34),   # olive diesel
 'peche':     (0.055, 0.62, 0.62),   # pêche
 'pin':       (0.375, 0.45, 0.34),   # vert pin sombre
 'orchidee':  (0.860, 0.42, 0.46),   # orchidée mauve
}

def hex_of(h, s, l):
    r, g, b = colorsys.hls_to_rgb(h % 1.0, max(0, min(1, l)), max(0, min(1, s)))
    return '#%02x%02x%02x' % tuple(round(c * 255) for c in (r, g, b))

def lab(hexs):
    h = hexs.lstrip('#')
    r, g, b = [int(h[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    f = lambda c: c / 12.92 if c <= .04045 else ((c + .055) / 1.055) ** 2.4
    r, g, b = f(r), f(g), f(b)
    x, y, z = (r*.4124 + g*.3576 + b*.1805), (r*.2126 + g*.7152 + b*.0722), (r*.0193 + g*.1192 + b*.9505)
    x, y, z = x/.9505, y, z/1.089
    g2 = lambda t: t ** (1/3) if t > .008856 else 7.787 * t + 16/116
    fx, fy, fz = g2(x), g2(y), g2(z)
    return (116*fy - 16, 500*(fx - fy), 200*(fy - fz))

def mix_hue(a, b, weight_a=.5):
    """Moyenne circulaire : évite le gris quand on mélange deux teintes opposées."""
    d = ((b - a + .5) % 1.0) - .5
    return (a + d * (1 - weight_a)) % 1.0

def hybrid_hsl(pa, pb, key):
    ha, sa, la = pa
    hb, sb, lb = pb
    seed = sum(ord(c) * (i + 7) for i, c in enumerate(key))
    jitter = ((seed * 2654435761) % 1000) / 1000.0
    arc = abs(((hb - ha + .5) % 1.0) - .5)
    if arc > .34:
        # Teintes quasi opposées : la moyenne circulaire partirait dans une couleur
        # étrangère aux deux parents. Le parent le plus saturé domine, l'autre l'infléchit.
        # Le parent dominant est choisi par le croisement lui-même, pas par sa
        # saturation : sinon les variétés les plus vives écraseraient la collection.
        lead, other = ((ha, sa, la), (hb, sb, lb)) if jitter < .5 else ((hb, sb, lb), (ha, sa, la))
        h = mix_hue(lead[0], other[0], .74)
        s = (lead[1] * .68 + other[1] * .32) * (.82 + jitter * .2)
        l = (lead[2] * .6 + other[2] * .4) * (.9 + jitter * .18)
    else:
        h = mix_hue(ha, hb, .5) + (jitter - .5) * .06
        s = max(sa, sb) * (.86 + jitter * .18)
        l = (la + lb) / 2 * (.9 + jitter * .2)
    h %= 1.0
    # Les verts très clairs et très saturés virent au fluo : on les tempère.
    if .17 < h < .47:
        s = min(s, .6)
        l = min(l, .46)
    return (h, max(.18, min(.8, s)), max(.22, min(.66, l)))

def leaf_of(h, s, l, anthocyanin):
    """Feuillage : toujours vert, juste infléchi par la teinte de la variété."""
    offset = ((h - 0.265 + .5) % 1.0) - .5
    lh = (0.265 + max(-.06, min(.06, offset * .25))) % 1.0
    return hex_of(lh, .36 + s * .14, (.25 + l * .14) * (.9 if anthocyanin else 1))

def pistil_of(h, s, l, seed):
    """Pistils : orangé à rouille, rosé sur les variétés très pigmentées."""
    purple = .55 < h < .95
    ph = (0.93 + (seed % 40) / 1000) if purple and s > .4 else (0.035 + (seed % 55) / 900)
    return hex_of(ph % 1.0, .48 + (seed % 30) / 100, .62 + (seed % 17) / 130)

rows = json.load(open(CATALOG))
by_id = {v['id']: v for v in rows}
hsl = {}

for vid, value in FOUNDERS.items():
    assert vid in by_id, vid
    hsl[vid] = value
for v in rows:
    if v['parents']:
        a, b = v['parents']
        hsl[v['id']] = hybrid_hsl(hsl[a], hsl[b], v['id'])

# Écartement : on pousse les paires trop proches jusqu'à ce qu'elles se distinguent.
ids = [v['id'] for v in rows]
founders = set(FOUNDERS)
TARGET = 9.0
for _ in range(600):
    hexes = {i: hex_of(*hsl[i]) for i in ids}
    labs = {i: lab(hexes[i]) for i in ids}
    worst = None
    for i1, i2 in itertools.combinations(ids, 2):
        d = math.dist(labs[i1], labs[i2])
        if worst is None or d < worst[0]:
            worst = (d, i1, i2)
    if worst[0] >= TARGET:
        break
    d, i1, i2 = worst
    for vid, sign in ((i1, 1), (i2, -1)):
        if vid in founders:
            continue
        h, s, l = hsl[vid]
        hsl[vid] = ((h + sign * .012) % 1.0, min(.86, s + sign * .012), max(.22, min(.72, l + sign * .014)))

hexes = {i: hex_of(*hsl[i]) for i in ids}
labs = {i: lab(hexes[i]) for i in ids}
pairs = sorted(math.dist(labs[a], labs[b]) for a, b in itertools.combinations(ids, 2))
print(f"ΔE minimum entre variétés : {pairs[0]:.1f}   (médiane {pairs[len(pairs)//2]:.1f})")
print(f"paires sous 5 : {sum(1 for d in pairs if d < 5)}")

bands = {'rouge/orange':0,'jaune/or':0,'vert':0,'turquoise':0,'bleu':0,'violet':0,'rose':0}
for vid in ids:
    h = hsl[vid][0] * 360
    key = ('rouge/orange' if h < 40 else 'jaune/or' if h < 75 else 'vert' if h < 160
           else 'turquoise' if h < 200 else 'bleu' if h < 255 else 'violet' if h < 310 else 'rose')
    bands[key] += 1
print('répartition des teintes :', bands)

for v in rows:
    h, s, l = hsl[v['id']]
    anthocyanin = .55 < h < .99 or h < .12
    seed = int(v['seed'])
    v['bud'] = hex_of(h, s, l)
    v['leaf'] = leaf_of(h, s, l, anthocyanin)
    v['pistil'] = pistil_of(h, s, l, seed)

if '--write' in sys.argv:
    json.dump(rows, open(CATALOG, 'w'), ensure_ascii=False, indent=2)
    print('catalog.json mis à jour')
else:
    print('\naperçu :')
    for v in rows[:6] + rows[14:20]:
        print(f"  {v['id']:24} bud {v['bud']}  leaf {v['leaf']}  pistil {v['pistil']}")
