# Einsatzplanung

Webbasiertes Stundenplanungs-Tool für Grundschulen. Lehrkräfte werden Klassen und Fächern zugeordnet; Auslastung, Änderungshistorie und Druckansichten sind integriert.

Entwickelt für die **33. Grundschule Spandau**.

---

## Voraussetzungen

- PHP 7.4 oder neuer (mit JSON-Erweiterung)
- Beliebiger Webserver (Apache, Nginx, Caddy) – oder der eingebaute PHP-Entwicklungsserver
- Schreibrechte auf `einsaetze.json` für den Webserver-Prozess

---

## Schnellstart (lokal)

```bash
php -S localhost:8000 router.php
```

Browser öffnen: `http://localhost:8000`

Standard-Passwort: `schule2026` – **vor dem ersten produktiven Einsatz ändern** (siehe [Einstellungen](#einstellungen)).

---

## Deployment

1. Dateien auf den Webserver kopieren
2. Sicherstellen, dass `einsaetze.json` vom Webserver-Prozess beschrieben werden darf:
   ```bash
   touch einsaetze.json
   chown www-data:www-data einsaetze.json
   ```
3. Im Browser einloggen und unter **Verwaltung → Einstellungen** Schulname, Schuljahr und Passwort setzen

---

## Dateistruktur

```
einsatzplanung/
├── config.php          Konfiguration, Session, Datenzugriff
├── index.php           Login-Seite
├── einsatzplan.php     Hauptansicht: Planungstabelle
├── lehrkraefte.php     Lehrkräfte-Übersicht mit Auslastungskarten
├── verwaltung.php      Admin: Klassen, Lehrkräfte, Import, Backup
├── druck.php           Druckoptimierte Ansicht
├── historie.php        Änderungshistorie
├── hilfe.php           Integrierte Dokumentation
├── router.php          URL-Router für den PHP-Entwicklungsserver
├── einsaetze.json      Datenspeicher (wird automatisch angelegt)
└── partials/
    ├── head.php        Gemeinsames CSS (Design-System)
    └── nav.php         Navigationsleiste
```

---

## Funktionsübersicht

### Einsatzplan
- Tabelle: Klassen als Zeilen, Fächer als Spalten
- Eintrag per Kürzel mit Autocomplete, Stunden-Feld pro Eintrag
- Mehrere Lehrkräfte pro Zelle möglich (Teilstunden)
- Zellen-Farbcodes: rot = Bedarf offen, orange = Doppelsteckung, weiß = erfüllt
- Notiz-Feld pro Zelle (erscheint in Druckansicht, wird historisiert)
- Spaltenüberschriften bleiben beim Scrollen sichtbar

### Lehrkraft-Chips
- Leiste oberhalb der Tabelle mit Auslastung pro Lehrkraft
- **Hover:** Popover mit vollständigem Namen, Fortschrittsbalken und Einsatz-Liste
- **Klick:** Alle Einsätze der Lehrkraft in der Tabelle hervorheben
- **Toggle „Nur Klassen mit Einsatz":** Blendet Klassen ohne Einsatz der markierten Lehrkraft aus
- **Sortierung:** A–Z oder nach offenen Stunden absteigend

### Lehrkräfte-Übersicht
- Karten mit Fortschrittsbalken, Stunden-Verhältnis und Einsatz-Liste
- Druckbar (eine Seite pro Lehrkraft)

### Verwaltung
| Bereich | Funktion |
|---|---|
| Einstellungen | Schulname, Schuljahr, Passwort |
| Stundentafeln | Vorlagen für Klassenstufen (Fach → Stunden) |
| Klassen | Anlegen, Bearbeiten (Name, Farbe, Fächerstunden), Löschen |
| Lehrkräfte | Einzeln hinzufügen oder per Excel-Import (Copy&Paste) |
| Datensicherung | JSON-Export und -Import |
| Zurücksetzen | Alle Einsätze löschen (Lehrkräfte bleiben) |

### Druckansicht
- **Unterrichtstabelle** (A4 quer): vollständiger Plan mit Schreiblinien
- **Pro Lehrkraft** (A4 hoch): eine Seite pro Person mit Notizen

### Änderungshistorie
- Automatische Protokollierung jeder Zelländerung (Vorher → Nachher)
- Notizänderungen werden ebenfalls erfasst
- **Zäsuren**: benannte Markierungen zur Trennung von Planungsphasen
- Begrenzt auf 2.000 Einträge (älteste fallen heraus)

---

## Datenspeicher

Alle Daten liegen in `einsaetze.json`:

```json
{
  "einstellungen": { "schulname": "…", "schuljahr": "…", "passwort_hash": "…" },
  "stundentafeln": { "1/2": { "Deutsch": 8, "Mathematik": 5 } },
  "klassen": {
    "blau 1/2": { "farbe": "#3a6ea5", "faecher": { "KL": 1, "Deutsch": 8 } }
  },
  "lehrkraefte": {
    "Bel": { "name": "Bellmann, Lisa", "stunden": 28 }
  },
  "einsaetze": {
    "blau 1/2": {
      "Deutsch": [{ "k": "Bel", "std": 8 }]
    }
  },
  "zusatz": [{ "bezeichnung": "AG Chor", "k": "Bel", "std": 2 }],
  "notizen": { "blau 1/2": { "Deutsch": "Notiztext …" } },
  "historie": [ … ]
}
```

Die Datei kann manuell bearbeitet oder als Backup gespeichert und wieder eingespielt werden.

---

## Einstellungen

### Passwort
Im Browser unter **Verwaltung → Passwort ändern** setzen. Das Passwort wird als bcrypt-Hash in `einsaetze.json` gespeichert. Das Klartext-Passwort in `config.php` (`PASSWORD`) dient nur als Fallback, wenn noch kein Hash hinterlegt ist.

### Schulname & Schuljahr
Erscheinen in Navigation, Login-Seite und Druckansichten. Ebenfalls unter **Verwaltung → Einstellungen**.

---

## Tastaturkürzel (Einsatzplan)

| Taste | Funktion |
|---|---|
| `↓` / `↑` | Im Autocomplete-Dropdown navigieren |
| `Enter` | Vorschlag übernehmen / Eingabe bestätigen |
| `Escape` | Dropdown schließen, Eingabe zurücksetzen |
| `Tab` | Zum nächsten Feld wechseln (speichert automatisch) |
