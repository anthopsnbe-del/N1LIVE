#!/usr/bin/env python3
"""Optimise les images du jeu : passe unique, à relancer sur les originaux.

Les images d'origine (27 Mo de PNG 1024x1536 affichés dans des canvas de
220x230) sont réduites à leur taille d'affichage x2 et converties en WebP.
Les titans, dessinés sur fond noir et affichés jusqu'ici en
`mix-blend-mode: screen`, reçoivent une vraie couche alpha : la couleur est
déduite de la luminance, ce qui détoure le sprite sans halo et le rend
opaque sur n'importe quel décor.

    python3 tools/optimize_assets.py <dossier_des_png_originaux>
"""
import os
import sys

import numpy as np
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ASSETS = os.path.join(ROOT, "assets")


def key_black(im):
    """Fond noir -> couche alpha.

    Le fond est un noir franc, la peinture ne l'est jamais : l'opacite suit la
    luminance entre deux seuils bas, ce qui detoure sans halo et garde les
    ombres du titan opaques (contrairement au `mix-blend-mode: screen` d'avant,
    qui rendait tout le sprite translucide).
    """
    a = np.asarray(im.convert("RGB"), dtype=np.float32)
    lum = a.max(axis=2)
    alpha = np.clip((lum - 4.0) / 22.0, 0.0, 1.0)
    out = np.dstack([a, alpha * 255.0]).astype(np.uint8)
    return Image.fromarray(out, "RGBA")


def fit(im, box):
    im = im.copy()
    im.thumbnail(box, Image.LANCZOS)
    return im


def webp(im, name, quality=86):
    path = os.path.join(ASSETS, name)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    im.save(path, "WEBP", quality=quality, method=6)
    return path


def main(src):
    def load(rel):
        return Image.open(os.path.join(src, rel))

    total = 0
    # Titans : alpha réelle, rognage du vide, moitié de la définition.
    for name in ("titan", "anormal", "colossal", "blinde", "feminin", "bestial"):
        im = key_black(load(os.path.join("enemies", name + ".png")))
        bbox = im.getbbox()
        if bbox:
            im = im.crop(bbox)
        total += os.path.getsize(webp(fit(im, (512, 640)), "enemies/%s.webp" % name, 88))

    # Planches d'icônes : demi-définition (les bornes de art.js suivent).
    for name in ("heroes", "items", "abilities"):
        im = load(name + ".png").convert("RGB")
        total += os.path.getsize(
            webp(im.resize((im.width // 2, im.height // 2), Image.LANCZOS), name + ".webp"))

    total += os.path.getsize(webp(fit(load("environments.png"), (768, 768)), "environments.webp"))
    total += os.path.getsize(webp(fit(load("atlas.png"), (512, 512)), "atlas.webp", 90))
    total += os.path.getsize(webp(fit(load("logo.png").convert("RGBA"), (760, 380)), "logo.webp", 90))
    total += os.path.getsize(webp(fit(load("app-icon.png"), (512, 512)), "app-icon.webp"))

    # Icône de lanceur : 144 px suffisent en xxhdpi, contre 1254 px auparavant.
    icon = fit(load("app-icon.png").convert("RGBA"), (144, 144))
    icon.save(os.path.join(ROOT, "shell/res/mipmap-xxhdpi/ic_launcher.png"), "PNG", optimize=True)

    print("assets WebP : %.2f Mo" % (total / 1048576.0))


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(ASSETS))
