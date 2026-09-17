# Änderungen

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
