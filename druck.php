<?php
require_once 'config.php';
requireLogin();

$data    = loadData();
$modus   = $_GET['modus'] ?? 'tabelle'; // 'tabelle' oder 'lk'

// Fächerliste
$alleFaecher = [];
foreach ($klassen as $faecher) {
    foreach (array_keys($faecher) as $f) $alleFaecher[$f] = true;
}
$alleFaecher = array_keys($alleFaecher);
$fachOrder = ['KL','Deutsch','Mathematik','Sachunterricht','Englisch',
              'Naturwissenschaften','Gesellschaftswissenschaften','Kunst','Musik','Sport'];
usort($alleFaecher, function($a, $b) use ($fachOrder) {
    $ia = array_search($a, $fachOrder); $ib = array_search($b, $fachOrder);
    return ($ia === false ? 99 : $ia) - ($ib === false ? 99 : $ib);
});

// Auswertung pro Lehrkraft (für LK-Modus)
function lkAuswertung($data, $klassen) {
    $result = [];
    foreach ($data['lehrkraefte'] as $k => $lk) {
        $result[$k] = ['name' => $lk['name'], 'soll' => $lk['stunden'],
                       'einsaetze' => [], 'zusatz' => [], 'eingetragen' => 0];
    }
    foreach ($data['einsaetze'] ?? [] as $klasse => $faecher) {
        foreach ($faecher as $fach => $value) {
            $sollstd = $klassen[$klasse][$fach] ?? 0;
            $entries = normCell($value, $sollstd);
            foreach ($entries as $e) {
                if (!isset($result[$e['k']])) continue;
                $result[$e['k']]['einsaetze'][] =
                    ['klasse' => $klasse, 'fach' => $fach, 'std' => $e['std']];
                $result[$e['k']]['eingetragen'] += $e['std'];
            }
        }
    }
    foreach ($data['zusatz'] ?? [] as $z) {
        if (!isset($result[$z['k']])) continue;
        $result[$z['k']]['zusatz'][]    = $z;
        $result[$z['k']]['eingetragen'] += $z['std'];
    }
    return $result;
}
$lkData = ($modus === 'lk') ? lkAuswertung($data, $klassen) : [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Einsatzplan – Druckansicht</title>
<style>
<?php if ($modus === 'lk'): ?>
  @page { size: A4 portrait; margin: 1.5cm; }
<?php else: ?>
  @page { size: A4 landscape; margin: 1cm; }
<?php endif; ?>

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    color: #000;
    background: #fff;
  }

  /* ── Toolbar (nur Bildschirm) ── */
  .toolbar {
    background: #f5f2ee;
    border-bottom: 1px solid #ddd;
    padding: 0.75rem 1.5rem;
    display: flex;
    gap: 0.75rem;
    align-items: center;
  }
  .toolbar button {
    padding: 0.4rem 1rem;
    background: #2a7c6f;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
  }
  .toolbar a {
    font-size: 0.875rem;
    color: #6b6b6b;
    text-decoration: none;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
  }
  .toolbar a.active {
    background: #e8f4f2;
    color: #2a7c6f;
    font-weight: 600;
  }
  @media print { .toolbar { display: none; } }

  /* ── Gemeinsamer Seitenheader ── */
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    border-bottom: 2pt solid #000;
    padding-bottom: 5pt;
    margin-bottom: 10pt;
  }
  .page-header h1 { font-size: 13pt; font-weight: bold; }
  .page-header .meta { font-size: 8pt; color: #555; }

  /* ════════════════════════════════
     MODUS: UNTERRICHTSTABELLE
     ════════════════════════════════ */
  table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }
  th, td {
    border: 1.5pt solid #333;
    vertical-align: top;
    padding: 0;
  }
  .col-klasse {
    width: 56pt;
    background: #eee;
    font-weight: bold;
    font-size: 9pt;
    text-align: center;
    vertical-align: middle;
    padding: 4pt;
  }
  .col-kl { width: 36pt; }
  thead th {
    background: #ddd;
    text-align: center;
    padding: 4pt 2pt;
    font-size: 8pt;
    font-weight: bold;
  }
  .plan-cell {
    height: 64pt;
    padding: 3pt 4pt 0 4pt;
    position: relative;
  }
  .plan-cell.empty-cell { background: #f0f0f0; }
  .soll-badge {
    position: absolute;
    top: 3pt; right: 4pt;
    font-size: 7pt;
    color: #999;
    font-style: italic;
  }
  .assigned {
    font-size: 8pt;
    font-weight: bold;
    color: #222;
    margin-bottom: 3pt;
    line-height: 1.3;
  }
  .write-lines {
    position: absolute;
    left: 4pt; right: 4pt; bottom: 4pt;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
  }
  .write-line {
    border-bottom: 0.5pt solid #bbb;
    height: 14pt;
  }

  /* ════════════════════════════════
     MODUS: PRO LEHRKRAFT
     ════════════════════════════════ */
  .lk-page {
    page-break-after: always;
    padding-top: 4pt;
  }
  .lk-page:last-child { page-break-after: avoid; }

  .lk-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-bottom: 1.5pt solid #000;
    padding-bottom: 6pt;
    margin-bottom: 10pt;
  }
  .lk-name {
    font-size: 18pt;
    font-weight: bold;
    line-height: 1;
  }
  .lk-kuerzel {
    font-size: 11pt;
    color: #555;
    margin-left: 6pt;
  }
  .lk-stunden {
    font-size: 9pt;
    text-align: right;
    line-height: 1.5;
  }
  .lk-stunden strong { font-size: 11pt; }
  .lk-stunden .offen { color: #c00; }
  .lk-stunden .voll  { color: #060; }

  /* Fortschrittsbalken */
  .lk-bar-wrap {
    height: 6pt;
    background: #ddd;
    border-radius: 3pt;
    overflow: hidden;
    margin: 6pt 0 12pt;
  }
  .lk-bar {
    height: 100%;
    background: #2a7c6f;
    border-radius: 3pt;
  }
  .lk-bar.over { background: #c0392b; }

  /* Einsatz-Tabelle */
  .lk-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9pt;
    margin-bottom: 12pt;
  }
  .lk-table th {
    background: #eee;
    text-align: left;
    padding: 4pt 6pt;
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1pt solid #aaa;
    font-weight: bold;
  }
  .lk-table td {
    padding: 5pt 6pt;
    border-bottom: 0.5pt solid #ddd;
    vertical-align: middle;
  }
  .lk-table tr:last-child td { border-bottom: none; }
  .lk-table .std-col {
    text-align: right;
    width: 48pt;
    font-weight: bold;
  }
  .lk-table .kl-badge {
    display: inline-block;
    font-size: 8pt;
    color: #555;
    margin-left: 4pt;
  }
  .lk-section-head {
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #888;
    margin: 10pt 0 4pt;
    font-weight: bold;
  }
  .lk-total-row td {
    border-top: 1.5pt solid #333 !important;
    font-weight: bold;
    padding-top: 5pt;
  }
</style>
</head>
<body>

<div class="toolbar">
  <button onclick="window.print()">🖨 Drucken</button>
  <a href="druck.php" class="<?= $modus === 'tabelle' ? 'active' : '' ?>">Unterrichtstabelle</a>
  <a href="druck.php?modus=lk" class="<?= $modus === 'lk' ? 'active' : '' ?>">Pro Lehrkraft</a>
  <a href="einsatzplan.php" style="margin-left:auto">← Zurück</a>
</div>

<?php if ($modus === 'tabelle'): ?>

<!-- ── UNTERRICHTSTABELLE ── -->
<div class="page-header">
  <h1>Unterrichtseinsatzplan <?= htmlspecialchars($schuljahr) ?> &ndash; <?= htmlspecialchars($schulname) ?></h1>
  <span class="meta">Bitte Kürzel + Stundenanzahl eintragen</span>
</div>

<table>
  <thead>
    <tr>
      <th class="col-klasse">Klasse</th>
      <?php foreach ($alleFaecher as $fach): ?>
      <th<?= $fach === 'KL' ? ' class="col-kl"' : '' ?>><?= htmlspecialchars($fach) ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($klassen as $klassenname => $faecher): ?>
    <tr>
      <td class="col-klasse"><?= htmlspecialchars($klassenname) ?></td>
      <?php foreach ($alleFaecher as $fach): ?>
        <?php if (!isset($faecher[$fach])): ?>
          <td class="plan-cell empty-cell"></td>
        <?php else: ?>
          <?php
            $sollstd = $faecher[$fach];
            $cellVal = normCell($data['einsaetze'][$klassenname][$fach] ?? null, $sollstd);
            $isKL    = ($fach === 'KL');
          ?>
          <td class="plan-cell<?= $isKL ? ' kl-cell' : '' ?>">
            <?php if (!$isKL): ?><div class="soll-badge"><?= $sollstd ?> Std.</div><?php endif; ?>
            <?php if (!empty($cellVal)): ?>
            <div class="assigned">
              <?php foreach ($cellVal as $e): ?>
              <?= htmlspecialchars($e['k']) ?><?= (!$isKL && $e['std']) ? ' · '.$e['std'].'h' : '' ?><br>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php $notiz = $data['notizen'][$klassenname][$fach] ?? ''; ?>
            <?php if ($notiz): ?>
            <div style="font-size:6.5pt;color:#888;font-style:italic;margin-bottom:2pt">
              <?= htmlspecialchars($notiz) ?>
            </div>
            <?php endif; ?>
            <?php if (!$isKL): ?>
            <div class="write-lines">
              <?php $lines = max(2, min(4, 4 - count($cellVal)));
                    for ($i = 0; $i < $lines; $i++): ?>
              <div class="write-line"></div>
              <?php endfor; ?>
            </div>
            <?php endif; ?>
          </td>
        <?php endif; ?>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php else: ?>

<!-- ── PRO LEHRKRAFT ── -->
<?php foreach ($lkData as $k => $lk):
    $pct   = $lk['soll'] > 0 ? min(100, round($lk['eingetragen'] / $lk['soll'] * 100)) : 0;
    $offen = $lk['soll'] - $lk['eingetragen'];
    $over  = $lk['eingetragen'] > $lk['soll'];
?>
<div class="lk-page">

  <div class="page-header">
    <span style="font-size:9pt;color:#555"><?= htmlspecialchars($schulname) ?> &ndash; Einsatzplan <?= htmlspecialchars($schuljahr) ?></span>
    <span style="font-size:9pt;color:#555">Ausdruck</span>
  </div>

  <div class="lk-header">
    <div>
      <span class="lk-name"><?= htmlspecialchars($lk['name']) ?></span>
      <span class="lk-kuerzel">(<?= htmlspecialchars($k) ?>)</span>
    </div>
    <div class="lk-stunden">
      <strong><?= $lk['eingetragen'] ?> / <?= $lk['soll'] ?> Std.</strong><br>
      <?php if ($over): ?>
        <span class="offen"><?= $lk['eingetragen'] - $lk['soll'] ?> Std. über Soll</span>
      <?php elseif ($offen > 0): ?>
        <span class="offen"><?= $offen ?> Std. noch offen</span>
      <?php else: ?>
        <span class="voll">✓ vollständig verplant</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="lk-bar-wrap">
    <div class="lk-bar<?= $over ? ' over' : '' ?>" style="width:<?= $pct ?>%"></div>
  </div>

  <?php if (!empty($lk['einsaetze'])): ?>
  <div class="lk-section-head">Unterricht</div>
  <table class="lk-table">
    <thead>
      <tr>
        <th>Klasse</th>
        <th>Fach</th>
        <th class="std-col">Std.</th>
      </tr>
    </thead>
    <tbody>
      <?php
        // Nach Klasse sortieren
        usort($lk['einsaetze'], fn($a,$b) => strcmp($a['klasse'], $b['klasse']));
        $gesamtUnterricht = array_sum(array_column($lk['einsaetze'], 'std'));
        foreach ($lk['einsaetze'] as $e):
          $isKL = ($e['fach'] === 'KL');
      ?>
      <tr>
        <td><?= htmlspecialchars($e['klasse']) ?></td>
        <td><?= htmlspecialchars($e['fach']) ?></td>
        <td class="std-col"><?= $e['std'] ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="lk-total-row">
        <td colspan="2">Unterricht gesamt</td>
        <td class="std-col"><?= $gesamtUnterricht ?></td>
      </tr>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if (!empty($lk['zusatz'])): ?>
  <div class="lk-section-head">Zusatzaufgaben</div>
  <table class="lk-table">
    <thead>
      <tr><th>Aufgabe</th><th class="std-col">Std.</th></tr>
    </thead>
    <tbody>
      <?php foreach ($lk['zusatz'] as $z): ?>
      <tr>
        <td><?= htmlspecialchars($z['bezeichnung']) ?></td>
        <td class="std-col"><?= $z['std'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if (empty($lk['einsaetze']) && empty($lk['zusatz'])): ?>
  <p style="color:#999; font-style:italic; margin-top:1cm">Noch keine Einsätze eingetragen.</p>
  <?php endif; ?>

</div>
<?php endforeach; ?>

<?php endif; ?>
</body>
</html>
