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
