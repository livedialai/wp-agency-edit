# WP Agency Edit

Zentrale für betreute WordPress-Websites. Websites mit Adresse und
Anwendungspasswort hinterlegen, Verbindung prüfen — und dann per KI-Chat
Änderungen auf der jeweiligen Kundenseite vornehmen.

Das Gegenstück auf der Kundenseite ist **[WP AI Edit](https://github.com/livedialai/wp-ai-edit)**.
Dort wird der Zugang angelegt (Einstellungen → WP AI Edit → Fernzugriff), hier
wird er hinterlegt.

## Wozu

Wer mehrere Websites betreut, loggt sich sonst durch jede einzeln: Öffnungszeiten,
Preise, Telefonnummer, ein neues Bild. WP Agency Edit macht daraus ein Fenster mit
einer Website-Auswahl — der Agent fragt die gewählte Seite, was sie kann, und
arbeitet dort.

**Das Sprachmodell läuft in der Zentrale.** Die Kundenseiten brauchen keinen
eigenen API-Zugang und keine Kenntnis davon, welches Modell benutzt wird. Der
Kunde merkt nur, dass sich etwas ändert.

## Installation

**Paket herunterladen** (Anmeldung bei GitHub nötig, das Repository ist privat):

```
https://github.com/livedialai/wp-agency-edit/releases/latest
```

Die Datei `wp-agency-edit.zip` entpackt nach `wp-agency-edit/` und lässt sich im
Backend unter *Plugins → Installieren → Plugin hochladen* einspielen.

Ein direkter Installationbefehl über eine URL ist bei einem privaten Repository
**nicht möglich**: WordPress lehnt URLs mit eingebetteten Zugangsdaten ab
(„keine gültige URL"). Wer das Plugin per Kommandozeile nachziehen will, macht
das Repository öffentlich — dann geht:

```bash
wp plugin install https://github.com/livedialai/wp-agency-edit/releases/latest/download/wp-agency-edit.zip --force
```

## Einrichtung

1. **Auf der Kundenseite:** WP AI Edit installieren. Unter
   *Einstellungen → WP AI Edit → Fernzugriff* einen Zugang anlegen. Das
   Anwendungspasswort wird **einmal** angezeigt — kopieren.
2. **Hier:** *Agentur → Website hinzufügen* — Name, Adresse, Benutzername und
   das Anwendungspasswort eintragen. Speichern prüft die Verbindung sofort und
   zeigt WordPress-Version und Zahl der erreichbaren Fähigkeiten.
3. **Hier:** *Agentur → Sprachmodell* — Basis-URL, Modell und Schlüssel
   hinterlegen (DeepSeek, OpenAI, Mistral, eigenes Gateway, Ollama).

Danach unten rechts auf **🏢 Agentur** klicken, die Website auswählen und
schreiben, was geändert werden soll.

## Sperre

Beim ersten Öffnen des Chatfensters wirst du gefragt:

> **Ersteinrichtung: Agentur-Passwort festlegen**

Danach beginnt jede Arbeitssitzung mit **„Welche Seite möchtest du bearbeiten?"**
und der Abfrage des Agentur-Passworts.

Das Agentur-Passwort gehört **diesem Plugin**, nicht dem WordPress-Konto. Es wird
beim Festlegen **gehasht** gespeichert (bcrypt, Kostenfaktor 12 — dasselbe
Verfahren, das WordPress für seine Benutzer verwendet) und ist auch aus der
Datenbank nicht zurückzurechnen.

- **Festlegen und ändern** direkt im Chatfenster. Für eine Änderung wird das
  bisherige Passwort verlangt.
- **Vergessen?** Das eigene WordPress-Passwort öffnet ebenfalls. Zusätzlich gibt
  es auf der Agentur-Seite einen Knopf **Passwort zurücksetzen** — danach legst
  du beim nächsten Öffnen ein neues fest.
- **Automatische Sperre** nach einer einstellbaren Ruhezeit (Vorgabe
  5 Minuten). Jede Nachricht im Chat gilt als Lebenszeichen und stellt die Uhr
  zurück.
- **Abmelden** über den Knopf rechts oben — jederzeit.
- **Website wechseln sperrt sofort.** Wer eine andere Kundenseite bearbeiten
  will, muss das Passwort erneut eingeben.
- **Nach einer Passwortänderung ist die Sitzung sofort zu.**
- **Fehlversuche sind begrenzt:** nach fünf falschen Eingaben ist für zehn
  Minuten Ruhe.
- Die Sitzung gilt **nur für die gewählte Website**. Selbst wer die Kennung einer
  anderen Seite kennt, kommt damit nicht weiter.

Die Ruhezeit steht unter *Agentur → Sprachmodell → Sperre* (1 bis 120 Minuten),
der Zustand des Passworts direkt darunter.

**Gib dein Agentur-Passwort niemals per Chat weiter** — es gehört in das
Eingabefeld des Chatfensters und nirgendwo sonst hin.

## Wie es arbeitet

Der Agent spricht nicht mit einer eigenen Kopie der Website, sondern mit der
echten:

```
Zentrale                                    Kundenseite
────────                                    ───────────
Sprachmodell (hier)                         WP AI Edit
   │  Werkzeugaufruf                           │
   ├─ GET  /wp-json/wp-ai-edit/v1/auskunft ────┤  Zustand abfragen
   ├─ GET  /wp-json/wp-abilities/v1/abilities ─┤  Fähigkeiten holen
   └─ POST /wp-json/wp-abilities/v1/abilities/kiedit/…/run ─┤  ausführen
```

Für die Kundenseite ist jeder Aufruf ein normaler Benutzer. Ihre
Berechtigungsprüfungen greifen unverändert — ein Administrator-Zugang hat alle
Rechte, ein Redakteur-Zugang entsprechend weniger. Was der Zugang darf,
entscheidet also der Kunde über das Konto, dem er das Passwort gibt.

Die Fähigkeitsliste wird fünf Minuten zwischengespeichert, damit nicht jede
Nachricht eine zusätzliche Anfrage kostet.

## Vorschläge statt Überraschungen

Die Kundenseite arbeitet in der Regel im Vorschlagsmodus: Schreibbefehle landen
dort als Vorschlag mit Vorschau, nicht sofort live. Der Agent kann offene
Vorschläge auflisten und auf Anweisung freigeben oder verwerfen. Die letzte
Entscheidung bleibt beim Eigentümer der Website.

## Dokumentation

- **[Funktionsreferenz](docs/funktionen.md)** — jede Klasse mit ihren Methoden,
  alle REST-Routen, alle Datenbank-Optionen, die Haken und der Dateiaufbau.
- **Nachlegen:** Die Referenz wird aus der **laufenden Installation** erzeugt,
  damit sie nicht vom Code abweichen kann:

  ```bash
  wp eval-file wp-content/plugins/wp-agency-edit/docs/referenz-erheben.php > /tmp/doku.json
  python3 docs/referenz-erzeugen.py . /tmp/doku.json
  ```

  Alles kommt aus dem laufenden System: Klassen und Methoden über Reflexion, die
  Routen aus dem REST-Server, die Optionen aus der Datenbank, die Haken aus dem
  Quelltext. Es gibt keine von Hand gepflegte Liste, die veralten könnte.

### Aufbau

```
wp-agency-edit.php            Hauptklasse: Menü, Website-Liste, Chatfenster, REST-Routen
includes/class-speicher.php   Website-Liste und verschlüsselte Token
includes/class-fernruf.php    HTTP gegen eine betreute Website
includes/class-agent.php      Werkzeugrunde gegen das Sprachmodell
assets/js/widget.js           Chatfenster mit Website-Auswahl
assets/css/widget.css         Gestaltung
prompts/agentur.md            Systemanweisung
docs/                         Referenz und die Skripte, die sie erzeugen
```

## Sicherheit

- **Anwendungspasswörter** aus dem WordPress-Kern, kein eigenes Schlüsselsystem.
  Jeder Zugang ist auf der Kundenseite einzeln widerrufbar, der Widerruf wirkt
  sofort.
- **Token verschlüsselt abgelegt** (sodium_crypto_secretbox, Rückfall
  AES-256-GCM). Der Schlüssel wird aus den Salzen der Installation abgeleitet und
  steht in der `wp-config.php` — ein Datenbankauszug allein nützt niemandem.
- **Jeder Fernzugriff wird auf der Kundenseite protokolliert**: Zeit,
  Zugangsname, Route, Status, gekürzte IP. Vorschläge tragen zusätzlich die
  Herkunft `fern`.
- Keine Zugangsdaten im Quelltext, keine in der Ausgabe.

## Voraussetzungen

- WordPress 6.9 oder neuer, PHP 8.0 oder neuer
- Auf der Kundenseite WP AI Edit mit aktiviertem Fernzugriff
- Ein API-Zugang zu einem OpenAI-kompatiblen Sprachmodell

## Lizenz

GPL-2.0-or-later
