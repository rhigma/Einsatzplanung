<?php
require_once 'config.php';
requireLogin();

$data = loadData();

// Hilfsfunktion: Historieeintrag anhängen (max. 2000 Einträge)
function historieAppend(array &$data, array $entry): void {
    if (!isset($data['historie'])) $data['historie'] = [];
    $data['historie'][] = $entry;
    if (count($data['historie']) > 2000) {
        $data['historie'] = array_slice($data['historie'], -2000);
    }
}

// Normaler POST (kein AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax']) && !isset($_POST['notiz_ajax'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'zaesur') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            historieAppend($data, ['typ' => 'zaesur', 'ts' => time(), 'name' => $name]);
            saveData($data);
        }
    }
    if ($action === 'add_zusatz') {
        $bez = trim($_POST['bezeichnung'] ?? '');
        $k   = ucfirst(strtolower(trim($_POST['kuerzel'] ?? '')));
        $std = intval($_POST['stunden'] ?? 0);
        if ($bez && $k && $std > 0 && isset($data['lehrkraefte'][$k])) {
            $data['zusatz'][] = ['bezeichnung' => $bez, 'k' => $k, 'std' => $std];
            historieAppend($data, ['typ' => 'zusatz_add', 'ts' => time(),
                'bezeichnung' => $bez, 'k' => $k, 'std' => $std]);
            saveData($data);
        }
    }
    if ($action === 'delete_zusatz') {
        $idx = intval($_POST['idx'] ?? -1);
        if ($idx >= 0 && isset($data['zusatz'][$idx])) {
            $z = $data['zusatz'][$idx];
            historieAppend($data, ['typ' => 'zusatz_del', 'ts' => time(),
                'bezeichnung' => $z['bezeichnung'], 'k' => $z['k'], 'std' => $z['std']]);
            array_splice($data['zusatz'], $idx, 1);
            saveData($data);
        }
    }
    header('Location: einsatzplan.php');
    exit;
}

// Notiz speichern (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notiz_ajax'])) {
    $klasse = $_POST['klasse'] ?? '';
    $fach   = $_POST['fach']   ?? '';
    $notiz  = trim($_POST['notiz'] ?? '');
    if (array_key_exists($klasse, $GLOBALS['klassen'] ?? [])) {
        if (!isset($data['notizen'])) $data['notizen'] = [];
        if (!isset($data['notizen'][$klasse])) $data['notizen'][$klasse] = [];
        $alt = $data['notizen'][$klasse][$fach] ?? '';
        if ($notiz) $data['notizen'][$klasse][$fach] = $notiz;
        else unset($data['notizen'][$klasse][$fach]);
        if ($alt !== $notiz) {
            historieAppend($data, ['typ' => 'notiz', 'ts' => time(),
                'klasse' => $klasse, 'fach' => $fach, 'alt' => $alt, 'neu' => $notiz]);
        }
        saveData($data);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// Einsatz speichern (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $klasse  = $_POST['klasse'] ?? '';
    $fach    = $_POST['fach']   ?? '';
    $rawKs   = (array)($_POST['k']   ?? []);
    $rawStds = (array)($_POST['std'] ?? []);

    if (!array_key_exists($klasse, $GLOBALS['klassen'] ?? [])) {
        echo json_encode(['ok' => false, 'error' => 'Unbekannte Klasse']);
        exit;
    }

    $rows = [];
    for ($i = 0; $i < count($rawKs); $i++) {
        $k   = ucfirst(strtolower(trim($rawKs[$i] ?? '')));
        $std = (int)($rawStds[$i] ?? 0);
        if (!$k || $std <= 0) continue;
        if (!array_key_exists($k, $data['lehrkraefte'])) {
            echo json_encode(['ok' => false, 'error' => 'Unbekanntes Kürzel: ' . $k]);
            exit;
        }
        $rows[] = ['k' => $k, 'std' => $std];
    }

    if (!isset($data['einsaetze'][$klasse])) {
        $data['einsaetze'][$klasse] = [];
    }

    // Alte Belegung für Historie merken
    $sollstd = $klassen[$klasse][$fach] ?? 0;
    $alt = array_values(array_filter(
        normCell($data['einsaetze'][$klasse][$fach] ?? null, $sollstd),
        fn($e) => $e['k'] && ($e['std'] ?? 0) > 0
    ));

    $data['einsaetze'][$klasse][$fach] = $rows;

    // Nur aufzeichnen wenn sich etwas geändert hat
    if (json_encode($alt) !== json_encode($rows)) {
        historieAppend($data, [
            'typ'    => 'aenderung',
            'ts'     => time(),
            'klasse' => $klasse,
            'fach'   => $fach,
            'alt'    => $alt,
            'neu'    => $rows,
        ]);
    }

    saveData($data);
    echo json_encode(['ok' => true]);
    exit;
}

// Alle Fächer ermitteln (Union aller Klassen)
$alleFaecher = [];
foreach ($klassen as $faecher) {
    foreach (array_keys($faecher) as $f) {
        $alleFaecher[$f] = true;
    }
}
$alleFaecher = array_keys($alleFaecher);
$fachOrder = ['KL','Deutsch','Mathematik','Sachunterricht','Englisch','Naturwissenschaften','Gesellschaftswissenschaften','Kunst','Musik','Sport'];
usort($alleFaecher, function($a, $b) use ($fachOrder) {
    $ia = array_search($a, $fachOrder);
    $ib = array_search($b, $fachOrder);
    if ($ia === false) $ia = 99;
    if ($ib === false) $ib = 99;
    return $ia - $ib;
});

// Eingetragene Stunden pro Lehrkraft (Unterricht + Zusatzaufgaben)
function stundenProLehrkraft($data, $klassen) {
    $result = [];
    foreach ($data['einsaetze'] ?? [] as $klasse => $faecher) {
        foreach ($faecher as $fach => $value) {
            foreach (normCell($value, $klassen[$klasse][$fach] ?? 0) as $e) {
                if (!($e['std'] ?? 0)) continue;
                $result[$e['k']] = ($result[$e['k']] ?? 0) + $e['std'];
            }
        }
    }
    foreach ($data['zusatz'] ?? [] as $z) {
        $result[$z['k']] = ($result[$z['k']] ?? 0) + $z['std'];
    }
    return $result;
}

$stundenEingetragen = stundenProLehrkraft($data, $klassen);

// Abkürzungen für Fächer (Spaltenheader); fehlt ein Eintrag, wird der Fachname selbst genutzt
$fachKurz = [
    'KL'                    => 'KL',
    'Deutsch'               => 'D',
    'Mathematik'            => 'M',
    'Sachunterricht'        => 'SU',
    'Englisch'              => 'E',
    'Kunst'                 => 'Ku',
    'Musik'                 => 'Mu',
    'Sport'                 => 'Sp',
    'Naturwissenschaften'   => 'Nawi',
    'Gesellschaftswissenschaften' => 'Gewi',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Einsatzplan – Einsatzplanung 2026/27</title>
<?php include 'partials/head.php'; ?>
<style>
  .einsatzplan-wrap {
    overflow: auto;
    max-height: calc(100vh - 220px);
  }

  .einsatzplan {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
    font-size: 0.85rem;
  }

  .cell-notiz {
    display: block;
    width: 100%;
    border: none;
    border-top: 1px dashed var(--border);
    background: transparent;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.68rem;
    color: var(--muted);
    padding: 3px 5px;
    resize: none;
    line-height: 1.35;
    overflow: hidden;
    min-height: 20px;
    transition: background 0.15s, color 0.15s;
  }
  .cell-notiz:focus { outline: none; background: #fffdf0; color: var(--text); }
  .cell-notiz::placeholder { color: var(--border); font-style: italic; }
  .cell-notiz.has-notiz { color: #b45309; border-top-color: #fcd34d; }

  .einsatzplan .soll-label {
    font-size: 0.7rem;
    color: var(--muted);
    text-align: center;
    padding: 3px 0 4px;
    border-top: 1px solid var(--border);
    line-height: 1;
    user-select: none;
  }

  .einsatzplan th, .einsatzplan td {
    border: 1px solid var(--border);
    padding: 0;
    text-align: center;
  }

  .einsatzplan thead th {
    background: var(--bg);
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 2;
  }

  .einsatzplan .klasse-header {
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
    padding: 0.5rem 0.875rem;
    letter-spacing: 0.03em;
    text-transform: none;
    white-space: nowrap;
    text-align: left;
  }

  .einsatzplan thead th:not(.klasse-header) {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
  }

  .cell {
    min-width: 90px;
    position: relative;
    vertical-align: top;
  }

  .cell.empty-fach { background: #f9f9f9; }

  /* Sollstunden noch nicht erreicht → leicht rot */
  .cell.needs-hours { background: #fff5f5; }

  /* Mehr Stunden eingetragen als Sollstunden → leicht orange */
  .cell.doppelt { background: #fff7ed; }

  /* Lehrkraft-Filter-Highlight: nur das Kürzel-Feld */
  .inp-k.highlight {
    background: var(--teal) !important;
    color: white !important;
    border-radius: 3px;
  }

  .cell-row {
    display: flex;
    align-items: stretch;
    height: 40px;
  }

  .cell-row + .cell-row {
    border-top: 1px solid var(--border);
  }

  .inp-k {
    flex: 1;
    min-width: 0;
    border: none;
    background: transparent;
    text-align: center;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text);
    cursor: pointer;
    padding: 0 0.3rem;
  }

  .inp-k:focus { outline: none; background: var(--teal-lt); }
  .inp-k.filled { color: var(--teal); }
  .inp-k.saving, .inp-std.saving { opacity: 0.5; }

  .inp-std {
    width: 38px;
    border: none;
    border-left: 1px solid var(--border);
    background: transparent;
    text-align: center;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text);
    cursor: pointer;
    padding: 0;
    -moz-appearance: textfield;
  }
  .inp-std::-webkit-inner-spin-button,
  .inp-std::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
  .inp-std:focus { outline: none; background: var(--teal-lt); }
  .inp-std.filled { color: var(--teal); }

  .cell .error-msg {
    position: absolute;
    bottom: -20px;
    left: 0;
    right: 0;
    font-size: 0.65rem;
    color: var(--red);
    background: white;
    border: 1px solid var(--red);
    border-radius: 3px;
    padding: 1px 4px;
    z-index: 10;
    white-space: nowrap;
  }

  /* Autocomplete-Dropdown */
  .ac-dropdown {
    position: fixed;
    background: var(--card);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 6px 24px rgba(0,0,0,0.12);
    z-index: 9999;
    overflow: hidden;
    min-width: 180px;
  }
  .ac-item {
    padding: 0.45rem 0.75rem;
    cursor: pointer;
    font-size: 0.82rem;
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    white-space: nowrap;
  }
  .ac-item:hover, .ac-item.active {
    background: var(--teal-lt);
  }
  .ac-item .ac-k    { font-weight: 600; color: var(--teal); }
  .ac-item .ac-name { color: var(--muted); font-size: 0.75rem; }

  /* Sidebar: Stunden-Übersicht */
  .sidebar {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
  }

  .lk-chip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--card);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
  }

  .lk-chip .kuerzel {
    font-weight: 600;
    font-size: 0.85rem;
  }

  .lk-chip .stunden-info {
    color: var(--muted);
    font-size: 0.75rem;
  }

  .lk-chip.ok    { border-color: #6ee7b7; }
  .lk-chip.over  { border-color: #fca5a5; }
  .lk-chip.under { border-color: var(--border); }
  .lk-chip.active-filter {
    border-color: var(--teal);
    background: var(--teal-lt);
    color: var(--teal);
  }

  .chip-sort-btn {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 5px;
    border: 1px solid var(--border);
    background: transparent;
    cursor: pointer;
    color: var(--muted);
    line-height: 1.4;
  }
  .chip-sort-btn.active {
    background: var(--teal-lt);
    color: var(--teal);
    border-color: var(--teal);
    font-weight: 500;
  }

  #lk-popover {
    position: fixed;
    z-index: 500;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.875rem 1rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.14);
    width: 270px;
    max-height: 420px;
    overflow-y: auto;
    font-size: 0.82rem;
    pointer-events: auto;
  }
  .pop-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 0.5rem;
  }
  .pop-name { font-weight: 500; font-size: 0.9rem; }
  .pop-k    { color: var(--muted); font-size: 0.72rem; }
  .pop-bar  { height: 5px; background: var(--bg); border-radius: 3px; margin-bottom: 0.5rem; overflow: hidden; }
  .pop-bar-fill { height: 100%; border-radius: 3px; background: var(--teal); }
  .pop-bar-fill.over { background: var(--red); }
  .pop-stunden { color: var(--muted); font-size: 0.78rem; margin-bottom: 0.6rem; }
  .pop-list { list-style: none; display: flex; flex-direction: column; }
  .pop-list li {
    display: flex;
    justify-content: space-between;
    padding: 0.22rem 0;
    border-bottom: 1px solid var(--border);
    gap: 0.5rem;
  }
  .pop-list li:last-child { border-bottom: none; }
  .pop-list .pop-muted { color: var(--muted); font-size: 0.7rem; }
  .pop-empty { color: var(--muted); font-style: italic; font-size: 0.78rem; margin-top: 0.25rem; }

  .row-filter-toggle {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.7rem;
    color: var(--muted);
    cursor: pointer;
    user-select: none;
    padding-left: 0.75rem;
    border-left: 1px solid var(--border);
    margin-right: 0.25rem;
  }
  .row-filter-toggle.disabled { opacity: 0.4; pointer-events: none; }
  .toggle-track {
    width: 28px; height: 16px;
    background: var(--border);
    border-radius: 8px;
    position: relative;
    transition: background 0.2s;
    flex-shrink: 0;
  }
  .toggle-thumb {
    position: absolute;
    width: 12px; height: 12px;
    background: white;
    border-radius: 50%;
    top: 2px; left: 2px;
    transition: left 0.15s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
  }
  .toggle-track.on { background: var(--teal); }
  .toggle-track.on .toggle-thumb { left: 14px; }

  .print-btn {
    float: right;
  }
</style>
</head>
<body>
<?php include 'partials/nav.php'; ?>

<main class="container">
  <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem; flex-wrap:wrap;">
    <h1 style="margin-bottom:0">Einsatzplan</h1>
<form method="post" class="no-print"
      style="display:flex;gap:0.5rem;align-items:center;margin-left:auto">
      <input type="hidden" name="action" value="zaesur">
      <span class="tip" data-tip="Benannten Zeitstempel in die Änderungshistorie einfügen – z.B. 'Wünsche erfasst' oder 'Finale Version'. Hilft später dabei, Planungsphasen zu unterscheiden.">?</span>
      <input type="text" name="name" placeholder="Zäsur setzen …"
        style="padding:0.35rem 0.75rem;font-size:0.85rem;border-radius:7px;
               border:1.5px solid var(--border);background:var(--bg);width:200px">
      <button type="submit" class="btn btn-secondary btn-sm">Zäsur</button>
    </form>
  </div>

  <!-- Lehrkräfte-Übersicht -->
  <?php if (!empty($data['lehrkraefte'])): ?>
  <div class="sidebar no-print" style="align-items:center">
    <div class="no-print" style="display:flex;gap:0.25rem;align-items:center;margin-right:0.25rem">
      <span style="font-size:0.7rem;color:var(--muted)">Sortierung:</span>
      <button class="chip-sort-btn active" data-sort="az">A–Z</button>
      <button class="chip-sort-btn" data-sort="offen">Offen ↓</button>
    </div>
    <label id="row-filter-toggle" class="row-filter-toggle no-print disabled" title="Nur aktiv wenn eine Lehrkraft markiert ist">
      <span class="toggle-track"><span class="toggle-thumb"></span></span>
      Nur Klassen mit Einsatz
    </label>
    <?php foreach ($data['lehrkraefte'] as $k => $lk):
      $eingetragen = (int)($stundenEingetragen[$k] ?? 0);
      $gesamt = $lk['stunden'];
      $status = $eingetragen > $gesamt ? 'over' : ($eingetragen === $gesamt ? 'ok' : 'under');
    ?>
    <div class="lk-chip <?= $status ?>"
         data-kuerzel="<?= htmlspecialchars($k) ?>"
         data-offen="<?= $gesamt - $eingetragen ?>">
      <span class="kuerzel"><?= htmlspecialchars($k) ?></span>
      <span class="stunden-info"><?= $eingetragen ?>/<?= $gesamt ?> Std.</span>
    </div>
    <?php endforeach; ?>
    <span class="tip" style="margin-left:0.25rem" data-tip="Klick auf ein Kürzel hebt alle Einsätze dieser Lehrkraft in der Tabelle hervor. Farben: grüner Rand = Soll erfüllt, roter Rand = überzogen, grauer Rand = noch offen.">?</span>
  </div>
  <?php endif; ?>

  <!-- Einsatzplan-Tabelle -->
  <div class="card">
    <div class="einsatzplan-wrap">
      <table class="einsatzplan">
        <thead>
          <tr>
            <th style="text-align:left; padding:0.5rem 0.875rem; width:80px;">Klasse</th>
            <?php foreach ($alleFaecher as $fach): ?>
            <th title="<?= htmlspecialchars($fach) ?>">
              <?= htmlspecialchars($fachKurz[$fach] ?? $fach) ?>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($klassen as $klassenname => $faecher):
            $color = $klassenfarben[$klassenname] ?? '#555555';
          ?>
          <tr>
            <th class="klasse-header" style="background:<?= $color ?>">
              <?= htmlspecialchars($klassenname) ?>
            </th>
            <?php foreach ($alleFaecher as $fach): ?>
              <?php if (isset($faecher[$fach])): ?>
                <?php
                  $sollstd = $faecher[$fach];
                  $cellVal = normCell($data['einsaetze'][$klassenname][$fach] ?? null, $sollstd);
                  $sumStd  = array_sum(array_column($cellVal, 'std'));
                  $cellCls = 'cell';
                  if ($sumStd < $sollstd)                    $cellCls .= ' needs-hours';
                  elseif ($sumStd > $sollstd && $sumStd > 0) $cellCls .= ' doppelt';
                ?>
                <?php if ($fach === 'KL'): ?>
                <td class="<?= $cellCls ?> cell-kl"
                  data-klasse="<?= htmlspecialchars($klassenname) ?>"
                  data-fach="KL"
                  data-sollstd="1"
                  data-saved="<?= htmlspecialchars(json_encode($cellVal), ENT_QUOTES) ?>">
                  <div class="cell-row">
                    <input type="text" class="inp-k<?= !empty($cellVal) ? ' filled' : '' ?>"
                      maxlength="10"
                      value="<?= !empty($cellVal) ? htmlspecialchars($cellVal[0]['k']) : '' ?>"
                      placeholder="–">
                  </div>
                  <?php $notiz = $data['notizen'][$klassenname]['KL'] ?? ''; ?>
                  <textarea class="cell-notiz<?= $notiz ? ' has-notiz' : '' ?>"
                    rows="1" placeholder="Notiz …"
                    data-klasse="<?= htmlspecialchars($klassenname) ?>"
                    data-fach="KL"><?= htmlspecialchars($notiz) ?></textarea>
                </td>
                <?php else: ?>
                <td class="<?= $cellCls ?>"
                  data-klasse="<?= htmlspecialchars($klassenname) ?>"
                  data-fach="<?= htmlspecialchars($fach) ?>"
                  data-sollstd="<?= $sollstd ?>"
                  data-saved="<?= htmlspecialchars(json_encode($cellVal), ENT_QUOTES) ?>"
                  title="Soll: <?= $sollstd ?> Std.">
                  <?php foreach ($cellVal as $entry): ?>
                  <div class="cell-row">
                    <input type="text" class="inp-k filled" maxlength="10"
                      value="<?= htmlspecialchars($entry['k']) ?>">
                    <input type="number" class="inp-std<?= $entry['std'] ? ' filled' : '' ?>"
                      min="1" max="30"
                      value="<?= $entry['std'] !== null ? (int)$entry['std'] : '' ?>">
                  </div>
                  <?php endforeach; ?>
                  <div class="cell-row add-row">
                    <input type="text" class="inp-k" maxlength="10"
                      placeholder="<?= empty($cellVal) ? '–' : '+' ?>">
                    <input type="number" class="inp-std" min="1" max="30" placeholder="">
                  </div>
                  <div class="soll-label"><?= $sumStd ?>/<?= $sollstd ?> Std.</div>
                  <?php $notiz = $data['notizen'][$klassenname][$fach] ?? ''; ?>
                  <textarea class="cell-notiz<?= $notiz ? ' has-notiz' : '' ?>"
                    rows="1" placeholder="Notiz …"
                    data-klasse="<?= htmlspecialchars($klassenname) ?>"
                    data-fach="<?= htmlspecialchars($fach) ?>"><?= htmlspecialchars($notiz) ?></textarea>
                </td>
                <?php endif; ?>
              <?php else: ?>
                <td class="cell empty-fach"></td>
              <?php endif; ?>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (empty($data['lehrkraefte'])): ?>
  <p class="muted no-print">Noch keine Lehrkräfte angelegt. Gehe zu <a href="verwaltung.php">Verwaltung</a>.</p>
  <?php endif; ?>

  <!-- Zusatzaufgaben -->
  <div class="card no-print">
    <h2>Zusatzaufgaben</h2>

    <?php if (!empty($data['zusatz'])): ?>
    <table class="table" style="margin-bottom:1.25rem">
      <thead><tr><th>Bezeichnung</th><th>Lehrkraft</th><th>Std./Woche</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($data['zusatz'] as $i => $z): ?>
        <tr>
          <td><?= htmlspecialchars($z['bezeichnung']) ?></td>
          <td><strong><?= htmlspecialchars($z['k']) ?></strong></td>
          <td><?= $z['std'] ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="delete_zusatz">
              <input type="hidden" name="idx" value="<?= $i ?>">
              <button type="submit" class="btn btn-danger btn-sm">Entfernen</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <form method="post" class="form-row">
      <input type="hidden" name="action" value="add_zusatz">
      <div class="form-group" style="flex:2">
        <label>Bezeichnung</label>
        <input type="text" name="bezeichnung" placeholder="z.B. AG Basketball">
      </div>
      <div class="form-group">
        <label>Lehrkraft</label>
        <select name="kuerzel" required>
          <option value="">– wählen –</option>
          <?php foreach ($data['lehrkraefte'] as $k => $lk): ?>
          <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?> – <?= htmlspecialchars($lk['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Std./Woche</label>
        <input type="number" name="stunden" min="1" placeholder="2">
      </div>
      <button type="submit" class="btn btn-primary">Hinzufügen</button>
    </form>
  </div>
</main>

<script>
// ── Chip-Sortierung ──────────────────────────────────────────────────────────
let chipSort = 'az';
const chipSidebar = document.querySelector('.sidebar');

function sortChips() {
  const tip = chipSidebar.querySelector('.tip');
  const chips = [...chipSidebar.querySelectorAll('.lk-chip')];
  chips.sort((a, b) => chipSort === 'az'
    ? a.dataset.kuerzel.localeCompare(b.dataset.kuerzel)
    : parseInt(b.dataset.offen) - parseInt(a.dataset.offen)
  );
  chips.forEach(c => chipSidebar.insertBefore(c, tip));
}

document.querySelectorAll('.chip-sort-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.chip-sort-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    chipSort = btn.dataset.sort;
    sortChips();
  });
});

sortChips();

// ── Chip-Popover ─────────────────────────────────────────────────────────────
const pop = document.createElement('div');
pop.id = 'lk-popover';
pop.style.display = 'none';
document.body.appendChild(pop);
let popHideTimer;

function buildEinsaetze(k) {
  const result = [];
  document.querySelectorAll('.cell[data-fach]').forEach(cell => {
    const entries = JSON.parse(cell.dataset.saved || '[]');
    const mine = entries.filter(e => e.k === k);
    if (!mine.length) return;
    const geteilt = entries.length > 1;
    mine.forEach(e => result.push({
      klasse: cell.dataset.klasse, fach: cell.dataset.fach,
      stunden: e.std || 0, geteilt,
    }));
  });
  return result;
}

function showPopover(chip) {
  const k = chip.querySelector('.kuerzel').textContent;
  const d = LK_DETAIL[k];
  if (!d) return;

  const infoText = chip.querySelector('.stunden-info').textContent;
  const eingetragen = parseInt(infoText);
  const gesamt = d.stunden;
  const offen = gesamt - eingetragen;
  const pct = gesamt > 0 ? Math.min(100, Math.round(eingetragen / gesamt * 100)) : 0;
  const isOver = eingetragen > gesamt;

  let offerStr = '';
  if (offen > 0)       offerStr = ` · <strong>${offen} offen</strong>`;
  else if (offen < 0)  offerStr = ` · <strong style="color:var(--red)">${Math.abs(offen)} zu viel</strong>`;
  else                 offerStr = ` · <strong style="color:#065f46">✓</strong>`;

  const einsaetze = buildEinsaetze(k);
  let listHtml = '';
  if (einsaetze.length || d.zusatz.length) {
    listHtml = '<ul class="pop-list">';
    einsaetze.forEach(e => {
      listHtml += `<li><span>${e.klasse} – ${e.fach}${e.geteilt ? ' <span class="pop-muted">(geteilt)</span>' : ''}</span><span style="white-space:nowrap">${e.stunden} Std.</span></li>`;
    });
    d.zusatz.forEach(z => {
      listHtml += `<li style="font-style:italic"><span>${z.bezeichnung}</span><span style="white-space:nowrap">${z.std} Std.</span></li>`;
    });
    listHtml += '</ul>';
  } else {
    listHtml = '<p class="pop-empty">Noch keine Einsätze eingetragen.</p>';
  }

  pop.innerHTML = `
    <div class="pop-header">
      <span class="pop-name">${d.name}</span>
      <span class="pop-k">${k}</span>
    </div>
    <div class="pop-bar"><div class="pop-bar-fill${isOver ? ' over' : ''}" style="width:${pct}%"></div></div>
    <div class="pop-stunden">${eingetragen}/${gesamt} Std.${offerStr}</div>
    ${listHtml}`;

  pop.style.display = 'block';
  const rect = chip.getBoundingClientRect();
  const left = Math.min(rect.left, window.innerWidth - 278);
  pop.style.left = Math.max(4, left) + 'px';
  pop.style.top  = (rect.bottom + 6) + 'px';
}

document.querySelectorAll('.lk-chip').forEach(chip => {
  chip.addEventListener('mouseenter', () => { clearTimeout(popHideTimer); showPopover(chip); });
  chip.addEventListener('mouseleave', () => { popHideTimer = setTimeout(() => pop.style.display = 'none', 150); });
});
pop.addEventListener('mouseenter', () => clearTimeout(popHideTimer));
pop.addEventListener('mouseleave', () => { pop.style.display = 'none'; });

// ── Lehrkraft-Filter (Klick auf Chip → Zellen highlighten) ──────────────────
let activeFilter = null;
let filterRows    = false;

const rowFilterToggle = document.getElementById('row-filter-toggle');
const rowFilterTrack  = rowFilterToggle.querySelector('.toggle-track');

rowFilterToggle.addEventListener('click', () => {
  filterRows = !filterRows;
  rowFilterTrack.classList.toggle('on', filterRows);
  refreshHighlight();
});

document.querySelectorAll('.lk-chip').forEach(chip => {
  chip.style.cursor = 'pointer';
  chip.title = 'Klicken zum Hervorheben';
  chip.addEventListener('click', function () {
    const k = this.querySelector('.kuerzel').textContent;
    if (activeFilter === k) {
      activeFilter = null;
      this.classList.remove('active-filter');
    } else {
      document.querySelector('.lk-chip.active-filter')?.classList.remove('active-filter');
      activeFilter = k;
      this.classList.add('active-filter');
    }
    rowFilterToggle.classList.toggle('disabled', !activeFilter);
    refreshHighlight();
  });
});

function refreshHighlight() {
  document.querySelectorAll('.cell .inp-k').forEach(inp => {
    inp.classList.toggle('highlight', !!activeFilter && inp.value.trim() === activeFilter);
  });

  document.querySelectorAll('.einsatzplan tbody tr').forEach(row => {
    if (filterRows && activeFilter) {
      const hasTeacher = [...row.querySelectorAll('.cell[data-saved]')].some(cell =>
        JSON.parse(cell.dataset.saved || '[]').some(e => e.k === activeFilter)
      );
      row.style.display = hasTeacher ? '' : 'none';
    } else {
      row.style.display = '';
    }
  });
}

// ── Hilfsfunktionen ─────────────────────────────────────────────────────────
function getCellEntries(cell) {
  if (cell.classList.contains('cell-kl')) {
    const k = cell.querySelector('.inp-k')?.value.trim();
    return k ? [{ k, std: 1 }] : [];
  }
  return Array.from(cell.querySelectorAll('.cell-row:not(.add-row)'))
    .map(row => ({
      k:   row.querySelector('.inp-k').value.trim(),
      std: parseInt(row.querySelector('.inp-std').value) || 0
    }))
    .filter(e => e.k && e.std > 0);
}

function makeAddRow(hasOthers) {
  const row = document.createElement('div');
  row.className = 'cell-row add-row';
  const kInp = document.createElement('input');
  kInp.type = 'text'; kInp.className = 'inp-k';
  kInp.maxLength = 4; kInp.placeholder = hasOthers ? '+' : '–';
  const sInp = document.createElement('input');
  sInp.type = 'number'; sInp.className = 'inp-std';
  sInp.min = 1; sInp.max = 30; sInp.placeholder = '';
  row.appendChild(kInp); row.appendChild(sInp);
  return row;
}

// ── Tastatur & Fokus ────────────────────────────────────────────────────────
document.addEventListener('focus', function (e) {
  const inp = e.target;
  if (!inp.matches('.cell .inp-k, .cell .inp-std')) return;
  inp.dataset.orig = inp.value;
}, true);

// ── Autocomplete ────────────────────────────────────────────────────────────
<?php
$lkJs = [];
foreach ($data['lehrkraefte'] as $k => $v) {
    $lkJs[] = ['k' => $k, 'name' => $v['name']];
}
$lkDetail = [];
foreach ($data['lehrkraefte'] as $k => $v) {
    $zusatz = array_values(array_filter(
        $data['zusatz'] ?? [],
        fn($z) => $z['k'] === $k
    ));
    $lkDetail[$k] = ['name' => $v['name'], 'stunden' => $v['stunden'], 'zusatz' => $zusatz];
}
?>
const LEHRKRAEFTE = <?= json_encode($lkJs) ?>;
const LK_DETAIL   = <?= json_encode($lkDetail) ?>;

const acDrop = document.createElement('div');
acDrop.className = 'ac-dropdown';
acDrop.style.display = 'none';
document.body.appendChild(acDrop);

let acInput = null, acMatches = [], acIdx = -1;

function acFilter(val) {
  const q = val.toLowerCase();
  return LEHRKRAEFTE.filter(m =>
    m.k.toLowerCase().startsWith(q) || m.name.toLowerCase().includes(q)
  ).slice(0, 8);
}

function acShow(inp, matches) {
  acInput = inp; acMatches = matches; acIdx = -1;
  acDrop.innerHTML = '';
  if (!matches.length) { acHide(); return; }
  matches.forEach((m) => {
    const el = document.createElement('div');
    el.className = 'ac-item';
    el.innerHTML = `<span class="ac-k">${m.k}</span><span class="ac-name">${m.name}</span>`;
    el.addEventListener('mousedown', e => { e.preventDefault(); acSelect(m.k); });
    acDrop.appendChild(el);
  });
  const r = inp.getBoundingClientRect();
  Object.assign(acDrop.style, {
    display: 'block',
    left: r.left + 'px',
    top: (r.bottom + 2) + 'px',
    minWidth: Math.max(180, r.width) + 'px',
  });
}

function acHide() {
  acDrop.style.display = 'none';
  acInput = null; acMatches = []; acIdx = -1;
}

function acHighlight(idx) {
  acIdx = idx;
  acDrop.querySelectorAll('.ac-item').forEach((el, i) => {
    el.classList.toggle('active', i === idx);
    if (i === idx) el.scrollIntoView({ block: 'nearest' });
  });
}

function acSelect(k) {
  if (!acInput) return;
  acInput.value = k;
  acInput.classList.toggle('filled', k.length > 0);
  acHide();
  const cell = acInput.closest('.cell');
  if (cell?.classList.contains('cell-kl')) {
    acInput.blur();
  } else {
    const stdInp = acInput.closest('.cell-row')?.querySelector('.inp-std');
    if (stdInp) { stdInp.focus(); stdInp.select(); }
  }
}

document.addEventListener('input', function (e) {
  const inp = e.target;
  if (!inp.matches('.cell .inp-k')) return;
  const val = inp.value.trim();
  if (!val) { acHide(); return; }
  acShow(inp, acFilter(val));
}, true);

// ── Tastatur ────────────────────────────────────────────────────────────────
document.addEventListener('keydown', function (e) {
  // Autocomplete-Navigation hat Vorrang
  if (acDrop.style.display !== 'none') {
    const n = acMatches.length;
    if (e.key === 'ArrowDown') {
      e.preventDefault(); acHighlight(Math.min(acIdx + 1, n - 1)); return;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault(); acHighlight(Math.max(acIdx - 1, 0)); return;
    }
    if (e.key === 'Enter' && acIdx >= 0) {
      e.preventDefault(); e.stopPropagation(); acSelect(acMatches[acIdx].k); return;
    }
    if (e.key === 'Escape') { acHide(); return; }
  }

  const inp = e.target;
  if (!inp.matches('.cell .inp-k, .cell .inp-std')) return;
  if (e.key === 'Enter') { e.preventDefault(); inp.blur(); }
  if (e.key === 'Escape') { inp.value = inp.dataset.orig || ''; inp.blur(); }
}, true);

// ── Autosave bei Verlassen ──────────────────────────────────────────────────
document.addEventListener('blur', function (e) {
  const inp = e.target;
  if (!inp.matches('.cell .inp-k, .cell .inp-std')) return;
  acHide();

  const cell = inp.closest('.cell');

  // KL-Sonderbehandlung: nur Kürzel, automatisch std=1
  if (cell.classList.contains('cell-kl')) {
    const raw = inp.value.trim();
    inp.value = raw ? raw[0].toUpperCase() + raw.slice(1).toLowerCase() : '';
    inp.classList.toggle('filled', inp.value.length > 0);
    saveCell(cell);
    return;
  }

  const row  = inp.closest('.cell-row');
  if (!row) return;

  const isAddRow = row.classList.contains('add-row');
  const kInp = row.querySelector('.inp-k');
  const sInp = row.querySelector('.inp-std');

  // Kürzel normalisieren
  if (inp === kInp) {
    const raw = inp.value.trim();
    inp.value = raw ? raw[0].toUpperCase() + raw.slice(1).toLowerCase() : '';
  }

  const kVal = kInp.value.trim();
  const sVal = parseInt(sInp.value) || 0;

  if (isAddRow) {
    if (kVal && sVal > 0) {
      // Add-Zeile vollständig → zur normalen Zeile machen
      row.classList.remove('add-row');
      kInp.classList.add('filled');
      sInp.classList.add('filled');
      cell.appendChild(makeAddRow(true));
      saveCell(cell);
    }
    // Unvollständige Add-Zeile → noch nicht speichern
  } else {
    if (!kVal) {
      // Kürzel geleert → Zeile entfernen
      row.remove();
      const addRow = cell.querySelector('.add-row');
      if (addRow && !cell.querySelector('.cell-row:not(.add-row)')) {
        addRow.querySelector('.inp-k').placeholder = '–';
      }
      saveCell(cell);
    } else {
      inp.classList.toggle('filled', inp === kInp ? kVal.length > 0 : sVal > 0);
      saveCell(cell);
    }
  }
}, true);

// ── Speichern ───────────────────────────────────────────────────────────────
function saveCell(cell) {
  const klasse = cell.dataset.klasse;
  const fach   = cell.dataset.fach;
  const entries = getCellEntries(cell);

  const saved = JSON.parse(cell.dataset.saved || '[]')
    .filter(e => e.k && (e.std ?? 0) > 0);
  if (JSON.stringify(entries) === JSON.stringify(saved)) return;

  cell.querySelectorAll('.error-msg').forEach(m => m.remove());
  cell.querySelectorAll('input').forEach(i => i.classList.add('saving'));

  const body = new URLSearchParams({ ajax: '1', klasse, fach });
  entries.forEach(e => { body.append('k[]', e.k); body.append('std[]', e.std); });

  fetch('einsatzplan.php', { method: 'POST', body })
    .then(r => r.json())
    .then(result => {
      cell.querySelectorAll('input').forEach(i => i.classList.remove('saving'));
      if (result.ok) {
        cell.dataset.saved = JSON.stringify(entries);
        updateCellStatus(cell);
        updateChips();
        if (activeFilter) refreshHighlight();
      } else {
        const msg = document.createElement('span');
        msg.className = 'error-msg';
        msg.textContent = result.error;
        cell.appendChild(msg);
        setTimeout(() => msg.remove(), 3000);
      }
    })
    .catch(() => cell.querySelectorAll('input').forEach(i => i.classList.remove('saving')));
}

// ── Zell-Status (needs-hours / doppelt) ─────────────────────────────────────
function updateCellStatus(cell) {
  const sollstd = parseFloat(cell.dataset.sollstd) || 0;
  const sumStd  = getCellEntries(cell).reduce((s, e) => s + e.std, 0);
  cell.classList.toggle('needs-hours', sumStd < sollstd);
  cell.classList.toggle('doppelt',     sumStd > sollstd && sumStd > 0);
  const label = cell.querySelector('.soll-label');
  if (label) label.textContent = sumStd + '/' + sollstd + ' Std.';
}

// ── Nav-Statistiken live aktualisieren ──────────────────────────────────────
const NAV_SOLL    = <?= array_sum(array_map(fn($f) => array_sum(array_filter($f, fn($s, $k) => $k !== 'KL', ARRAY_FILTER_USE_BOTH)), $klassen)) ?>;
const NAV_SOLL_KL = <?= array_sum(array_map(fn($f) => $f['KL'] ?? 0, $klassen)) ?>;
const NAV_ZUSATZ  = <?= array_sum(array_column($data['zusatz'] ?? [], 'std')) ?>;
const NAV_SOLL_LK = <?= array_sum(array_column($data['lehrkraefte'], 'stunden')) ?>;
<?php
// Pro Lehrkraft: Soll-Stunden und Zusatz-Stunden
$_lkData = [];
foreach ($data['lehrkraefte'] as $k => $lk) {
    $z = 0;
    foreach ($data['zusatz'] ?? [] as $za) { if ($za['k'] === $k) $z += $za['std']; }
    $_lkData[$k] = ['soll' => $lk['stunden'], 'zusatz' => $z];
}
?>
const LK_DATA = <?= json_encode($_lkData) ?>;

function updateNavStats(stundenPerLk, verteiltU, verteiltKL) {
  const verteiltGes = verteiltU + verteiltKL + NAV_ZUSATZ;
  const pct = NAV_SOLL_LK > 0 ? Math.min(100, Math.round(verteiltGes / NAV_SOLL_LK * 100)) : 0;

  // Freie Lehrkraft-Kapazität
  let lkOffen = 0;
  for (const [k, ld] of Object.entries(LK_DATA)) {
    const eingetragen = (stundenPerLk[k] || 0) + ld.zusatz;
    if (ld.soll > eingetragen) lkOffen += ld.soll - eingetragen;
  }

  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('nav-verteilt-ges', verteiltGes);
  set('nav-verteilt-u',   verteiltU);
  set('nav-offen-val',    lkOffen);

  const elKL = document.getElementById('nav-verteilt-kl');
  if (elKL) elKL.textContent = verteiltKL + '/' + NAV_SOLL_KL;

  const lbl  = document.getElementById('nav-offen-lbl');
  const stat = document.getElementById('nav-offen-stat');
  const bar  = document.getElementById('nav-bar-fill');
  const wrap = document.getElementById('nav-bar-wrap');
  if (lbl)  lbl.textContent  = lkOffen > 0 ? 'LK noch offen' : '✓ fertig';
  if (stat) stat.style.color = lkOffen > 0 ? 'var(--red)' : '#065f46';
  if (bar)  bar.style.width  = pct + '%';
  if (wrap) wrap.title       = pct + '% der Lehrkraft-Kapazität vergeben';
}

// ── Chip-Leiste aktualisieren ───────────────────────────────────────────────
function updateChips() {
  const stunden = {};
  let verteiltU = 0, verteiltKL = 0;
  document.querySelectorAll('.cell[data-fach]').forEach(cell => {
    const isKL = cell.dataset.fach === 'KL';
    getCellEntries(cell).forEach(e => {
      stunden[e.k] = (stunden[e.k] || 0) + e.std;
      if (isKL) verteiltKL += e.std;
      else      verteiltU  += e.std;
    });
  });

  document.querySelectorAll('.lk-chip').forEach(chip => {
    const wasActive = chip.classList.contains('active-filter');
    const k    = chip.querySelector('.kuerzel').textContent;
    const info = chip.querySelector('.stunden-info');
    const gesamt = parseInt(info.textContent.split('/')[1]);
    const eingetragen = stunden[k] || 0;
    info.textContent = eingetragen + '/' + gesamt + ' Std.';
    chip.dataset.offen = gesamt - eingetragen;
    chip.className = 'lk-chip ' +
      (eingetragen > gesamt ? 'over' : eingetragen === gesamt ? 'ok' : 'under');
    if (wasActive) chip.classList.add('active-filter');
  });
  sortChips();

  // Nav-Statistiken mitaktualisieren
  updateNavStats(stunden, verteiltU, verteiltKL);
}

// ── Notizen ──────────────────────────────────────────────────────────────────
function notizAutoResize(ta) {
  ta.style.height = 'auto';
  ta.style.height = ta.scrollHeight + 'px';
}

document.querySelectorAll('.cell-notiz').forEach(ta => notizAutoResize(ta));

document.addEventListener('input', function(e) {
  if (e.target.matches('.cell-notiz')) notizAutoResize(e.target);
}, true);

document.addEventListener('focus', function(e) {
  const ta = e.target;
  if (!ta.matches('.cell-notiz')) return;
  ta.dataset.orig = ta.value;
}, true);

document.addEventListener('blur', function(e) {
  const ta = e.target;
  if (!ta.matches('.cell-notiz')) return;
  if (ta.value === ta.dataset.orig) return;
  const klasse = ta.dataset.klasse;
  const fach   = ta.dataset.fach;
  const notiz  = ta.value;
  ta.classList.toggle('has-notiz', notiz.trim().length > 0);
  fetch('einsatzplan.php', {
    method: 'POST',
    body: new URLSearchParams({ notiz_ajax: '1', klasse, fach, notiz })
  });
}, true);
</script>
</body>
</html>
