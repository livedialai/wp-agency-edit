# Änderungen

## 1.2.0 — 17.09.2026

Eigenes Agentur-Passwort, im Chatfenster gesetzt und geändert.

- **Ersteinrichtung:** Beim ersten Öffnen wird ein Agentur-Passwort festgelegt
  (mindestens 8 Zeichen, doppelte Eingabe). Es gilt für das Plugin, nicht für das
  WordPress-Konto, und wird **gehasht** gespeichert (bcrypt, Kostenfaktor 12).
- **Ändern** im Sperrbildschirm — das bisherige Passwort wird verlangt; danach ist
  die Sitzung sofort gesperrt
- **Vergessen:** das eigene WordPress-Passwort öffnet ebenfalls; zusätzlich ein
  Knopf *Passwort zurücksetzen* auf der Agentur-Seite
- Neue Routen `POST /setup` und `POST /passwort`; `/status` meldet
  `eingerichtet` und `gesetzt_am`
- Geprüft: zu kurzes Passwort und abweichende Wiederholung abgelehnt, Klartext
  nicht in der Datenbank, Anmeldung mit Agentur- und mit WordPress-Passwort,
  Änderung sperrt, altes Passwort gilt danach nicht mehr, Oberfläche fehlerfrei

Beim Wechsel von 1.1.0 auf 1.2.0 ist noch kein Passwort gesetzt — die
Ersteinrichtung läuft beim nächsten Öffnen des Chatfensters.


## 1.1.0 — 17.09.2026

Sitzungssperre.

- Vor dem Bearbeiten einer Website muss die Bedienperson das **Agentur-Passwort**
  eingeben; geprüft wird gegen das eigene WordPress-Benutzerkonto
- **Abmelden** über einen Knopf im Chatfenster
- **Automatische Sperre** nach einer einstellbaren Ruhezeit (Vorgabe 5 Minuten);
  jede Nachricht zählt als Lebenszeichen und stellt die Uhr zurück
- **Website wechseln sperrt sofort** — eine andere Kundenseite erfordert erneutes
  Entsperren
- Sitzung gilt **nur für die gewählte Website**
- **Fünf Fehlversuche**, danach zehn Minuten Sperre
- Neue Routen `POST /unlock` und `POST /lock`; `/status` meldet Sperrzustand und
  Restzeit, `/chat` verweigert ohne entsperrte Sitzung
- Restzeitanzeige im Chatfenster

Geprüft mit einem Wegwerfkonto: gesperrt ohne Eingabe, falsches Passwort
abgelehnt („Noch 4 Versuche"), richtiges entsperrt auf 300 s, fremde Website
bleibt gesperrt, Verlängerung um 3 s gemessen, Abmelden sperrt, Chat ohne
Sitzung antwortet mit `{"gesperrt":true}`. Wegwerfkonto danach gelöscht.

## 1.0.0 — 17.09.2026

Erste Fassung.

- Website-Liste: Adresse, Benutzername und Anwendungspasswort hinterlegen;
  Speichern prüft die Verbindung sofort und zeigt Name, WordPress-Version und
  Zahl der erreichbaren Fähigkeiten
- Zugangstoken **verschlüsselt** abgelegt (sodium_crypto_secretbox, Rückfall
  AES-256-GCM); Schlüssel aus den Salzen der Installation, nicht in der Datenbank
- Chatfenster mit Website-Auswahl
- Sprachmodell-Zugang frei einstellbar (Basis-URL, Modell, Schlüssel,
  Temperatur, Rundenzahl); der Schlüssel liegt in der Zentrale
- Werkzeugliste kommt von der Gegenstelle, Ausführung dort per HTTP über
  `POST /wp-json/wp-abilities/v1/abilities/<name>/run`
- Systemanweisung: erst lesen, dann handeln; Vorschläge nicht ungefragt
  freigeben; Plugins und Einstellungen nur auf ausdrückliche Anweisung
- Funktionsreferenz, erzeugt aus der laufenden Installation

### Behoben

- Der Schrägstrich im Fähigkeitsnamen darf bei der Route nicht kodiert werden —
  `rawurlencode()` machte aus `kiedit/get-page` ein `%2F` und alle Fernaufrufe
  liefen ins Leere (HTTP 404). Erster Testlauf 23,6 s ohne Ergebnis, danach
  2,9 s mit Ergebnis.
