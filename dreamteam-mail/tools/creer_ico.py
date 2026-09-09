"""Convertit assets/icone.png en assets/icone.ico (plusieurs tailles).

Utilise Pillow. Sans le PNG, le script ne fait rien et sort en succes : la
compilation continue alors avec l'icone par defaut de PyInstaller.
"""

from __future__ import annotations

import sys
from pathlib import Path

RACINE = Path(__file__).resolve().parents[1]
PNG = RACINE / "assets" / "icone.png"
ICO = RACINE / "assets" / "icone.ico"
TAILLES = [(16, 16), (24, 24), (32, 32), (48, 48), (64, 64), (128, 128), (256, 256)]


def main() -> int:
    if not PNG.exists():
        print(f"Pas de {PNG.name} : compilation sans icone personnalisee.")
        return 0
    try:
        from PIL import Image
    except ImportError:
        print("Pillow absent : impossible de convertir le PNG. pip install pillow")
        return 1
    with Image.open(PNG) as image:
        image.convert("RGBA").save(ICO, format="ICO", sizes=TAILLES)
    print(f"{ICO.name} genere ({', '.join(f'{l}x{h}' for l, h in TAILLES)}).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
