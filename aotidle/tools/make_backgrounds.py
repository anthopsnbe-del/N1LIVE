#!/usr/bin/env python3
"""Fabrique les décors d'écran à partir des illustrations déjà embarquées.

Chaque écran (QG, boss, front, arène, marché, fiche) reçoit son propre fond :
un quadrant de `environments.webp` recadré, flouté juste ce qu'il faut pour ne
pas concurrencer l'interface, étalonné dans une couleur qui identifie l'écran,
puis vignetté et grainé. Aucune nouvelle illustration à produire, six ambiances
distinctes, environ 40 Ko chacune.

    python3 tools/make_backgrounds.py
"""
import os

import numpy as np
from PIL import Image, ImageEnhance, ImageFilter

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ASSETS = os.path.join(ROOT, "assets")
SIZE = (900, 1200)

# nom, quadrant source (colonne, ligne), teinte d'étalonnage, intensité
SCREENS = [
    ("qg", (0, 0), (108, 168, 150), 0.42),
    ("boss", (1, 1), (196, 92, 74), 0.46),
    ("front", (1, 0), (128, 140, 208), 0.44),
    ("arene", (0, 1), (226, 176, 96), 0.40),
    ("marche", (0, 0), (96, 156, 208), 0.44),
    ("profil", (1, 1), (176, 150, 116), 0.38),
]


def quadrant(sheet, cell):
    width, height = sheet.size
    box = (cell[0] * width // 2, cell[1] * height // 2,
           (cell[0] + 1) * width // 2, (cell[1] + 1) * height // 2)
    return sheet.crop(box)


def cover(image, size):
    """Recadre au format demandé sans déformer."""
    ratio = max(size[0] / image.width, size[1] / image.height)
    scaled = image.resize((int(image.width * ratio) + 1, int(image.height * ratio) + 1), Image.LANCZOS)
    left = (scaled.width - size[0]) // 2
    top = (scaled.height - size[1]) // 3      # on garde le ciel plutôt que le sol
    return scaled.crop((left, top, left + size[0], top + size[1]))


def grade(image, tint, strength):
    base = np.asarray(image, dtype=np.float32) / 255.0
    color = np.array(tint, dtype=np.float32) / 255.0
    # Étalonnage : on tire l'image vers la teinte de l'écran, puis on assombrit.
    graded = base * (1 - strength) + base * color * strength * 2.0
    graded = np.clip(graded, 0, 1) ** 1.25 * 0.72

    height, width = graded.shape[:2]
    y, x = np.mgrid[0:height, 0:width]
    cx, cy = width / 2, height / 2
    radius = np.sqrt(((x - cx) / cx) ** 2 + ((y - cy) / cy) ** 2)
    vignette = np.clip(1.15 - 0.62 * radius ** 1.6, 0.18, 1.0)[..., None]
    # Dégradé vers le bas : l'interface s'appuie sur une zone sombre.
    fade = np.clip(1.05 - (y / height) ** 1.7 * 0.85, 0.15, 1.0)[..., None]
    graded = graded * vignette * fade

    grain = np.random.default_rng(7).normal(0, 0.012, graded.shape[:2])[..., None]
    return Image.fromarray(np.clip((graded + grain) * 255, 0, 255).astype(np.uint8))


def main():
    sheet = Image.open(os.path.join(ASSETS, "environments.webp")).convert("RGB")
    total = 0
    for name, cell, tint, strength in SCREENS:
        image = cover(quadrant(sheet, cell), SIZE)
        image = image.filter(ImageFilter.GaussianBlur(1.6))
        image = ImageEnhance.Contrast(image).enhance(1.12)
        path = os.path.join(ASSETS, "bg-%s.webp" % name)
        grade(image, tint, strength).save(path, "WEBP", quality=72, method=6)
        total += os.path.getsize(path)
        print("bg-%s.webp — %d Ko" % (name, os.path.getsize(path) // 1024))
    print("total : %d Ko" % (total // 1024))


if __name__ == "__main__":
    main()
