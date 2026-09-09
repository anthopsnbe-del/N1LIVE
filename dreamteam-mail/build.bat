@echo off
REM ---------------------------------------------------------------
REM Construit DreamTeamMail.exe (Windows uniquement).
REM Prerequis : Python 3.11+ installe depuis python.org (tkinter inclus).
REM ---------------------------------------------------------------
setlocal

echo [1/4] Verification de Python...
python --version || (echo Python introuvable. Installe-le depuis python.org & exit /b 1)

echo [2/4] Environnement virtuel...
if not exist .venv python -m venv .venv
call .venv\Scripts\activate.bat

echo [3/4] Installation de PyInstaller...
python -m pip install --upgrade pip >nul
python -m pip install "pyinstaller>=6.6" pillow || exit /b 1

echo [4/4] Icone puis compilation...
python tools\creer_ico.py
python -m PyInstaller --clean --noconfirm DreamTeamMail.spec || exit /b 1

echo.
echo Termine : %CD%\dist\DreamTeamMail.exe
endlocal
