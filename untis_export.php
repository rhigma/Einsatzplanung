<?php
/**
 * untis_export.php — Export aller Einsätze als Untis GPU002.TXT
 *
 * Erzeugt eine komma-getrennte Textdatei im Untis-Unterrichts-Format,
 * die direkt in Untis importiert werden kann (Unterrichts-Import).
 *
 * Format basiert auf der Untis-Dokumentation: https://www.untis.at/manual/
 */

require_once 'config.php';
requireLogin();

$data = loadData();

// ---------------------------------------------------------------------------
// 1. Schuljahr → Start-/End-Datum (YYYYMMDD) ableiten
// ---------------------------------------------------------------------------
$defaultSj = $schuljahr; // z. B. "2026/27"
$startYear = 0; $endYear = 0;
if (sscanf($defaultSj, '%d/%d', $startYear, $endYear) !== 2) {
    // Fallback
    $startYear = (int)date('Y');
    $endYear   = $startYear + 1;
}
// 2-stellige Jahreszahlen auf 4-stellig ergänzen
if ($startYear < 100) $startYear += 2000;
if ($endYear   < 100) $endYear   += 2000;
$startDate = sprintf('%04d0901', $startYear);
$endDate   = sprintf('%04d0731', $endYear);

// ---------------------------------------------------------------------------
// 2. Fach-Mapping: vollständiger Name → Untis-Kürzel
// ---------------------------------------------------------------------------
function shortFach(string $fach): string {
    static $map = [
        'Deutsch'                    => 'D',
        'Mathematik'                 => 'M',
        'Sachunterricht'             => 'SU',
        'Englisch'                   => 'E',
        'Kunst'                      => 'Ku',
        'Musik'                      => 'Mu',
        'Sport'                      => 'Sp',
        'Naturwissenschaften'        => 'Nawi',
        'Gesellschaftswissenschaften' => 'Gewi',
        'Schwimmen'                  => 'Schw',
    ];
    if (isset($map[$fach])) return $map[$fach];
    // Für unbekannte Fächer: max. 12 Zeichen, nur Buchstaben/Ziffern
    $short = preg_replace('/[^A-Za-zäöüÄÖÜ0-9]/', '', $fach);
    return substr($short, 0, 12);
}

// ---------------------------------------------------------------------------
// 3. Export-Datei generieren
// ---------------------------------------------------------------------------

$lessonId = 0;
$lines    = [];

// Eine Hilfszeile bauen
$buildLine = function (int $id, int $soll, int $ist, int $soll2, string $klasse,
                       string $lehrer, string $fachKurz) use ($startDate, $endDate): string {
    $std       = $soll;
    $weight    = sprintf('%.5f', round($std / 227.27, 5));
    $stdDec    = sprintf('%.5f', (float)$std);
    $stdBig    = $std * 100000;
    $dist      = (int)floor($std / 2);
    $classType = $klasse !== '' ? 'Bn' : 'n';

    // Felder als Array bauen
    $f = [];

    $q = function ($v, string $mode = 'auto') {
        if ($mode === 'force') return '"' . str_replace('"', '""', $v) . '"';
        if ($mode === 'no')    return (string)$v;
        // auto: nicht-leere Strings, die nicht rein numerisch sind
        if (is_string($v) && $v !== '' && !is_numeric($v)) {
            return '"' . str_replace('"', '""', $v) . '"';
        }
        return (string)$v;
    };
    // Leere Strings immer leer lassen, auch bei force
    $quoteForce = function ($v) use ($q) {
        if ($v === '' || $v === null) return '';
        return $q($v, 'force');
    };

    $f[] = $q($id, 'no');                         //  1 Unterrichts-ID
    $f[] = $q($soll, 'no');                       //  2 Wochenstunden Soll
    $f[] = $q($ist, 'no');                        //  3 Wochenstunden Ist
    $f[] = $q($soll2, 'no');                      //  4 Wochenstunden Soll2
    $f[] = $quoteForce($klasse);                  //  5 Klasse
    $f[] = $q($lehrer, 'force');                  //  6 Lehrkraft
    $f[] = $q($fachKurz, 'force');                //  7 Fach
    $f[] = '';                                   //  8 Raum
    $f[] = '';                                   //  9
    $f[] = '0';                                  // 10
    $f[] = $q($stdDec, 'no');                     // 11 Wochenstunden (Dezimal)
    $f[] = ''; $f[] = ''; $f[] = '';             // 12-14
    $f[] = $q($startDate, 'force');               // 15 Startdatum
    $f[] = $q($endDate, 'force');                 // 16 Enddatum
    $f[] = $q($weight, 'no');                     // 17 Gewicht
    $f[] = ''; $f[] = ''; $f[] = ''; $f[] = ''; $f[] = ''; // 18-22
    $f[] = '';                                   // 23
    $f[] = $quoteForce($classType);               // 24 Klassentyp
    $f[] = ''; $f[] = ''; $f[] = '';             // 25-27

    // Verteilung (Halbjahre)
    if ($std > 1) {
        $f[] = $q($dist, 'no');                   // 28
        $f[] = $q($dist, 'no');                   // 29
    } else {
        $f[] = ''; $f[] = '';                    // 28-29
    }

    $f[] = ''; $f[] = ''; $f[] = ''; $f[] = ''; // 30-33
    $f[] = '';                                   // 34
    $f[] = '0'; $f[] = '0';                      // 35-36
    $f[] = ''; $f[] = ''; $f[] = ''; $f[] = ''; // 37-40
    $f[] = $q($stdBig, 'no');                     // 41 Stunden × 100000
    $f[] = $q($stdDec, 'no');                     // 42 Wochenstunden (Dezimal)
    $f[] = ''; $f[] = ''; $f[] = ''; $f[] = ''; // 43-46
    $f[] = '';                                   // 47
    $f[] = '0';                                  // 48

    return implode(',', $f) . ',';
};

// ---------------------------------------------------------------------------
// 3a. Einsätze (klassengebundener Unterricht) – jeder Lehrer eigener Unterricht
// ---------------------------------------------------------------------------
foreach (($data['einsaetze'] ?? []) as $klasse => $faecher) {
    // Klassen-Info für Stundenbedarf
    $klassenFaecher = $klassen[$klasse] ?? [];

    foreach ($faecher as $fach => $value) {
        $sollStd = (int)($klassenFaecher[$fach] ?? 0);
        $entries = normCell($value, $sollStd);
        if (empty($entries)) continue;
        if ($sollStd <= 0) $sollStd = (int)($entries[0]['std'] ?? 0);

        $fachKurz = shortFach($fach);

        // Jeder Eintrag bekommt eine eigene Unterrichts-ID
        foreach ($entries as $e) {
            $k = $e['k'];
            if (empty($k)) continue;
            $lessonId++;
            $std = (int)($e['std'] ?? $sollStd);
            $lines[] = $buildLine(
                $lessonId, $std, $std, $std,
                $klasse, $k, $fachKurz
            );
        }
    }
}

// ---------------------------------------------------------------------------
// 3b. Zusatzaufgaben (ohne Klasse)
// ---------------------------------------------------------------------------
foreach (($data['zusatz'] ?? []) as $z) {
    $k = $z['k'] ?? '';
    if (empty($k) || !isset($data['lehrkraefte'][$k])) continue;
    $std  = (int)($z['std'] ?? 0);
    if ($std <= 0) continue;

    $lessonId++;
    $bezKurz = shortFach($z['bezeichnung'] ?? 'Zusatz');
    $lines[] = $buildLine(
        $lessonId, $std, $std, $std,
        '', $k, $bezKurz
    );
}

// ---------------------------------------------------------------------------
// 4. Ausgabe
// ---------------------------------------------------------------------------
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="GPU002.TXT"');

echo implode("\r\n", $lines) . "\r\n";