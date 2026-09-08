#!/usr/bin/env python3
"""Prépare le dossier à envoyer sur l'hébergement, APK compris.

    python3 tools/package_ftp.py

Produit `build/AOT-IDLE-v6-FTP/` et son zip : les fichiers PHP du service, le
widget du site, l'APK signé et un `release.json` calculé sur cet APK (taille et
empreinte SHA-256, comme l'exige `release-lib.php`).

La clé de signature vient des variables `KEYSTORE`, `KS_PASS`, `KEY_ALIAS`,
comme pour `build_apk.py`. Sans elles l'APK est signé avec la clé de
développement : parfait pour tester, à ne surtout pas publier — les joueurs ne
pourraient pas mettre à jour leur installation.
"""
import hashlib
import json
import os
import shutil
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BUILD = os.path.join(ROOT, "build")
VERSION_NAME = "6.1"
VERSION_CODE = 9
SERVER_FILES = [
    "index.php", "social.php", "social-core.php", "boss-core.php",
    "release.php", "release-lib.php", "telecharger.php",
    "download-widget.js", "download-widget.css"
]
NOTES = ("Boss mondial coopératif, classement des assaillants et des clans, combats qui montent "
         "en difficulté, élites, sac et fusion d'équipement, panoplies, passifs de recrues, arbre "
         "des âmes, missions quotidiennes, Tour à modificateurs, jeu dix fois plus léger.")


def main():
    stage = os.path.join(BUILD, "AOT-IDLE-v6-FTP")
    site = os.path.join(stage, "aotidle")
    releases = os.path.join(site, "releases")
    shutil.rmtree(stage, ignore_errors=True)
    os.makedirs(releases, exist_ok=True)

    apk_name = "aot-idle-%s.apk" % VERSION_NAME
    apk = os.path.join(releases, apk_name)
    subprocess.run([sys.executable, os.path.join(ROOT, "tools/build_apk.py"), apk], check=True)

    data = open(apk, "rb").read()
    release = {
        "versionCode": VERSION_CODE,
        "versionName": VERSION_NAME,
        "packageName": "com.n1live.aotidl3",
        "releaseEndpoint": "https://asylum-games.fr/aotidle/release.php",
        "downloadUrl": "https://asylum-games.fr/aotidle/telecharger.php",
        "notes": NOTES,
        "file": apk_name,
        "size": len(data),
        "sha256": hashlib.sha256(data).hexdigest()
    }

    for name in SERVER_FILES:
        shutil.copy(os.path.join(ROOT, "server", name), os.path.join(site, name))
    with open(os.path.join(site, "release.json"), "w", encoding="utf-8") as out:
        json.dump(release, out, ensure_ascii=False, indent=2)
    for name in ("INSTALLATION-FTP.md", "BOUTON-A-COLLER.html"):
        shutil.copy(os.path.join(ROOT, "server", name), os.path.join(stage, name))

    archive = shutil.make_archive(stage, "zip", BUILD, "AOT-IDLE-v6-FTP")
    print("%s — %.2f Mo" % (archive, os.path.getsize(archive) / 1048576.0))
    if not os.environ.get("KEYSTORE"):
        print("ATTENTION : APK signé avec la clé de développement. Pour publier, relancez avec "
              "KEYSTORE, KS_PASS et KEY_ALIAS renseignés.")


if __name__ == "__main__":
    main()
