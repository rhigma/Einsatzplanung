<?php
// Konfiguration
define('PASSWORD', 'CHANGE_ME'); // Vor dem ersten Start durch ein sicheres Passwort ersetzen!
define('DATA_FILE', __DIR__ . '/einsaetze.json');
define('SESSION_NAME', 'stundenplan_session');

session_name(SESSION_NAME);
session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function needsSetup(): bool {
    if (!file_exists(DATA_FILE)) return true;
    $d = json_decode(file_get_contents(DATA_FILE), true) ?? [];
    return empty($d['einstellungen']['passwort_hash']) && PASSWORD === 'CHANGE_ME';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

// Stundentafeln-Vorlagen (Fallback, falls noch keine im JSON)
$_defaultStundentafeln = [
    '1/2' => ['KL' => 1, 'Deutsch' => 8, 'Mathematik' => 5, 'Sachunterricht' => 2, 'Kunst' => 2, 'Musik' => 2, 'Sport' => 3],
    '3'   => ['KL' => 1, 'Deutsch' => 8, 'Mathematik' => 5, 'Sachunterricht' => 3, 'Englisch' => 2, 'Naturwissenschaften' => 2, 'Kunst' => 2, 'Musik' => 2, 'Sport' => 3],
    '4'   => ['KL' => 1, 'Deutsch' => 8, 'Mathematik' => 5, 'Sachunterricht' => 5, 'Englisch' => 3, 'Naturwissenschaften' => 2, 'Gesellschaftswissenschaften' => 2, 'Kunst' => 2, 'Musik' => 2, 'Sport' => 3],
];

function loadData(): array {
    global $_defaultStundentafeln;
    $empty = ['einstellungen' => [], 'stundentafeln' => $_defaultStundentafeln,
              'klassen' => [], 'lehrkraefte' => [], 'einsaetze' => [], 'zusatz' => []];
    if (!file_exists(DATA_FILE)) return $empty;
    $data = json_decode(file_get_contents(DATA_FILE), true) ?? $empty;
    if (empty($data['stundentafeln'])) $data['stundentafeln'] = $_defaultStundentafeln;
    if (!isset($data['klassen']))      $data['klassen']       = [];
    if (!isset($data['zusatz']))       $data['zusatz']        = [];
    if (!isset($data['einstellungen'])) $data['einstellungen'] = [];
    // Migration: KL-Fach in Klassen ergänzen, die es noch nicht haben
    foreach ($data['klassen'] as &$kinfo) {
        if (!isset($kinfo['faecher']['KL'])) {
            $kinfo['faecher'] = array_merge(['KL' => 1], $kinfo['faecher']);
        }
    }
    unset($kinfo);
    ksort($data['stundentafeln']);
    uasort($data['lehrkraefte'], fn($a, $b) => strcmp($a['name'], $b['name']));
    ksort($data['klassen']);
    return $data;
}

// Passwort prüfen: JSON-Hash hat Vorrang vor config-Konstante
function checkPassword(string $input, array $data): bool {
    $hash = $data['einstellungen']['passwort_hash'] ?? null;
    return $hash ? password_verify($input, $hash) : ($input === PASSWORD);
}

function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Normalisiert einen Zellwert zu [{k, std}]-Array.
// Alte Formate (String, String-Array) werden automatisch konvertiert.
// Wenn std null ist, wird aus $sollstd gleichmäßig aufgeteilt.
function normCell($value, int $sollstd = 0): array {
    if (!$value && $value !== 0) return [];
    if (is_string($value)) {
        $entries = [['k' => $value, 'std' => null]];
    } elseif (is_array($value)) {
        $entries = array_values(array_filter(array_map(function ($item) {
            if (is_string($item) && $item) return ['k' => $item, 'std' => null];
            if (is_array($item) && !empty($item['k'])) {
                $std = isset($item['std']) && (int)$item['std'] > 0 ? (int)$item['std'] : null;
                return ['k' => $item['k'], 'std' => $std];
            }
            return null;
        }, $value)));
    } else {
        return [];
    }
    $nullCount = count(array_filter($entries, fn($e) => $e['std'] === null));
    if ($nullCount > 0 && $sollstd > 0) {
        $n = count($entries);
        $entries = array_map(function ($e) use ($sollstd, $n) {
            if ($e['std'] === null) $e['std'] = max(1, (int)round($sollstd / $n));
            return $e;
        }, $entries);
    }
    return $entries;
}

// Globale Variablen aus geladenen Daten setzen
$_d            = loadData();
$klassen       = array_map(fn($k) => $k['faecher'], $_d['klassen']);
$klassenfarben = array_map(fn($k) => $k['farbe'],   $_d['klassen']);
$stundentafeln = $_d['stundentafeln'];
$schulname     = $_d['einstellungen']['schulname'] ?? 'Musterschule';
$schuljahr     = $_d['einstellungen']['schuljahr'] ?? '2026/27';
unset($_d);

// Gesamtstatistik: Bedarf, Verfügbarkeit, bereits verteilt, Zusatzaufgaben
function globalStats(array $data, array $klassen): array {
    $soll = 0; $sollKL = 0;
    foreach ($klassen as $faecher) {
        foreach ($faecher as $fach => $std) {
            if ($fach === 'KL') $sollKL += $std;
            else $soll += $std;
        }
    }
    $verfuegbar = array_sum(array_column($data['lehrkraefte'] ?? [], 'stunden'));
    $verteilt = 0; $verteiltKL = 0;
    foreach ($data['einsaetze'] ?? [] as $faecher) {
        foreach ($faecher as $fach => $value) {
            foreach (normCell($value) as $e) {
                if ($fach === 'KL') $verteiltKL += $e['std'] ?? 0;
                else $verteilt += $e['std'] ?? 0;
            }
        }
    }
    $zusatz = array_sum(array_column($data['zusatz'] ?? [], 'std'));

    // Noch freie Kapazität der Lehrkräfte (Soll − eingetragen, nur positiv)
    $perLk = [];
    foreach ($data['einsaetze'] ?? [] as $kl => $faecher) {
        foreach ($faecher as $fach => $value) {
            foreach (normCell($value) as $e) {
                $perLk[$e['k']] = ($perLk[$e['k']] ?? 0) + ($e['std'] ?? 0);
            }
        }
    }
    foreach ($data['zusatz'] ?? [] as $z) {
        $perLk[$z['k']] = ($perLk[$z['k']] ?? 0) + $z['std'];
    }
    $lkOffen = 0;
    foreach ($data['lehrkraefte'] ?? [] as $k => $lk) {
        $diff = $lk['stunden'] - ($perLk[$k] ?? 0);
        if ($diff > 0) $lkOffen += $diff;
    }

    return [
        'soll'       => $soll,
        'sollKL'     => $sollKL,
        'verfuegbar' => (int)$verfuegbar,
        'verteilt'   => (int)$verteilt,
        'verteiltKL' => (int)$verteiltKL,
        'zusatz'     => (int)$zusatz,
        'lkOffen'    => (int)$lkOffen,
    ];
}
?>
