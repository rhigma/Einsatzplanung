<?php
require_once 'config.php';
requireLogin();

$data = loadData();

// Stunden pro Lehrkraft + Detailliste (Unterricht + Zusatzaufgaben)
function auswertungProLehrkraft($data, $klassen) {
    $result = [];
    foreach ($data['lehrkraefte'] as $k => $lk) {
        $result[$k] = [
            'name'       => $lk['name'],
            'stunden'    => $lk['stunden'],
            'eingetragen'=> 0,
            'einsaetze'  => [],
            'zusatz'     => [],
        ];
    }
    foreach ($data['einsaetze'] ?? [] as $klasse => $faecher) {
        foreach ($faecher as $fach => $value) {
            $sollstd = $klassen[$klasse][$fach] ?? 0;
            $entries = normCell($value, $sollstd);
            $geteilt = count($entries) > 1;
            foreach ($entries as $e) {
                $k = $e['k'];
                if (!isset($result[$k])) continue;
                $std = $e['std'] ?? 0;
                $result[$k]['eingetragen'] += $std;
                $result[$k]['einsaetze'][] = [
                    'klasse'  => $klasse,
                    'fach'    => $fach,
                    'stunden' => $std,
                    'geteilt' => $geteilt,
                ];
            }
        }
    }
    foreach ($data['zusatz'] ?? [] as $z) {
        $k = $z['k'];
        if (!isset($result[$k])) continue;
        $result[$k]['eingetragen'] += $z['std'];
        $result[$k]['zusatz'][] = $z;
    }
    return $result;
}

$auswertung = auswertungProLehrkraft($data, $klassen);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Lehrkräfte – Einsatzplanung 2026/27</title>
<?php include 'partials/head.php'; ?>
<style>
  .lk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
  }

  .lk-card {
    background: var(--card);
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 8px rgba(0,0,0,0.06);
    border-left: 4px solid var(--border);
  }

  .lk-card.ok   { border-left-color: #6ee7b7; }
  .lk-card.over { border-left-color: #fca5a5; }

  .lk-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 0.75rem;
  }

  .lk-name {
    font-weight: 500;
    font-size: 0.95rem;
  }

  .lk-kuerzel {
    font-size: 0.75rem;
    color: var(--muted);
  }

  .lk-progress {
    height: 6px;
    background: var(--bg);
    border-radius: 3px;
    margin-bottom: 0.75rem;
    overflow: hidden;
  }

  .lk-progress-bar {
    height: 100%;
    border-radius: 3px;
    background: var(--teal);
    transition: width 0.3s;
  }

  .lk-progress-bar.over { background: var(--red); }

  .lk-stunden {
    font-size: 0.8rem;
    color: var(--muted);
    margin-bottom: 0.75rem;
  }

  .lk-einsaetze {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  .lk-einsaetze li {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    padding: 0.25rem 0;
    border-bottom: 1px solid var(--border);
  }

  .lk-einsaetze li:last-child { border-bottom: none; }

  .lk-empty {
    font-size: 0.8rem;
    color: var(--muted);
    font-style: italic;
  }
</style>
</head>
<body>
<?php include 'partials/nav.php'; ?>

<main class="container">
  <div style="display:flex; align-items:baseline; gap:1rem; margin-bottom:1rem;">
    <h1>Lehrkräfte</h1>
    <button onclick="window.print()" class="btn btn-secondary btn-sm no-print">🖨 Drucken</button>
  </div>

  <?php if (empty($auswertung)): ?>
    <p class="muted">Noch keine Lehrkräfte angelegt. Gehe zu <a href="verwaltung.php">Verwaltung</a>.</p>
  <?php else: ?>
  <div class="lk-grid">
    <?php foreach ($auswertung as $k => $lk):
      $pct = $lk['stunden'] > 0 ? min(100, round($lk['eingetragen'] / $lk['stunden'] * 100)) : 0;
      $offen = $lk['stunden'] - $lk['eingetragen'];
      $status = $lk['eingetragen'] > $lk['stunden'] + 0.01 ? 'over' : (abs($offen) < 0.01 ? 'ok' : '');
    ?>
    <div class="lk-card <?= $status ?>">
      <div class="lk-header">
        <span class="lk-name"><?= htmlspecialchars($lk['name']) ?></span>
        <span class="lk-kuerzel"><?= htmlspecialchars($k) ?></span>
      </div>

      <div class="lk-progress">
        <div class="lk-progress-bar <?= $status ?>" style="width:<?= $pct ?>%"></div>
      </div>

      <div class="lk-stunden">
        <?= round($lk['eingetragen'], 1) ?> / <?= $lk['stunden'] ?> Stunden eingetragen
        <span class="tip" data-tip="Eingetragene Stunden (Unterricht + Zusatzaufgaben) im Verhältnis zu den Soll-Wochenstunden. Grüner Rand = Soll erfüllt, roter Rand = überzogen.">?</span>
        <?php if ($offen > 0.01): ?>
          · <strong><?= round($offen, 1) ?> offen</strong>
        <?php elseif ($offen < -0.01): ?>
          · <strong style="color:var(--red)"><?= round(abs($offen), 1) ?> zu viel</strong>
        <?php else: ?>
          · <strong style="color:#065f46">✓ vollständig</strong>
        <?php endif; ?>
      </div>

      <?php if (empty($lk['einsaetze']) && empty($lk['zusatz'])): ?>
        <p class="lk-empty">Noch keine Einsätze eingetragen.</p>
      <?php else: ?>
        <ul class="lk-einsaetze">
          <?php foreach ($lk['einsaetze'] as $e): ?>
          <li>
            <span>
              <?= htmlspecialchars($e['klasse']) ?> – <?= htmlspecialchars($e['fach']) ?>
              <?php if ($e['geteilt']): ?>
                <span style="color:var(--muted); font-size:0.7rem">(geteilt)</span>
              <?php endif; ?>
            </span>
            <span><?= ($e['stunden'] == floor($e['stunden'])) ? (int)$e['stunden'] : round($e['stunden'], 1) ?> Std.</span>
          </li>
          <?php endforeach; ?>
          <?php foreach ($lk['zusatz'] as $z): ?>
          <li style="font-style:italic">
            <span><?= htmlspecialchars($z['bezeichnung']) ?></span>
            <span><?= $z['std'] ?> Std.</span>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
