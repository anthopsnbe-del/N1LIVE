# -*- mode: python ; coding: utf-8 -*-
"""Recette PyInstaller : un seul .exe, sans console."""

block_cipher = None

a = Analysis(
    ["main.py"],
    pathex=["."],
    binaries=[],
    datas=[],
    hiddenimports=["dreamteam_mail.gui", "dreamteam_mail.core", "dreamteam_mail.backends"],
    hookspath=[],
    runtime_hooks=[],
    excludes=["pytest", "numpy", "pandas"],
    cipher=block_cipher,
    noarchive=False,
)
pyz = PYZ(a.pure, a.zipped_data, cipher=block_cipher)

exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.zipfiles,
    a.datas,
    [],
    name="DreamTeamMail",
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=False,
    console=False,          # application fenetree, pas de console noire
    disable_windowed_traceback=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
)
