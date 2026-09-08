#!/usr/bin/env python3
"""La version du jeu, à un seul endroit.

Elle vivait à trois endroits — `assets/build-info.js` (ce que l'application
croit être), `shell/AndroidManifest.xml` (ce qu'Android installe) et
`tools/package_ftp.py` (ce que le site annonce). Les trois ont fini par ne plus
dire la même chose : l'APK 6.6 embarquait un `build-info.js` resté en 6.5, donc
le jeu comparait 13 à 14 et affichait « Version 6.6 disponible » indéfiniment,
même fraîchement installé.

Ce module est désormais la source unique : `sync()` réécrit `build-info.js` et
corrige le manifeste binaire, et `build_apk.py` l'appelle avant d'assembler
l'APK. Le décalage ne peut plus revenir.
"""
import json
import os
import struct

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

VERSION_NAME = "6.7"
VERSION_CODE = 15
PACKAGE = "com.n1live.aotidl3"
RELEASE_ENDPOINT = "https://asylum-games.fr/aotidle/release.php"
DOWNLOAD_URL = "https://asylum-games.fr/aotidle/telecharger.php"
NOTES = ("Correctif : le service multijoueur et la boucle « mise à jour disponible ». "
         "Diagnostic du serveur intégré au jeu (onglet Monde). Retour en mode portrait. "
         "Cosmétiques (cape, harnais, cadre) débloqués par vos exploits, journal de clan et "
         "emotes. Fiche de soldat complète, Archives du bataillon en cartes de R à LR, hauts "
         "faits, ouverture narrative et décors sur les écrans en ligne. Dépôt du bataillon, "
         "marché entre joueurs et saisons de l'arène. Guerres de clans sur 24 heures, boss "
         "mondial coopératif, combats qui montent en difficulté, élites, sac et fusion "
         "d'équipement, panoplies, passifs de recrues, arbre des âmes, missions quotidiennes, "
         "Tour à modificateurs, jeu dix fois plus léger.")

# Identifiants de ressources Android des attributs qui nous intéressent.
ATTR_VERSION_CODE = 0x0101021B
ATTR_VERSION_NAME = 0x0101021C
ATTR_ORIENTATION = 0x0101001E

MANIFEST = os.path.join(ROOT, "shell", "AndroidManifest.xml")
BUILD_INFO = os.path.join(ROOT, "assets", "build-info.js")


def build_info() -> dict:
    return {
        "versionCode": VERSION_CODE,
        "versionName": VERSION_NAME,
        "packageName": PACKAGE,
        "releaseEndpoint": RELEASE_ENDPOINT,
        "downloadUrl": DOWNLOAD_URL,
        "notes": NOTES
    }


def write_build_info() -> None:
    payload = json.dumps(build_info(), ensure_ascii=False)
    with open(BUILD_INFO, "w", encoding="utf-8") as out:
        out.write("window.AOT_BUILD = Object.freeze(%s);\n" % payload)


# ----------------------------------------------------------------------
# Manifeste binaire (AXML) : lecture et retouche sur place
# ----------------------------------------------------------------------

def _string_pool(data: bytes):
    """Renvoie (offset du chunk, liste des chaînes, offsets de leurs données)."""
    assert struct.unpack_from("<H", data, 0)[0] == 0x0003, "ce n'est pas un AXML"
    pos = 8   # le pool de chaînes suit immédiatement l'en-tête du fichier
    kind, header, size = struct.unpack_from("<HHI", data, pos)
    assert kind == 0x0001, "chaîne de caractères attendue en tête"
    count, _styles, flags, strings_start, _styles_start = struct.unpack_from("<IIIII", data, pos + 8)
    utf8 = bool(flags & (1 << 8))
    offsets = struct.unpack_from("<%dI" % count, data, pos + 28)
    values, starts = [], []
    for off in offsets:
        at = pos + strings_start + off
        starts.append(at)
        if utf8:
            length = data[at + 1]
            values.append(data[at + 2:at + 2 + length].decode("utf-8", "replace"))
        else:
            length = struct.unpack_from("<H", data, at)[0]
            values.append(data[at + 2:at + 2 + length * 2].decode("utf-16-le"))
    return pos, size, values, starts, utf8


def _resource_map(data: bytes):
    """Table « index de chaîne → identifiant de ressource »."""
    pos = 8
    while pos < len(data):
        kind, header, size = struct.unpack_from("<HHI", data, pos)
        if kind == 0x0180:
            count = (size - header) // 4
            return list(struct.unpack_from("<%dI" % count, data, pos + header))
        pos += size
    return []


def _attributes(data: bytes):
    """Parcourt les attributs de tous les éléments : (offset, id de ressource)."""
    resmap = _resource_map(data)
    pos = 8
    found = []
    while pos < len(data):
        kind, header, size = struct.unpack_from("<HHI", data, pos)
        if kind == 0x0102:   # START_ELEMENT
            start, _sz, count = struct.unpack_from("<HHH", data, pos + 16 + 4 + 4)
            base = pos + 16 + start
            for i in range(count):
                at = base + i * 20
                name = struct.unpack_from("<I", data, at + 4)[0]
                res = resmap[name] if name < len(resmap) else 0
                found.append((at, res))
        pos += size
    return found


def read_manifest() -> dict:
    data = open(MANIFEST, "rb").read()
    _pos, _size, values, _starts, _utf8 = _string_pool(data)
    out = {}
    for at, res in _attributes(data):
        raw, value = struct.unpack_from("<i", data, at + 8)[0], struct.unpack_from("<I", data, at + 16)[0]
        if res == ATTR_VERSION_CODE:
            out["versionCode"] = value
        elif res == ATTR_VERSION_NAME:
            out["versionName"] = values[raw] if 0 <= raw < len(values) else None
        elif res == ATTR_ORIENTATION:
            out["orientation"] = value
    return out


def patch_manifest() -> dict:
    """Aligne le manifeste sur VERSION_CODE / VERSION_NAME."""
    data = bytearray(open(MANIFEST, "rb").read())
    _pos, _size, values, starts, utf8 = _string_pool(bytes(data))
    for at, res in _attributes(bytes(data)):
        if res == ATTR_VERSION_CODE:
            struct.pack_into("<I", data, at + 16, VERSION_CODE)
        elif res == ATTR_VERSION_NAME:
            index = struct.unpack_from("<i", data, at + 8)[0]
            old = values[index]
            if old != VERSION_NAME:
                # Retouche sur place : le nom doit occuper exactement la même
                # longueur, faute de quoi il faudrait reconstruire le pool de
                # chaînes (et tous les décalages du fichier).
                if len(old) != len(VERSION_NAME):
                    raise SystemExit("versionName %r et %r n'ont pas la même longueur : "
                                     "reconstruire le manifeste avec aapt2." % (old, VERSION_NAME))
                encoded = VERSION_NAME.encode("utf-8" if utf8 else "utf-16-le")
                head = 2   # deux octets de longueur avant les données, dans les deux encodages
                data[starts[index] + head:starts[index] + head + len(encoded)] = encoded
    open(MANIFEST, "wb").write(bytes(data))
    return read_manifest()


def sync(verbose: bool = True) -> dict:
    write_build_info()
    manifest = patch_manifest()
    if manifest.get("versionCode") != VERSION_CODE or manifest.get("versionName") != VERSION_NAME:
        raise SystemExit("le manifeste n'a pas pris la version : %r" % manifest)
    if verbose:
        print("version %s (code %d) — build-info.js et manifeste synchronisés, orientation %s"
              % (VERSION_NAME, VERSION_CODE,
                 {1: "portrait", 6: "paysage"}.get(manifest.get("orientation"), manifest.get("orientation"))))
    return manifest


if __name__ == "__main__":
    print(json.dumps(sync(), ensure_ascii=False))
