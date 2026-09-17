# Funktionen — vollständige Referenz

Erzeugt am 2026-09-17 14:51:41 · WordPress 7.1 · PHP 8.4.25 · Plugin 1.0.0 · https://gofonia.life

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
| POST | `/reset` | — |

In der Anfrage verwendete Parameternamen: `nachricht`, `site`.

---

## Datenbank

| Option | Zweck |
|---|---|
| `wp_agency_edit_llm` | Zugang zum Sprachmodell: Basis-URL, Modell, Schlüssel, Temperatur, Rundenzahl. (vorhanden) |
| `wp_agency_edit_sites` | Liste der betreuten Websites. Zugangstoken darin **verschlüsselt**. (vorhanden) |

Kurzzeitspeicher (verfallen von selbst):

- `wpaeg_faehig_cea379ea8da3fb723ce8483fd9c603d8` — Fähigkeitsliste einer Website, 5 Minuten.

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
| `README.md` | 87 | 3812 B |
| `assets/css/widget.css` | 172 | 2771 B |
| `assets/js/widget.js` | 124 | 3172 B |
| `includes/class-agent.php` | 342 | 10022 B |
| `includes/class-fernruf.php` | 206 | 6387 B |
| `includes/class-speicher.php` | 231 | 6829 B |
| `prompts/agentur.md` | 42 | 1867 B |
| `wp-agency-edit.php` | 478 | 19868 B |
| **gesamt** | **1682** | |

---

## Haken

| Art | Haken | Rückruf | Datei |
|---|---|---|---|
| Aktion | `admin_menu` | `$this->menue()` | `wp-agency-edit.php` |
| Aktion | `admin_enqueue_scripts` | `$this->assets()` | `wp-agency-edit.php` |
| Aktion | `admin_footer` | `$this->widget()` | `wp-agency-edit.php` |
| Aktion | `rest_api_init` | `$this->routen()` | `wp-agency-edit.php` |
