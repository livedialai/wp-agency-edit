#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Erzeugt docs/funktionen.md aus dem Datensatz der laufenden Installation.

Aufruf:  python3 docs/referenz-erzeugen.py <plugin-verzeichnis> <datensatz.json>
"""
import json
import sys
from pathlib import Path

QUELLE = Path(sys.argv[1] if len(sys.argv) > 1 else ".")
DATEN = json.loads(Path(sys.argv[2]).read_text(encoding="utf-8"))

# Was nicht in die Referenz gehoert (bewusst leer — hier ist alles oeffentlich).
AUSGENOMMENE_METHODEN = set()

Z = []
z = Z.append

z(f"# Funktionen — vollständige Referenz\n")
z(f"Erzeugt am {DATEN['zeit']} · WordPress {DATEN['wp']} · PHP {DATEN['php']} · "
  f"Plugin {DATEN['plugin']} · {DATEN['website']}\n")
z("Diese Datei wird aus einer **laufenden Installation** erzeugt: Klassen und Methoden über")
z("Reflexion, die Routen aus dem REST-Server, die Optionen aus der Datenbank, die Haken aus dem")
z("Quelltext. Sie beschreibt also, was tatsächlich vorhanden ist — nicht, was geplant war.\n")
z("---\n")

# ---------------------------------------------------------------- Routen
z("## REST-Schnittstelle\n")
z("Namensraum: `wp-agency-edit/v1`. Alle Routen verlangen `manage_options`.\n")
z("| Methode | Pfad | Parameter |")
z("|---|:--:|---|")
for r in DATEN.get("routen", []):
    params = ", ".join(f"`{p}`" for p in r.get("parameter", [])) or "—"
    pfad = r["pfad"].replace("/wp-agency-edit/v1", "") or "/"
    z(f"| {r['methoden']} | `{pfad}` | {params} |")
z("")
if DATEN.get("parameter"):
    z("In der Anfrage verwendete Parameternamen: "
      + ", ".join(f"`{k}`" for k in sorted(DATEN["parameter"])) + ".\n")
z("---\n")

# ------------------------------------------------------------- Optionen
z("## Datenbank\n")
z("| Option | Zweck |")
z("|---|---|")
zwecke = {
    "wp_agency_edit_sites": "Liste der betreuten Websites. Zugangstoken darin **verschlüsselt**.",
    "wp_agency_edit_llm": "Zugang zum Sprachmodell: Basis-URL, Modell, Schlüssel, Temperatur, Rundenzahl.",
}
for o in DATEN.get("optionen", []):
    if o["name"].startswith("_transient"):
        continue
    zweck = zwecke.get(o["name"], "")
    vorhanden = "vorhanden" if o.get("vorhanden") and o["groesse"] else "noch nicht angelegt"
    z(f"| `{o['name']}` | {zweck} ({vorhanden}) |")
z("")
transients = [o for o in DATEN.get("optionen", []) if o["name"].startswith("_transient_") and "timeout" not in o["name"]]
if transients:
    z("Kurzzeitspeicher (verfallen von selbst):\n")
    for t in transients:
        name = t["name"].replace("_transient_", "")
        if name.startswith("wpaeg_verlauf_"):
            z(f"- `{name}` — Gesprächsverlauf, je Benutzer und Website, 4 Stunden.")
        elif name.startswith("wpaeg_faehig_"):
            z(f"- `{name}` — Fähigkeitsliste einer Website, 5 Minuten.")
        else:
            z(f"- `{name}`")
    z("")
z("---\n")

# ------------------------------------------------------------- Aufbau
z("## Aufbau des Quelltexts\n")
nach_datei = {}
for k in DATEN.get("klassen", []):
    nach_datei.setdefault(k["datei"], []).append(k)

for datei in sorted(nach_datei):
    for k in nach_datei[datei]:
        z(f"### `{k['datei']}` — `{k['klasse']}`\n")
        if k["zweck"]:
            z(k["zweck"] + "\n")
        z("| Methode | Parameter | Zweck |")
        z("|---|---|---|")
        for m in k["methoden"]:
            if m["name"] in AUSGENOMMENE_METHODEN:
                continue
            statisch = " *(statisch)*" if m["statisch"] else ""
            z(f"| `{m['name']}()`{statisch} | {m['parameter'] or '—'} | {m['kurz'] or '—'} |")
        z("")

z("### Dateien\n")
z("| Datei | Zeilen | Größe |")
z("|---|---:|---:|")
for f in DATEN.get("dateien", []):
    z(f"| `{f['pfad']}` | {f['zeilen'] or '—'} | {f['bytes']} B |")
gesamt = sum(f["zeilen"] for f in DATEN.get("dateien", []))
z(f"| **gesamt** | **{gesamt}** | |")
z("")
z("---\n")

# --------------------------------------------------------------- Haken
z("## Haken\n")
z("| Art | Haken | Rückruf | Datei |")
z("|---|---|---|---|")
for h in DATEN.get("haken", []):
    ruf = h["rueckruf"]
    if ruf and ruf.count("(") > ruf.count(")"):
        ruf += ")"
    z(f"| {h['art']} | `{h['haken']}` | `{ruf}` | `{h['datei']}` |")
z("")

ziel = QUELLE / "docs" / "funktionen.md"
ziel.write_text("\n".join(Z), encoding="utf-8")
print(f"geschrieben: {ziel}")
print(f"  {len(Z)} Zeilen · {ziel.stat().st_size} Bytes")
print(f"  Klassen: {len(DATEN.get('klassen', []))} · Routen: {len(DATEN.get('routen', []))} · "
      f"Optionen: {len([o for o in DATEN.get('optionen', []) if not o['name'].startswith('_transient')])} · "
      f"Haken: {len(DATEN.get('haken', []))}")
