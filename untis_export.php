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

    $fields = [
        $id,                           //  1 Unterrichts-ID
        $soll,                         //  2 Wochenstunden Soll
        $ist,                          //  3 Wochenstunden Ist
        $soll2,                        //  4 Wochenstunden Soll2
        $klasse,                       //  5 Klasse
        $lehrer,                       //  6 Lehrkraft
        $fachKurz,                     //  7 Fach
        '',                            //  8 Raum
        '',                            //  9
        0,                             // 10
        $stdDec,                       // 11 Wochenstunden (Dezimal)
        '', '', '',                    // 12-14
        $startDate,                    // 15 Startdatum
        $endDate,                      // 16 Enddatum
        $weight,                       // 17 Gewicht
        '', '', '', '', '',            // 18-22
        '',                            // 23
        $klasse !== '' ? 'Bn' : 'n',   // 24 Klassentyp
        '', '', '',                    // 25-27
    ];

    // Verteilung (Halbjahre) – nur wenn std > 1
    if ($std > 1) {
        $fields[] = $dist;             // 28
        $fields[] = $dist;             // 29
    } else {
        $fields[] = '';                // 28
        $fields[] = '';                // 29
    }

    $fields = array_merge($fields, [
        '', '', '', '',                // 30-33
        '',                            // 34
        0, 0,                          // 35-36
        '', '', '', '',                // 37-40
        $stdBig,                       // 41 Stunden × 100000
        $stdDec,                       // 42 Wochenstunden (Dezimal)
        '', '', '', '',                // 43-46
        '',                            // 47
        0,                             // 48
    ]);

    // Hilfsfunktion: Feld quoten (nur nicht-leere Strings > 0 Zeichen ohne Anführungszeichen)
    $csvLine = implode(',', array_map(function ($v) {
        if (is_string($v) && $v !== '' && !is_numeric($v)) {
            return '"' . str_replace('"', '""', $v) . '"';
        }
        return (string)$v;
    }, $fields));
    return $csvLine;
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