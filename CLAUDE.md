# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Einsatzplanung** is a web-based teacher assignment planning system for the 33. Grundschule Spandau. It allows school administrators to assign teachers to specific subjects across different grade levels and view allocation summaries.

**Technology Stack:**
- PHP (backend, server-side rendering)
- HTML/CSS (frontend)
- Vanilla JavaScript (client-side interactivity)
- JSON (data persistence)

## Architecture

### Data Model
- **Single JSON file** (`einsaetze.json`): Stores all persistent data
  - `lehrkraefte`: Teacher registry (key: 4-char abbreviation → {name, stunden})
  - `einsaetze`: Assignment mappings (nested: class → subject → teacher abbreviation)

### Core Modules

#### `config.php` (Configuration & Session Management)
- Session initialization with password protection
- Teacher class definitions with subject hour allocations (5 classes: blau 1/2, gelb 1/2, rot 1/2, blau 3, blau 4)
- Subject hour definitions per class
- Helper functions: `isLoggedIn()`, `requireLogin()`, `loadData()`, `saveData()`

#### `index.php` (Login Page)
- Password-protected entry point
- Session-based authentication
- Styled login card with school branding

#### `einsatzplan.php` (Main Planning Interface)
- Interactive grid showing all subjects × classes
- AJAX-based cell editing for teacher assignments (real-time save on blur)
- Teacher hour tracking sidebar (shows allocated vs. total hours per teacher)
- Print-optimized layout
- Uses inline JavaScript for autosave and validation

#### `lehrkraefte.php` (Teacher Overview)
- Card-based display of all teachers with progress bars
- Shows detailed breakdowns of assigned hours by class/subject
- Calculates over/under allocation status
- Print-friendly output

#### `verwaltung.php` (Admin Management)
- Add/remove teachers from the system
- Reset all assignments (dangerous action with confirmation)
- Teacher CRUD operations with inline form validation

#### `untis_export.php` (Untis Export)
- Generates GPU002.TXT file for import into Untis scheduling software
- Maps full subject names to Untis short codes (Deutsch→D, Mathematik→M, etc.)
- Includes all class-based assignments as individual lesson entries
- Includes `zusatz` (additional assignments) as lessons without class
- Exports as downloadable CSV file with standard Untis format fields (dates, weights, period distribution)
- No room data required (field left empty)
- School year start/end dates derived from settings

#### Partial Templates (`partials/`)
- `head.php`: Shared CSS (design system with color variables, layout, forms, tables, buttons)
- `nav.php`: Navigation bar (context-aware active link highlighting, logout button)

### Data Flow
1. User logs in via `index.php` → session set
2. Browse to `einsatzplan.php` (main grid interface)
3. Enter teacher abbreviations in cells → AJAX POST to `einsatzplan.php`
4. Server validates (class exists, teacher exists, subject valid) → updates JSON
5. JavaScript updates UI without page reload (progress chips recalculate)
6. View results in `lehrkraefte.php` or manage in `verwaltung.php`

## Running & Deployment

### Local Development
```bash
# Start PHP development server (port 8000)
php -S localhost:8000 router.php

# Then open browser to http://localhost:8000
```

### Production
- Requires PHP 7.4+ with JSON extension
- Store on any web server with PHP support
- Update password in `config.php` before deployment
- Ensure `einsaetze.json` has write permissions for the web server user

## Design System & Styling

**Colors (CSS variables in `partials/head.php`):**
- `--teal: #2a7c6f` (primary, buttons, focus states)
- `--orange: #e07b39` (accent)
- `--blue: #3a6ea5` (class highlight for blue classes)
- `--red: #dc2626` (errors, over-allocation warnings)

**Class-Specific Colors:**
- Blau classes: `#3a6ea5`
- Gelb classes: `#c49a00`
- Rot classes: `#c0392b`

**Typography:**
- Headers: DM Serif Display (serif)
- Body/UI: DM Sans (sans-serif, weights: 300, 400, 500)

## Important Patterns & Conventions

### Data Validation
- Teacher abbreviations must exist in `lehrkraefte` before assignment
- Classes must match defined entries in `$klassen` array
- Subjects are dynamically discovered from class definitions
- Hour counts are pulled directly from subject hour definitions—not stored redundantly

### Subject Ordering
Subjects follow a predefined order in `einsatzplan.php`: Deutsch → Mathematik → Sachunterricht → Englisch → [others] → alphabetical fallback

### AJAX Error Handling
- Validation errors return JSON `{'ok': false, 'error': 'message'}`
- Error messages are displayed inline below cells for 3 seconds
- No auto-retry on network failures—user must manually resubmit

### Session Security
- Password-based authentication (no users/roles)
- Session timeout handled by PHP default (`session.gc_maxlifetime`)
- Logout destroys session, redirects to login

### JSON Data Format
```json
{
  "lehrkraefte": {
    "BEL": {"name": "Bellmann, Lisa", "stunden": 28},
    "MEL": {"name": "Melchert, Sarah", "stunden": 24}
  },
  "einsaetze": {
    "blau 1/2": {
      "Deutsch": "BEL",
      "Mathematik": "MEL",
      "Sport": ""
    }
  }
}
```

## Common Tasks

### Add a New Grade Class
1. Add entry to `$klassen` array in `config.php` with subject → hours mapping
2. Add color to `$klassenfarben` in `einsatzplan.php`
3. Subject will automatically appear in grids

### Add a New Subject
1. Add subject key to at least one class's hour mapping in `config.php`
2. (Optional) Add to `$fachOrder` in `einsatzplan.php` to control sort position
3. Subject will auto-discover across all classes in the grid

### Change Authentication
- Update `PASSWORD` constant in `config.php`
- No password reset mechanism—only choice is to update config directly

### Export/Backup Data
- Download `einsaetze.json` from server (JSON structure is human-readable and can be edited manually if needed)
- **Untis Export**: Navigate to "Untis Export" in the nav bar to download `GPU002.TXT` for import into Untis

### Subject-Shortcode-Mapping (for Untis Export)
When adding new subjects, add a mapping entry in `shortFach()` in `untis_export.php`:
- `'Neues Fach' => 'Kürzel'`
- Unmapped subjects are auto-abbreviated to max 12 chars

### School Year → Date Calculation
The export in `untis_export.php` parses `schuljahr` (e.g. "2025/26") to derive start date (Sept 1) and end date (July 31). A weight factor of `std / 227.27` is used per Untis convention.

## Debugging Tips

- Check browser console for JavaScript errors (AJAX failures logged)
- Verify `einsaetze.json` permissions if save fails silently
- Use browser DevTools Network tab to inspect AJAX requests/responses
- Teacher hour calculations recalculate in real-time via `updateChips()` JavaScript function

