# Funktionen — vollständige Referenz

Erzeugt am 2026-09-17 15:05:08 · WordPress 7.1 · PHP 8.4.25 · Plugin 1.2.0 · https://gofonia.life

Diese Datei wird aus einer **laufenden Installation** erzeugt: Klassen und Methoden über
Reflexion, die Routen aus dem REST-Server, die Optionen aus der Datenbank, die Haken aus dem
Quelltext. Sie beschreibt also, was tatsächlich vorhanden ist — nicht, was geplant war.

---

## REST-Schnittstelle

Namensraum: `wp-agency-edit/v1`. Alle Routen verlangen `manage_options`.

| Methode | Pfad | Parameter |
|---|:--:|---|
| GET | `/` | `namespace`, `context` |
| GET | `/status` | — |
| POST | `/chat` | — |
| POST | `/setup` | — |
| POST | `/passwort` | — |
| POST | `/unlock` | — |
| POST | `/lock` | — |
| POST | `/reset` | — |

In der Anfrage verwendete Parameternamen: `bisher`, `nachricht`, `neu`, `passwort`, `site`, `wiederholung`.

---

## Datenbank

Diese Optionen liest oder schreibt das Plugin. Die Namen werden aus dem Quelltext
abgeleitet, damit auch eine noch nicht angelegte Option erscheint. Zugangstoken
liegen darin **verschlüsselt**.

| Option | Inhalt | Zustand |
|---|---|---|
| `wp_agency_edit_llm` | Sprachmodell: Basis-URL, Modell, Schlüssel, Temperatur, Rundenzahl | angelegt (234 B) |
| `wp_agency_edit_sites` | Betreute Websites samt Zugangstoken (**verschlüsselt**) | angelegt (407 B) |
| `wp_agency_edit_sperre` | Agentur-Passwort als Hash (bcrypt) und Zeitpunkt des Setzens | noch nicht angelegt |

Kurzzeitspeicher (verfallen von selbst):

- `wpaeg_faehig_cea379ea8da3fb723ce8483fd9c603d8` — Fähigkeitsliste einer Website, 5 Minuten.
- `wpaeg_sitzung_3`
- `wpaeg_sitzung_5`

---

## Aufbau des Quelltexts

### `includes/class-agent.php` — `WP_Agency_Edit_Agent`

Werkzeugrunde gegen ein Sprachmodell.

| Methode | Parameter | Zweck |
|---|---|---|
| `bereit()` *(statisch)* | — | Ist der Zugang vollständig? |
| `chat()` *(statisch)* | array $messages, array $site | Gespräch führen, bis das Modell eine Textantwort liefert. |
| `defaults()` *(statisch)* | — | Standardwerte des API-Zugangs. |
| `faehigkeiten()` *(statisch)* | array $site | Fähigkeiten der Gegenstelle, mit kurzem Zwischenspeicher. |
| `funktionsname()` *(statisch)* | string $name | Fähigkeitsname in einen gültigen Funktionsnamen wandeln. |
| `maske()` *(statisch)* | — | Schlüssel maskiert anzeigen. |
| `settings()` *(statisch)* | — | Einstellungen. |
| `test()` *(statisch)* | — | Sprachmodell prüfen. |

### `includes/class-fernruf.php` — `WP_Agency_Edit_Fernruf`

HTTP-Zugriff auf eine betreute Website.

| Methode | Parameter | Zweck |
|---|---|---|
| `ausfuehren()` *(statisch)* | array $site, string $name, array $input | Eine Fähigkeit auf der Gegenstelle ausführen. |
| `auskunft()` *(statisch)* | array $site | Auskunft der Gegenstelle. |
| `faehigkeiten()` *(statisch)* | array $site | Fähigkeiten der Gegenstelle, die dieses Plugin dort anbietet. |
| `pruefen()` *(statisch)* | array $site | Verbindung prüfen und den Stand festhalten. |

### `includes/class-sitzung.php` — `WP_Agency_Edit_Sitzung`

Sitzung und Passwortbestätigung.

| Methode | Parameter | Zweck |
|---|---|---|
| `aendern()` *(statisch)* | string $bisher, string $neu, string $wiederholung | Passwort ändern (bisheriges nötig). |
| `beruehren()` *(statisch)* | string $site_id | Lebenszeichen: die Ruhezeit beginnt von vorn. |
| `dauer()` *(statisch)* | — | Ruhezeit, einstellbar über die Einstellungen. |
| `eingerichtet()` *(statisch)* | — | Ist bereits ein Agentur-Passwort gesetzt? |
| `entsperren()` *(statisch)* | string $passwort, string $site_id | Sitzung mit dem eigenen Passwort entsperren. |
| `entsperrt()` *(statisch)* | string $site_id | Ist die Sitzung entsperrt — und zwar für genau diese Website? |
| `festlegen()` *(statisch)* | string $neu, string $wiederholung | Ersteinrichtung: Agentur-Passwort festlegen. |
| `gesetzt_am()` *(statisch)* | — | Wann wurde das Passwort gesetzt? |
| `restversuche()` *(statisch)* | — | Wie viele Fehlversuche sind noch erlaubt? |
| `restzeit()` *(statisch)* | string $site_id | Verbleibende Sekunden bis zum Zufallen. |
| `sperren()` *(statisch)* | — | Sitzung sperren (Abmelden). |
| `zuruecksetzen()` *(statisch)* | — | Passwort zurücksetzen — nur über die Einstellungsseite. |

### `includes/class-speicher.php` — `WP_Agency_Edit_Speicher`

Verwaltung der Website-Liste.

| Methode | Parameter | Zweck |
|---|---|---|
| `alle()` *(statisch)* | — | Alle Websites. |
| `eine()` *(statisch)* | string $id | Eine Website anhand der Kennung. |
| `entfernen()` *(statisch)* | string $id | Website entfernen. |
| `entschluesseln()` *(statisch)* | string $gespeichert | Entschlüsselt einen Token. |
| `kennung()` *(statisch)* | string $url, string $user | Kennung für Klartextwerte (Adresse oder Benutzer), damit die Liste zusammenführt statt zu duplizieren. |
| `speichern()` *(statisch)* | array $werte | Website speichern (neu oder aktualisieren). |
| `stand()` *(statisch)* | string $id, array $stand | Ergebnis einer Prüfung festhalten. |
| `verschluesseln()` *(statisch)* | string $klar | Verschlüsselt einen Token. |
| `zugang()` *(statisch)* | array $site | Zugangsdaten einer Website im Klartext (nur zur Laufzeit). |

### `wp-agency-edit.php` — `WP_Agency_Edit`

Hauptklasse.

| Methode | Parameter | Zweck |
|---|---|---|
| `assets()` | string $hook | Skripte und Stile. |
| `chat()` | WP_REST_Request $anfrage | Chat. |
| `menue()` | — | Menüpunkt. |
| `prompt()` *(statisch)* | array $site | Systemanweisung für den Agenten. |
| `reset()` | WP_REST_Request $anfrage | Verlauf verwerfen. |
| `routen()` | — | REST-Routen. |
| `seite()` | — | Oberfläche. |
| `widget()` | — | Das Chatfenster ausgeben. |

### Dateien

| Datei | Zeilen | Größe |
|---|---:|---:|
| `CHANGELOG.md` | 48 | 2250 B |
| `LICENSE` | — | 17984 B |
| `README.md` | 157 | 6853 B |
| `assets/css/widget.css` | 292 | 4789 B |
| `assets/js/widget.js` | 334 | 9624 B |
| `includes/class-agent.php` | 342 | 10022 B |
| `includes/class-fernruf.php` | 206 | 6387 B |
| `includes/class-sitzung.php` | 323 | 8698 B |
| `includes/class-speicher.php` | 231 | 6829 B |
| `prompts/agentur.md` | 42 | 1867 B |
| `wp-agency-edit.php` | 642 | 28203 B |
| **gesamt** | **2617** | |

---

## Haken

| Art | Haken | Rückruf | Datei |
|---|---|---|---|
| Aktion | `admin_menu` | `$this->menue()` | `wp-agency-edit.php` |
| Aktion | `admin_enqueue_scripts` | `$this->assets()` | `wp-agency-edit.php` |
| Aktion | `admin_footer` | `$this->widget()` | `wp-agency-edit.php` |
| Aktion | `rest_api_init` | `$this->routen()` | `wp-agency-edit.php` |
