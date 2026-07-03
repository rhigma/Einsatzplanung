# Einsatzplanung

Webbasiertes Stundenplanungs-Tool fÃ¼r Grundschulen. LehrkrÃ¤fte werden Klassen und FÃ¤chern zugeordnet; Auslastung, Ã„nderungshistorie und Druckansichten sind integriert.

Entwickelt fÃ¼r die **33. Grundschule Spandau**.

---

## Voraussetzungen

- PHP 7.4 oder neuer (mit JSON-Erweiterung)
- Beliebiger Webserver (Apache, Nginx, Caddy) â€“ oder der eingebaute PHP-Entwicklungsserver
- Schreibrechte auf `einsaetze.json` fÃ¼r den Webserver-Prozess

---

## Schnellstart (lokal)

```bash
php -S localhost:8000 router.php
```

Browser Ã¶ffnen: `http://localhost:8000`

Standard-Passwort: `schule2026` â€“ **vor dem ersten produktiven Einsatz Ã¤ndern** (siehe [Einstellungen](#einstellungen)).

---

## Deployment

1. Dateien auf den Webserver kopieren
2. Sicherstellen, dass `einsaetze.json` vom Webserver-Prozess beschrieben werden darf:
   ```bash
   touch einsaetze.json
   chown www-data:www-data einsaetze.json
   ```
3. Im Browser einloggen und unter **Verwaltung â†’ Einstellungen** Schulname, Schuljahr und Passwort setzen

---

## Dateistruktur

```
einsatzplanung/
â”œâ”€â”€ config.php          Konfiguration, Session, Datenzugriff
â”œâ”€â”€ index.php           Login-Seite
â”œâ”€â”€ einsatzplan.php     Hauptansicht: Planungstabelle
â”œâ”€â”€ lehrkraefte.php     LehrkrÃ¤fte-Ãœbersicht mit Auslastungskarten
â”œâ”€â”€ verwaltung.php      Admin: Klassen, LehrkrÃ¤fte, Import, Backup
â”œâ”€â”€ untis_export.php    GPU002.TXT-Export für Untis-Import
â”œâ”€â”€ druck.php           Druckoptimierte Ansicht
â”œâ”€â”€ historie.php        Ã„nderungshistorie
â”œâ”€â”€ hilfe.php           Integrierte Dokumentation
â”œâ”€â”€ router.php          URL-Router fÃ¼r den PHP-Entwicklungsserver
â”œâ”€â”€ einsaetze.json      Datenspeicher (wird automatisch angelegt)
â””â”€â”€ partials/
    â”œâ”€â”€ head.php        Gemeinsames CSS (Design-System)
    â””â”€â”€ nav.php         Navigationsleiste
```

---

## FunktionsÃ¼bersicht

### Einsatzplan
- Tabelle: Klassen als Zeilen, FÃ¤cher als Spalten
- Eintrag per KÃ¼rzel mit Autocomplete, Stunden-Feld pro Eintrag
- Mehrere LehrkrÃ¤fte pro Zelle mÃ¶glich (Teilstunden)
- Zellen-Farbcodes: rot = Bedarf offen, orange = Doppelsteckung, weiÃŸ = erfÃ¼llt
- Notiz-Feld pro Zelle (erscheint in Druckansicht, wird historisiert)
- SpaltenÃ¼berschriften bleiben beim Scrollen sichtbar

### Lehrkraft-Chips
- Leiste oberhalb der Tabelle mit Auslastung pro Lehrkraft
- **Hover:** Popover mit vollstÃ¤ndigem Namen, Fortschrittsbalken und Einsatz-Liste
- **Klick:** Alle EinsÃ¤tze der Lehrkraft in der Tabelle hervorheben
- **Toggle â€žNur Klassen mit Einsatz":** Blendet Klassen ohne Einsatz der markierten Lehrkraft aus
- **Sortierung:** Aâ€“Z oder nach offenen Stunden absteigend

### LehrkrÃ¤fte-Ãœbersicht
- Karten mit Fortschrittsbalken, Stunden-VerhÃ¤ltnis und Einsatz-Liste
- Druckbar (eine Seite pro Lehrkraft)

### Verwaltung
| Bereich | Funktion |
|---|---|
| Einstellungen | Schulname, Schuljahr, Passwort |
| Stundentafeln | Vorlagen fÃ¼r Klassenstufen (Fach â†’ Stunden) |
| Klassen | Anlegen, Bearbeiten (Name, Farbe, FÃ¤cherstunden), LÃ¶schen |
| LehrkrÃ¤fte | Einzeln hinzufÃ¼gen oder per Excel-Import (Copy&Paste) |
| Datensicherung | JSON-Export und -Import |
| ZurÃ¼cksetzen | Alle EinsÃ¤tze lÃ¶schen (LehrkrÃ¤fte bleiben) |

### Druckansicht
- **Unterrichtstabelle** (A4 quer): vollstÃ¤ndiger Plan mit Schreiblinien
- **Pro Lehrkraft** (A4 hoch): eine Seite pro Person mit Notizen


### Untis-Export
- Navigationseintrag „Untis Export" lädt eine GPU002.TXT herunter
- Komma-getrenntes Format, direkt in Untis importierbar (Unterrichts-Import)
- Pro Lehrkraft/Klasse/Fach wird ein eigener Unterricht angelegt
- Team-Teaching wird als getrennte Unterrichte exportiert
- Zusatzaufgaben (z.B. Konferenzen, AGs) werden als Unterrichte ohne Klasse aufgenommen
- Fachnamen werden automatisch auf Untis-Kurzel gemappt (Deutsch→D, Mathe→M, …)
- Schuljahr, Start- und Enddatum werden aus den Einstellungen ubernommen
- Raum-Felder bleiben leer (in der Einsatzplanung nicht erfasst)

### Ã„nderungshistorie
- Automatische Protokollierung jeder ZellÃ¤nderung (Vorher â†’ Nachher)
- NotizÃ¤nderungen werden ebenfalls erfasst
- **ZÃ¤suren**: benannte Markierungen zur Trennung von Planungsphasen
- Begrenzt auf 2.000 EintrÃ¤ge (Ã¤lteste fallen heraus)

---

## Datenspeicher

Alle Daten liegen in `einsaetze.json`:

```json
{
  "einstellungen": { "schulname": "â€¦", "schuljahr": "â€¦", "passwort_hash": "â€¦" },
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
  "notizen": { "blau 1/2": { "Deutsch": "Notiztext â€¦" } },
  "historie": [ â€¦ ]
}
```

Die Datei kann manuell bearbeitet oder als Backup gespeichert und wieder eingespielt werden.

---

## Einstellungen

### Passwort
Im Browser unter **Verwaltung â†’ Passwort Ã¤ndern** setzen. Das Passwort wird als bcrypt-Hash in `einsaetze.json` gespeichert. Das Klartext-Passwort in `config.php` (`PASSWORD`) dient nur als Fallback, wenn noch kein Hash hinterlegt ist.

### Schulname & Schuljahr
Erscheinen in Navigation, Login-Seite und Druckansichten. Ebenfalls unter **Verwaltung â†’ Einstellungen**.

---

## TastaturkÃ¼rzel (Einsatzplan)

| Taste | Funktion |
|---|---|
| `â†“` / `â†‘` | Im Autocomplete-Dropdown navigieren |
| `Enter` | Vorschlag Ã¼bernehmen / Eingabe bestÃ¤tigen |
| `Escape` | Dropdown schlieÃŸen, Eingabe zurÃ¼cksetzen |
| `Tab` | Zum nÃ¤chsten Feld wechseln (speichert automatisch) |



