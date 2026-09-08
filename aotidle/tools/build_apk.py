#!/usr/bin/env python3
"""Assemble et signe l'APK d'AOT IDLE.

Le conteneur Android (manifeste, dex, ressources) vient de `shell/`, le jeu de
`assets/`. Rien n'est compilé côté Java : le dex d'origine est une simple
coquille WebView, seul le contenu web change.

    python3 tools/build_apk.py [sortie.apk]

Clé de signature : `KEYSTORE`, `KS_PASS`, `KEY_ALIAS` si vous voulez signer
avec la vôtre (obligatoire pour publier une mise à jour de l'app existante).
Sans ces variables, une clé de développement est créée dans `build/`, ce qui
impose de désinstaller la version précédente avant d'installer celle-ci.
"""
import os
import shutil
import subprocess
import sys
import urllib.request
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BUILD = os.path.join(ROOT, "build")
APKSIG_URL = ("https://repo1.maven.org/maven2/com/android/tools/build/"
              "apksig/2.3.0/apksig-2.3.0.jar")
# resources.arsc doit rester non compressé et aligné sur 4 octets (API 30+).
STORED = {"resources.arsc"}


def run(cmd):
    subprocess.run(cmd, check=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)


def files_of(directory, prefix=""):
    for base, _, names in os.walk(directory):
        for name in sorted(names):
            full = os.path.join(base, name)
            yield full, prefix + os.path.relpath(full, directory).replace(os.sep, "/")


def write_zip(path):
    """Écrit l'APK non signé, en alignant les entrées stockées sur 4 octets."""
    entries = []
    for src in ("shell", "assets"):
        prefix = "" if src == "shell" else "assets/"
        entries += list(files_of(os.path.join(ROOT, src), prefix))
    # Le manifeste et le dex d'abord : c'est l'ordre attendu par les outils.
    order = {"AndroidManifest.xml": 0, "classes.dex": 1, "resources.arsc": 2}
    entries.sort(key=lambda e: (order.get(e[1], 3), e[1]))

    with open(path, "wb") as raw:
        with zipfile.ZipFile(raw, "w") as zf:
            for full, name in entries:
                data = open(full, "rb").read()
                stored = name in STORED
                info = zipfile.ZipInfo(name, date_time=(2026, 1, 1, 0, 0, 0))
                info.compress_type = zipfile.ZIP_STORED if stored else zipfile.ZIP_DEFLATED
                info.external_attr = 0o644 << 16
                if stored:
                    # Bourrage dans le champ « extra » pour que les données
                    # commencent sur un multiple de 4.
                    offset = raw.tell() + 30 + len(name.encode())
                    pad = -offset % 4
                    info.extra = b"\x00" * pad
                zf.writestr(info, data)
    return path


def keystore():
    ks = os.environ.get("KEYSTORE")
    if ks:
        return ks, os.environ.get("KS_PASS", ""), os.environ.get("KEY_ALIAS", "key0")
    ks = os.path.join(BUILD, "dev.p12")
    if not os.path.exists(ks):
        run(["keytool", "-genkeypair", "-storetype", "PKCS12", "-keystore", ks,
             "-storepass", "aotidle", "-keypass", "aotidle", "-alias", "aotidle",
             "-keyalg", "RSA", "-keysize", "2048", "-validity", "10950",
             "-dname", "CN=AOT IDLE dev, O=N1LIVE, C=FR"])
    return ks, "aotidle", "aotidle"


def signer_jar():
    jar = os.path.join(BUILD, "apksig.jar")
    if not os.path.exists(jar):
        urllib.request.urlretrieve(APKSIG_URL, jar)
    classes = os.path.join(BUILD, "classes")
    marker = os.path.join(classes, "ApkSign.class")
    if not os.path.exists(marker):
        os.makedirs(classes, exist_ok=True)
        run(["javac", "-cp", jar, "-d", classes,
             os.path.join(ROOT, "tools/sign/ApkSign.java")])
    return jar, classes


def main():
    out = sys.argv[1] if len(sys.argv) > 1 else os.path.join(BUILD, "AOTIDLE-v6.0.apk")
    os.makedirs(BUILD, exist_ok=True)
    unsigned = os.path.join(BUILD, "unsigned.apk")
    write_zip(unsigned)
    jar, classes = signer_jar()
    ks, password, alias = keystore()
    # apksig 2.3.0 utilise des classes internes du JDK, fermées depuis Java 9.
    run(["java", "--add-exports", "java.base/sun.security.x509=ALL-UNNAMED",
         "--add-exports", "java.base/sun.security.pkcs=ALL-UNNAMED",
         "--add-exports", "java.base/sun.security.util=ALL-UNNAMED",
         "-cp", jar + os.pathsep + classes, "ApkSign",
         unsigned, out, ks, password, alias])
    print("%s — %.2f Mo" % (out, os.path.getsize(out) / 1048576.0))


if __name__ == "__main__":
    main()
