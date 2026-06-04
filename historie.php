<?php
require_once 'config.php';
requireLogin();

$data     = loadData();
$chrono   = $data['historie'] ?? [];          // ältestes zuerst (Reihenfolge wie gespeichert)
$historie = array_reverse($chrono);           // neuestes zuerst (für Anzeige)

function fmtZeit(int $ts): string {
    return date('d.m.Y H:i', $ts);
}

// Formatiert einen Zellzustand (Array von LK-Einträgen oder String für Notizen)
function fmtStand($val, string $fach, string $typ): string {
    if ($typ === 'notiz') {
        return $val !== '' ? '„' . htmlspecialchars($val) . '"'
                           : '<em style="color:#aaa">–</em>';
    }
    if (empty($val)) return '<em style="color:#aaa">–</em>';
    return implode(', ', array_map(function($e) use ($fach) {
        $k = '<strong>' . htmlspecialchars($e['k']) . '</strong>';
        return $fach === 'KL' ? $k : $k . ' (' . ($e['std'] ?? '?') . ' Std.)';
    }, $val));
}

// ── Zäsur-Liste aufbauen ────────────────────────────────────────────────────
// Jeder Eintrag: [label, idx in $chrono (-1 = vor erstem Eintrag)]
$zaesuren = [['label' => 'Anfang (vor allen Änderungen)', 'idx' => -1]];
foreach ($chrono as $i => $e) {
    if ($e['typ'] === 'zaesur') {
        $zaesuren[] = ['label' => $e['name'] . '  (' . fmtZeit($e['ts']) . ')', 'idx' => $i];
    }
}
$zaesuren[] = ['label' => 'Jetzt (aktuelle Version)', 'idx' => count($chrono)];

// ── Vergleichs-Modus ────────────────────────────────────────────────────────
$doCompare = isset($_GET['von']) && isset($_GET['bis']);
$compareResult = [];
$vonLabel = $bisLabel = '';

if ($doCompare) {
    $vonZ = $zaesuren[intval($_GET['von'])] ?? $zaesuren[0];
    $bisZ = $zaesuren[intval($_GET['bis'])] ?? $zaesuren[array_key_last($zaesuren)];
    $vonLabel = $vonZ['label'];
    $bisLabel = $bisZ['label'];

    // Einträge zwischen den beiden Zäsuren
    $start = $vonZ['idx'] + 1;
    $end   = $bisZ['idx'];      // exklusiv
    $slice = array_slice($chrono, $start, max(0, $end - $start));

    // Netto-Diff: pro Zelle erste 'alt' und letzte 'neu'
    $net = [];
    foreach ($slice as $e) {
        if (!in_array($e['typ'], ['aenderung', 'notiz'])) continue;
        $key = ($e['klasse'] ?? '') . "\0" . ($e['fach'] ?? '') . "\0" . $e['typ'];
        if (!isset($net[$key])) {
            $net[$key] = [
                'klasse' => $e['klasse'],
                'fach'   => $e['fach'],
                'typ'    => $e['typ'],
                'alt'    => $e['alt'],
            ];
        }
        $net[$key]['neu'] = $e['neu'];
    }
    // Nur tatsächliche Änderungen (alt ≠ neu)
    $compareResult = array_values(array_filter($net, fn($r) => $r['alt'] !== $r['neu']));
    usort($compareResult, fn($a, $b) => strcmp($a['klasse'] . $a['fach'], $b['klasse'] . $b['fach']));
}

// Für KL-Zellen std weglassen
function fmtEntry(array $e, string $fach): string {
    $k = '<strong>' . htmlspecialchars($e['k']) . '</strong>';
    return $fach === 'KL' ? $k : $k . ' (' . ($e['std'] ?? '?') . ' Std.)';
}

function fmtEinsaetze(array $entries, string $fach): string {
    if (empty($entries)) return '<em style="color:#aaa">–</em>';
    return implode(', ', array_map(fn($e) => fmtEntry($e, $fach), $entries));
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Historie – Einsatzplanung</title>
<?php include 'partials/head.php'; ?>
<style>
  .hist-list {
    display: flex;
    flex-direction: column;
    gap: 0;
  }

  /* Zäsur */
  .hist-zaesur {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 1.5rem 0 0.5rem;
    position: relative;
  }
  .hist-zaesur::before {
    content: '';
    flex: 1;
    height: 2px;
    background: var(--teal);
    border-radius: 1px;
  }
  .hist-zaesur::after {
    content: '';
    flex: 1;
    height: 2px;
    background: var(--teal);
    border-radius: 1px;
  }
  .hist-zaesur-label {
    background: var(--teal);
    color: white;
    padding: 0.3rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    white-space: nowrap;
  }
  .hist-zaesur-time {
    font-size: 0.75rem;
    color: var(--muted);
    white-space: nowrap;
  }

  /* Änderungszeile */
  .hist-row {
    display: flex;
    align-items: baseline;
    gap: 0.75rem;
    padding: 0.45rem 0;
    border-bottom: 1px solid var(--border);
    font-size: 0.875rem;
  }
  .hist-row:last-child { border-bottom: none; }

  .hist-time {
    color: var(--muted);
    font-size: 0.75rem;
    white-space: nowrap;
    min-width: 110px;
  }
  .hist-badge {
    font-size: 0.72rem;
    font-weight: 500;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    white-space: nowrap;
  }
  .badge-aenderung { background: #e8f4f2; color: var(--teal); }
  .badge-zusatz    { background: #fef3c7; color: #92400e; }

  .hist-desc { flex: 1; }
  .hist-arrow { color: var(--muted); margin: 0 0.25rem; }

  .hist-empty {
    text-align: center;
    color: var(--muted);
    padding: 3rem 0;
    font-style: italic;
  }

  /* Vergleichs-UI */
  .compare-form {
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
  }
  .compare-form .form-group { flex: 1; min-width: 200px; }

  .compare-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  .compare-table th {
    text-align: left;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    border-bottom: 1px solid var(--border);
  }
  .compare-table td {
    padding: 0.55rem 0.75rem;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
  }
  .compare-table tr:last-child td { border-bottom: none; }
  .compare-table .col-von { color: #991b1b; }
  .compare-table .col-bis { color: #065f46; }
  .compare-arrow { color: var(--muted); padding: 0 0.5rem; }
  .compare-empty {
    text-align: center;
    color: var(--muted);
    padding: 2rem 0;
    font-style: italic;
    font-size: 0.9rem;
  }
</style>
</head>
<body>
<?php include 'partials/nav.php'; ?>

<main class="container">
  <div style="display:flex;align-items:baseline;gap:1rem;margin-bottom:1.5rem">
    <h1>Änderungshistorie</h1>
    <span class="muted"><?= count($data['historie'] ?? []) ?> Einträge</span>
  </div>

  <!-- Zäsur-Vergleich -->
  <div class="card" style="margin-bottom:1.5rem">
    <h2 style="margin-bottom:1rem">Zwei Zäsuren vergleichen</h2>
    <?php if (count($zaesuren) < 3): ?>
      <p class="muted">Noch keine Zäsuren gesetzt. Setze Zäsuren im Einsatzplan, um Planungsphasen zu vergleichen.</p>
    <?php else: ?>
    <form method="get" class="compare-form">
      <div class="form-group">
        <label>Von</label>
        <select name="von" style="width:100%">
          <?php foreach ($zaesuren as $i => $z): ?>
          <option value="<?= $i ?>"<?= ($doCompare && intval($_GET['von']) === $i) ? ' selected' : '' ?>>
            <?= htmlspecialchars($z['label']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Bis</label>
        <select name="bis" style="width:100%">
          <?php foreach ($zaesuren as $i => $z): ?>
          <option value="<?= $i ?>"<?= ($doCompare && intval($_GET['bis']) === $i) ? ' selected' : (($i === count($zaesuren) - 1) ? ' selected' : '') ?>>
            <?= htmlspecialchars($z['label']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Vergleichen</button>
      <?php if ($doCompare): ?>
      <a href="historie.php" class="btn btn-secondary">Zurücksetzen</a>
      <?php endif; ?>
    </form>

    <?php if ($doCompare): ?>
      <hr style="border:none;border-top:1px solid var(--border);margin:1rem 0">
      <p style="font-size:0.85rem;color:var(--muted);margin-bottom:1rem">
        Änderungen zwischen <strong><?= htmlspecialchars($vonLabel) ?></strong>
        und <strong><?= htmlspecialchars($bisLabel) ?></strong>
        — <?= count($compareResult) ?> Zelle(n) geändert
      </p>
      <?php if (empty($compareResult)): ?>
        <div class="compare-empty">Keine Änderungen in diesem Zeitraum.</div>
      <?php else: ?>
      <table class="compare-table">
        <thead>
          <tr>
            <th style="width:90px">Klasse</th>
            <th style="width:130px">Fach</th>
            <th>Stand: <?= htmlspecialchars($vonLabel) ?></th>
            <th style="width:24px"></th>
            <th>Stand: <?= htmlspecialchars($bisLabel) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($compareResult as $r): ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['klasse']) ?></strong></td>
            <td><?= htmlspecialchars($r['fach']) ?></td>
            <td class="col-von"><?= fmtStand($r['alt'], $r['fach'], $r['typ']) ?></td>
            <td class="compare-arrow">→</td>
            <td class="col-bis"><?= fmtStand($r['neu'], $r['fach'], $r['typ']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (empty($historie)): ?>
    <div class="card hist-empty">
      Noch keine Änderungen aufgezeichnet.<br>
      Änderungen im Einsatzplan werden ab sofort automatisch gespeichert.
    </div>
  <?php else: ?>
  <div class="card">
    <div class="hist-list">
      <?php foreach ($historie as $entry):
        $ts   = $entry['ts'] ?? 0;
        $typ  = $entry['typ'] ?? '';
      ?>

        <?php if ($typ === 'zaesur'): ?>
          <div class="hist-zaesur">
            <span class="hist-zaesur-label">⚑ <?= htmlspecialchars($entry['name']) ?></span>
            <span class="hist-zaesur-time"><?= fmtZeit($ts) ?></span>
          </div>

        <?php elseif ($typ === 'aenderung'): ?>
          <div class="hist-row">
            <span class="hist-time"><?= fmtZeit($ts) ?></span>
            <span class="hist-badge badge-aenderung">Unterricht</span>
            <span class="hist-desc">
              <strong><?= htmlspecialchars($entry['klasse']) ?></strong>
              – <?= htmlspecialchars($entry['fach']) ?>:
              <?= fmtEinsaetze($entry['alt'] ?? [], $entry['fach']) ?>
              <span class="hist-arrow">→</span>
              <?= fmtEinsaetze($entry['neu'] ?? [], $entry['fach']) ?>
            </span>
          </div>

        <?php elseif ($typ === 'notiz'): ?>
          <div class="hist-row">
            <span class="hist-time"><?= fmtZeit($ts) ?></span>
            <span class="hist-badge" style="background:#fef9c3;color:#713f12">Notiz</span>
            <span class="hist-desc">
              <strong><?= htmlspecialchars($entry['klasse']) ?></strong>
              – <?= htmlspecialchars($entry['fach']) ?>:
              <?php if ($entry['alt'] !== ''): ?>
                „<?= htmlspecialchars($entry['alt']) ?>"
                <span class="hist-arrow">→</span>
              <?php endif; ?>
              <?php if ($entry['neu'] !== ''): ?>
                „<?= htmlspecialchars($entry['neu']) ?>"
              <?php else: ?>
                <em style="color:#aaa">gelöscht</em>
              <?php endif; ?>
            </span>
          </div>

        <?php elseif ($typ === 'zusatz_add'): ?>
          <div class="hist-row">
            <span class="hist-time"><?= fmtZeit($ts) ?></span>
            <span class="hist-badge badge-zusatz">Zusatz +</span>
            <span class="hist-desc">
              <?= htmlspecialchars($entry['bezeichnung']) ?>
              → <strong><?= htmlspecialchars($entry['k']) ?></strong>
              (<?= (int)$entry['std'] ?> Std.)
            </span>
          </div>

        <?php elseif ($typ === 'zusatz_del'): ?>
          <div class="hist-row">
            <span class="hist-time"><?= fmtZeit($ts) ?></span>
            <span class="hist-badge badge-zusatz" style="background:#fee2e2;color:#991b1b">Zusatz –</span>
            <span class="hist-desc">
              <?= htmlspecialchars($entry['bezeichnung']) ?>
              (<strong><?= htmlspecialchars($entry['k']) ?></strong>,
              <?= (int)$entry['std'] ?> Std.) entfernt
            </span>
          </div>

        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
