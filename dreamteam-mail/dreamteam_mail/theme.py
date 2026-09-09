"""Thème sombre orange/rouge, dans l'esprit d'un terminal Claude Code."""

from __future__ import annotations

import tkinter as tk
from tkinter import font as tkfont
from tkinter import ttk

# Palette : fonds sombres chauds, accents orange, texte rouge.
FOND = "#17120e"          # fond general
PANNEAU = "#1f1813"       # panneaux et champs
PANNEAU_HAUT = "#251c16"  # survol, en-tetes
BORDURE = "#3d2a1d"
ORANGE = "#d97757"        # accent principal
ORANGE_VIF = "#e8913a"    # accent secondaire, compte a rebours
ROUGE = "#ff6b5b"         # texte courant
ROUGE_SOMBRE = "#c8412f"  # texte discret
ROUGE_CLAIR = "#ff9a8c"   # texte secondaire
SELECTION = "#4a2417"
TEXTE_SELECTION = "#ffd7a8"

_FAMILLES = ("Cascadia Mono", "Consolas", "JetBrains Mono", "DejaVu Sans Mono", "Courier New")


def police(taille: int = 10, gras: bool = False) -> tuple:
    """Premiere police a chasse fixe disponible sur la machine."""
    try:
        disponibles = set(tkfont.families())
    except tk.TclError:
        disponibles = set()
    famille = next((f for f in _FAMILLES if f in disponibles), "TkFixedFont")
    return (famille, taille, "bold") if gras else (famille, taille)


def appliquer(racine: tk.Misc) -> ttk.Style:
    """Applique le thème à toute l'application. À appeler juste après la racine."""
    style = ttk.Style(racine)
    style.theme_use("clam")  # seul thème ttk qui accepte des couleurs sur mesure

    normale = police()
    grasse = police(gras=True)

    racine.configure(background=FOND)
    racine.option_add("*Font", normale)
    # Boites de dialogue natives (messagebox) : elles n'utilisent pas ttk.
    racine.option_add("*Dialog.msg.font", normale)
    racine.option_add("*background", FOND)
    racine.option_add("*foreground", ROUGE)

    style.configure(".", background=FOND, foreground=ROUGE, font=normale,
                    fieldbackground=PANNEAU, bordercolor=BORDURE,
                    darkcolor=PANNEAU, lightcolor=PANNEAU, troughcolor=PANNEAU)
    style.configure("TFrame", background=FOND)
    style.configure("TPanedwindow", background=FOND)
    style.configure("TLabel", background=FOND, foreground=ROUGE)
    # Etiquettes posees sur la barre laterale : meme fond qu'elle.
    style.configure("Barre.TLabel", background=PANNEAU, foreground=ROUGE)
    style.configure("Titre.TLabel", foreground=ORANGE, font=grasse)
    style.configure("Marque.TLabel", background=PANNEAU, foreground=ORANGE_VIF,
                    font=police(13, gras=True))
    style.configure("Discret.TLabel", background=PANNEAU, foreground=ROUGE_SOMBRE,
                    font=police(9))
    style.configure("Credit.TLabel", background=PANNEAU, foreground=ROUGE_SOMBRE,
                    padding=(10, 5))
    style.configure("Barre.TFrame", background=PANNEAU)
    style.configure("Statut.TFrame", background=PANNEAU)
    style.configure("TSeparator", background=BORDURE)
    style.configure("Statut.TLabel", background=PANNEAU, foreground=ORANGE_VIF,
                    relief="flat", padding=(10, 5))

    style.configure("TButton", background=PANNEAU, foreground=ORANGE,
                    bordercolor=BORDURE, focuscolor=ORANGE,
                    relief="flat", padding=(10, 5))
    style.map("TButton",
              background=[("pressed", SELECTION), ("active", PANNEAU_HAUT)],
              foreground=[("pressed", TEXTE_SELECTION), ("active", ORANGE_VIF)],
              bordercolor=[("active", ORANGE)])

    style.configure("TEntry", fieldbackground=PANNEAU, foreground=ROUGE,
                    insertcolor=ORANGE, bordercolor=BORDURE, padding=4)
    style.map("TEntry", bordercolor=[("focus", ORANGE)])

    style.configure("TCombobox", fieldbackground=PANNEAU, background=PANNEAU,
                    foreground=ROUGE, arrowcolor=ORANGE, bordercolor=BORDURE, padding=4)
    style.map("TCombobox",
              fieldbackground=[("readonly", PANNEAU)],
              foreground=[("readonly", ROUGE)],
              bordercolor=[("focus", ORANGE)])
    # La liste deroulante d'un Combobox est un widget Tk classique.
    racine.option_add("*TCombobox*Listbox.background", PANNEAU)
    racine.option_add("*TCombobox*Listbox.foreground", ROUGE)
    racine.option_add("*TCombobox*Listbox.selectBackground", SELECTION)
    racine.option_add("*TCombobox*Listbox.selectForeground", TEXTE_SELECTION)

    style.configure("TCheckbutton", background=FOND, foreground=ROUGE,
                    indicatorcolor=PANNEAU, focuscolor=ORANGE)
    style.map("TCheckbutton",
              indicatorcolor=[("selected", ORANGE)],
              foreground=[("active", ORANGE_VIF)])

    style.configure("Treeview", background=PANNEAU, fieldbackground=PANNEAU,
                    foreground=ROUGE, bordercolor=BORDURE, rowheight=24)
    style.configure("Treeview.Heading", background=PANNEAU_HAUT, foreground=ORANGE,
                    font=grasse, relief="flat", padding=(6, 5))
    style.map("Treeview.Heading", background=[("active", SELECTION)])
    style.map("Treeview",
              background=[("selected", SELECTION)],
              foreground=[("selected", TEXTE_SELECTION)])

    # Onglets : meme registre sombre, l'onglet actif porte l'accent orange.
    style.configure("TNotebook", background=FOND, bordercolor=BORDURE, tabmargins=(2, 6, 2, 0))
    style.configure("TNotebook.Tab", background=PANNEAU, foreground=ROUGE_SOMBRE,
                    padding=(18, 8), font=normale, bordercolor=BORDURE)
    style.map("TNotebook.Tab",
              background=[("selected", FOND), ("active", PANNEAU_HAUT)],
              foreground=[("selected", ORANGE), ("active", ORANGE_VIF)],
              font=[("selected", grasse)])

    style.configure("Vertical.TScrollbar", background=PANNEAU, troughcolor=FOND,
                    arrowcolor=ORANGE, bordercolor=FOND)
    style.map("Vertical.TScrollbar", background=[("active", SELECTION)])
    return style


def habiller_texte(zone: tk.Text) -> None:
    """Colore une zone de texte et declare ses styles de mise en forme."""
    zone.configure(
        background=PANNEAU, foreground=ROUGE, insertbackground=ORANGE,
        selectbackground=SELECTION, selectforeground=TEXTE_SELECTION,
        font=police(), relief="flat", borderwidth=0,
        highlightthickness=1, highlightbackground=BORDURE, highlightcolor=ORANGE,
        padx=16, pady=12,
        spacing1=3,   # avant chaque paragraphe
        spacing2=3,   # entre les lignes d'un meme paragraphe
        spacing3=6,   # apres chaque paragraphe
    )
    zone.tag_configure("titre", foreground=ORANGE, font=police(gras=True))
    zone.tag_configure("accent", foreground=ORANGE_VIF)
    zone.tag_configure("discret", foreground=ROUGE_CLAIR)
    zone.tag_configure("separateur", foreground=ROUGE_SOMBRE)
    zone.tag_configure("citation", foreground=ROUGE_SOMBRE, lmargin1=12, lmargin2=12)
