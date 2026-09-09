# Icône de l'application

Dépose ici le fichier **`icone.png`** (carré, idéalement 512×512 ou 1024×1024,
fond transparent ou non).

La compilation le convertit automatiquement en `icone.ico` multi-tailles
(16 → 256 px) via `tools/creer_ico.py`, et PyInstaller l'utilise comme icône de
`DreamTeamMail.exe`.

Sans `icone.png`, la compilation réussit quand même : l'exécutable garde
l'icône par défaut de PyInstaller.
