<?php
require_once 'config.php';
requireLogin();

$data = loadData();

// Aktionen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_lehrkraft') {
        $kuerzel = ucfirst(strtolower(trim($_POST['kuerzel'] ?? '')));
        $name    = trim($_POST['name'] ?? '');
        $stunden = intval($_POST['stunden'] ?? 0);
        if ($kuerzel && $name && $stunden > 0 && !isset($data['lehrkraefte'][$kuerzel])) {
            $data['lehrkraefte'][$kuerzel] = ['name' => $name, 'stunden' => $stunden];
            saveData($data);
        }
    }

    if ($action === 'edit_lehrkraft') {
        $oldK    = $_POST['old_kuerzel'] ?? '';
        $newK    = ucfirst(strtolower(trim($_POST['kuerzel'] ?? '')));
        $name    = trim($_POST['name'] ?? '');
        $stunden = intval($_POST['stunden'] ?? 0);
        if ($oldK && $newK && $name && $stunden > 0 && isset($data['lehrkraefte'][$oldK])) {
            unset($data['lehrkraefte'][$oldK]);
            $data['lehrkraefte'][$newK] = ['name' => $name, 'stunden' => $stunden];
            if ($oldK !== $newK) {
                // Kürzel in allen Unterrichtseinsätzen umbenennen
                foreach ($data['einsaetze'] ?? [] as $klasse => $faecher) {
                    foreach ($faecher as $fach => $value) {
                        $entries = normCell($value);
                        $changed = false;
                        foreach ($entries as &$entry) {
                            if ($entry['k'] === $oldK) { $entry['k'] = $newK; $changed = true; }
                        }
                        if ($changed) $data['einsaetze'][$klasse][$fach] = $entries;
                    }
                }
                // Kürzel in Zusatzaufgaben umbenennen
                foreach ($data['zusatz'] ?? [] as &$z) {
                    if ($z['k'] === $oldK) $z['k'] = $newK;
                }
            }
            saveData($data);
        }
    }

    if ($action === 'delete_lehrkraft') {
        $kuerzel = $_POST['kuerzel'] ?? '';
        unset($data['lehrkraefte'][$kuerzel]);
        foreach ($data['einsaetze'] ?? [] as $klasse => $faecher) {
            foreach ($faecher as $fach => $value) {
                $entries = array_values(array_filter(
                    normCell($value),
                    fn($e) => $e['k'] !== $kuerzel
                ));
                $data['einsaetze'][$klasse][$fach] = $entries;
            }
        }
        $data['zusatz'] = array_values(array_filter(
            $data['zusatz'] ?? [],
            fn($z) => $z['k'] !== $kuerzel
        ));
        saveData($data);
    }

    if ($action === 'import_lehrkraefte') {
        $rows    = json_decode($_POST['rows']    ?? '[]', true);
        $assigns = json_decode($_POST['assigns'] ?? '{}', true);
        $cN = $assigns['name']    ?? null;
        $cK = $assigns['kuerzel'] ?? null;
        $cS = $assigns['stunden'] ?? null;
        $imported = 0;
        foreach ((array)$rows as $row) {
            $name    = trim($row[$cN] ?? '');
            $k       = ucfirst(strtolower(trim($row[$cK] ?? '')));
            $stunden = intval($row[$cS] ?? 0);
            if ($name && $k && $stunden > 0) {
                $data['lehrkraefte'][$k] = ['name' => $name, 'stunden' => $stunden];
                $imported++;
            }
        }
        if ($imported > 0) saveData($data);
        header("Location: verwaltung.php?imported=$imported");
        exit;
    }

    if ($action === 'save_stundentafel') {
        $name    = trim($_POST['tafel_name'] ?? '');
        $oldName = trim($_POST['old_tafel_name'] ?? '');
        $faecher = [];
        foreach ($_POST['fach'] ?? [] as $f => $v) {
            $v = intval($v); if ($v > 0) $faecher[$f] = $v;
        }
        $newFach = trim($_POST['new_fach_name'] ?? '');
        $newStd  = intval($_POST['new_fach_std'] ?? 0);
        if ($newFach && $newStd > 0) $faecher[$newFach] = $newStd;

        if ($name) {
            if ($oldName && $oldName !== $name) unset($data['stundentafeln'][$oldName]);
            $data['stundentafeln'][$name] = $faecher;
            saveData($data);
        }
    }

    if ($action === 'delete_stundentafel') {
        $name = $_POST['tafel_name'] ?? '';
        unset($data['stundentafeln'][$name]);
        saveData($data);
    }

    if ($action === 'add_klasse') {
        $name  = trim($_POST['name'] ?? '');
        $farbe = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['farbe'] ?? '') ? $_POST['farbe'] : '#888888';
        $faecher = [];
        foreach ($_POST['fach'] ?? [] as $f => $v) {
            $v = intval($v);
            if ($v > 0) $faecher[$f] = $v;
        }
        if ($name && $faecher && !isset($data['klassen'][$name])) {
            $data['klassen'][$name] = ['farbe' => $farbe, 'faecher' => $faecher];
            saveData($data);
        }
    }

    if ($action === 'edit_klasse') {
        $oldName = $_POST['old_name'] ?? '';
        $newName = trim($_POST['name'] ?? '');
        $farbe   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['farbe'] ?? '') ? $_POST['farbe'] : '#888888';
        $faecher = [];
        foreach ($_POST['fach'] ?? [] as $f => $v) {
            $v = intval($v);
            if ($v > 0) $faecher[$f] = $v;
        }
        if ($oldName && $newName && $faecher && isset($data['klassen'][$oldName])) {
            unset($data['klassen'][$oldName]);
            $data['klassen'][$newName] = ['farbe' => $farbe, 'faecher' => $faecher];
            if ($oldName !== $newName && isset($data['einsaetze'][$oldName])) {
                $data['einsaetze'][$newName] = $data['einsaetze'][$oldName];
                unset($data['einsaetze'][$oldName]);
            }
            saveData($data);
        }
    }

    if ($action === 'delete_klasse') {
        $name = $_POST['name'] ?? '';
        unset($data['klassen'][$name], $data['einsaetze'][$name]);
        saveData($data);
    }

    if ($action === 'save_einstellungen') {
        $data['einstellungen']['schulname'] = trim($_POST['schulname'] ?? '') ?: 'Meine Schule';
        $data['einstellungen']['schuljahr'] = trim($_POST['schuljahr'] ?? '') ?: '2026/27';
        saveData($data);
    }

    if ($action === 'change_password') {
        $old     = $_POST['old_password']     ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!checkPassword($old, $data)) {
            header('Location: verwaltung.php?pw_err=wrong');
            exit;
        }
        if (strlen($new) < 6) {
            header('Location: verwaltung.php?pw_err=short');
            exit;
        }
        if ($new !== $confirm) {
            header('Location: verwaltung.php?pw_err=mismatch');
            exit;
        }
        $data['einstellungen']['passwort_hash'] = password_hash($new, PASSWORD_DEFAULT);
        saveData($data);
        header('Location: verwaltung.php?pw_ok=1');
        exit;
    }

    if ($action === 'export') {
        $name = preg_replace('/[^a-z0-9_-]/i', '_', $schulname);
        $filename = 'einsatzplanung_' . $name . '_' . date('Y-m-d') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'import') {
        if (isset($_FILES['backup']) && $_FILES['backup']['error'] === UPLOAD_ERR_OK) {
            $json     = file_get_contents($_FILES['backup']['tmp_name']);
            $imported = json_decode($json, true);
            if (is_array($imported) && (isset($imported['lehrkraefte']) || isset($imported['klassen']))) {
                file_put_contents(DATA_FILE, $json);
                header('Location: verwaltung.php?backup=ok');
            } else {
                header('Location: verwaltung.php?backup=invalid');
            }
        } else {
            header('Location: verwaltung.php?backup=error');
        }
        exit;
    }

    if ($action === 'reset_all') {
        $data['einsaetze'] = [];
        saveData($data);
    }

    header('Location: verwaltung.php');
    exit;
}

$data = loadData();
$_usedColors = array_values(array_unique(array_column($data['klassen'], 'farbe')));

function colorSwatches(array $colors): string {
    if (empty($colors)) return '';
    $html = '<div class="color-swatches">';
    foreach ($colors as $c) {
        $safe = htmlspecialchars($c, ENT_QUOTES);
        $html .= "<button type=\"button\" class=\"color-swatch-opt\" "
               . "style=\"background:{$safe}\" data-color=\"{$safe}\" "
               . "onclick=\"applySwatchColor(this)\" title=\"{$safe}\"></button>";
    }
    return $html . '</div>';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Verwaltung – Einsatzplanung</title>
<?php include 'partials/head.php'; ?>
<style>
  .color-swatches { display:flex; gap:4px; flex-wrap:wrap; align-items:center; margin-top:4px; }
  .color-swatch-opt {
    width:22px; height:22px; border-radius:4px; border:2px solid transparent;
    cursor:pointer; padding:0; transition: border-color 0.15s, transform 0.1s;
  }
  .color-swatch-opt:hover { border-color:#333; transform:scale(1.15); }
  .edit-row         { display: none; background: var(--teal-lt); }
  .edit-row.open    { display: table-row; }
  .edit-row td      { padding: 0.75rem; }
  .edit-row .form-row { margin: 0; }
  .lk-row td        { vertical-align: middle; }
  .klasse-edit-row  { display: none; background: var(--teal-lt); }
  .klasse-edit-row.open { display: table-row; }
  .tafel-edit-row   { display: none; background: var(--teal-lt); }
  .tafel-edit-row.open  { display: table-row; }
</style>
</head>
<body>
<?php include 'partials/nav.php'; ?>

<main class="container">
  <h1>Verwaltung</h1>

  <!-- Einstellungen -->
  <section class="card">
    <h2>Einstellungen</h2>

    <?php
      $pwErr = $_GET['pw_err'] ?? '';
      $pwOk  = isset($_GET['pw_ok']);
      $pwErrMsg = match($pwErr) {
          'wrong'    => 'Das aktuelle Passwort ist falsch.',
          'short'    => 'Das neue Passwort muss mindestens 6 Zeichen haben.',
          'mismatch' => 'Die neuen Passwörter stimmen nicht überein.',
          default    => '',
      };
    ?>

    <form method="post" class="form-row" style="margin-bottom:1.5rem">
      <input type="hidden" name="action" value="save_einstellungen">
      <div class="form-group" style="flex:3">
        <label>Schulname</label>
        <input type="text" name="schulname" value="<?= htmlspecialchars($schulname) ?>" required>
      </div>
      <div class="form-group" style="flex:1">
        <label>Schuljahr</label>
        <input type="text" name="schuljahr" value="<?= htmlspecialchars($schuljahr) ?>" placeholder="2026/27" required>
      </div>
      <button type="submit" class="btn btn-primary">Speichern</button>
    </form>

    <hr style="border:none;border-top:1px solid var(--border);margin-bottom:1.25rem">
    <h2>Passwort ändern</h2>

    <?php if ($pwOk): ?>
      <p style="color:#065f46;margin-bottom:1rem">✓ Passwort erfolgreich geändert.</p>
    <?php endif; ?>
    <?php if ($pwErrMsg): ?>
      <p style="color:var(--red);margin-bottom:1rem"><?= htmlspecialchars($pwErrMsg) ?></p>
    <?php endif; ?>

    <form method="post" class="form-row">
      <input type="hidden" name="action" value="change_password">
      <div class="form-group">
        <label>Aktuelles Passwort</label>
        <input type="password" name="old_password" required>
      </div>
      <div class="form-group">
        <label>Neues Passwort</label>
        <input type="password" name="new_password" required>
      </div>
      <div class="form-group">
        <label>Bestätigen</label>
        <input type="password" name="confirm_password" required>
      </div>
      <button type="submit" class="btn btn-primary">Ändern</button>
    </form>
  </section>

  <!-- Stundentafeln-Verwaltung -->
  <section class="card">
    <h2>Stundentafeln-Vorlagen <span class="tip" data-tip="Vorlagen definieren das Stundengitter für eine Klassenstufe (z.B. Klasse 1/2: Deutsch 8h, Mathe 5h …). Sie werden beim Anlegen einer neuen Klasse als Ausgangspunkt angeboten und können hier frei bearbeitet werden.">?</span></h2>
    <p class="muted" style="margin-bottom:1rem;font-size:0.85rem">
      Vorlagen werden beim Anlegen neuer Klassen als Ausgangspunkt angeboten.
    </p>

    <?php if (!empty($data['stundentafeln'])): ?>
    <table class="table" id="tafeln-table" style="margin-bottom:1.5rem">
      <thead>
        <tr><th>Name</th><th>Fächer &amp; Stunden</th><th></th></tr>
      </thead>
      <tbody>
        <?php $ti = 0; foreach ($data['stundentafeln'] as $tname => $tfaecher): $ti++; ?>
        <tr>
          <td><strong><?= htmlspecialchars($tname) ?></strong></td>
          <td style="font-size:0.8rem;color:var(--muted)">
            <?php foreach ($tfaecher as $f => $s): ?>
            <span style="margin-right:0.5rem"><?= htmlspecialchars($f) ?>:&nbsp;<?= $s ?></span>
            <?php endforeach; ?>
          </td>
          <td style="white-space:nowrap">
            <button type="button" class="btn btn-secondary btn-sm"
              onclick="toggleTafelEdit(<?= $ti ?>)">Bearbeiten</button>
            <form method="post" style="display:inline"
              onsubmit="return confirm('Vorlage löschen?')">
              <input type="hidden" name="action" value="delete_stundentafel">
              <input type="hidden" name="tafel_name" value="<?= htmlspecialchars($tname) ?>">
              <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
            </form>
          </td>
        </tr>
        <tr class="tafel-edit-row" id="tedit-<?= $ti ?>">
          <td colspan="3" style="padding:0.75rem">
            <?php
              $allTafelFaecher = array_keys(array_merge(...array_values($data['stundentafeln'])));
              foreach ($tfaecher as $f => $_) {
                  if (!in_array($f, $allTafelFaecher)) $allTafelFaecher[] = $f;
              }
            ?>
            <form method="post">
              <input type="hidden" name="action" value="save_stundentafel">
              <input type="hidden" name="old_tafel_name" value="<?= htmlspecialchars($tname) ?>">
              <div style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:0.75rem">
                <div class="form-group">
                  <label>Name der Vorlage</label>
                  <input type="text" name="tafel_name" value="<?= htmlspecialchars($tname) ?>" required style="width:120px">
                </div>
              </div>
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.75rem">
                <?php foreach ($allTafelFaecher as $f): ?>
                <div class="form-group" style="min-width:60px">
                  <label><?= htmlspecialchars($f) ?></label>
                  <input type="number" name="fach[<?= htmlspecialchars($f) ?>]"
                    min="0" max="30" style="width:60px"
                    value="<?= $tfaecher[$f] ?? 0 ?>">
                </div>
                <?php endforeach; ?>
              </div>
              <div style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:0.75rem;padding:0.75rem;background:var(--bg);border-radius:7px">
                <span style="font-size:0.75rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;align-self:center">Neues Fach</span>
                <div class="form-group">
                  <label>Bezeichnung</label>
                  <input type="text" name="new_fach_name" placeholder="z.B. Ethik">
                </div>
                <div class="form-group">
                  <label>Std.</label>
                  <input type="number" name="new_fach_std" min="1" max="30" style="width:60px">
                </div>
              </div>
              <button type="submit" class="btn btn-primary">Speichern</button>
              <button type="button" class="btn btn-secondary"
                onclick="toggleTafelEdit(<?= $ti ?>)">Abbrechen</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

  </section>

  <!-- Neue Vorlage -->
  <section class="card">
    <h2>Neue Vorlage anlegen</h2>
    <form method="post">
      <input type="hidden" name="action" value="save_stundentafel">
      <input type="hidden" name="old_tafel_name" value="">
      <div style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem">
        <div class="form-group">
          <label>Name der Vorlage</label>
          <input type="text" name="tafel_name" placeholder="z.B. 5/6" required style="width:120px">
        </div>
      </div>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem">
        <?php $allTafelFaecher = array_keys(array_merge(...array_values($data['stundentafeln']))); ?>
        <?php foreach ($allTafelFaecher as $f): ?>
        <div class="form-group" style="min-width:60px">
          <label><?= htmlspecialchars($f) ?></label>
          <input type="number" name="fach[<?= htmlspecialchars($f) ?>]"
            data-fach="<?= htmlspecialchars($f) ?>"
            min="0" max="30" style="width:60px" value="0">
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem;padding:0.75rem;background:var(--bg);border-radius:7px">
        <span style="font-size:0.75rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;align-self:center">Neues Fach</span>
        <div class="form-group">
          <label>Bezeichnung</label>
          <input type="text" name="new_fach_name" placeholder="z.B. Ethik">
        </div>
        <div class="form-group">
          <label>Std./Woche</label>
          <input type="number" name="new_fach_std" min="1" max="30" style="width:60px">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Vorlage anlegen</button>
    </form>
  </section>

  <!-- Klassenverwaltung -->
  <section class="card">
    <h2>Klassen</h2>

    <?php if (!empty($data['klassen'])): ?>
    <table class="table" id="klassen-table" style="margin-bottom:1.5rem">
      <thead>
        <tr><th>Farbe</th><th>Name</th><th>Fächer &amp; Stunden</th><th></th></tr>
      </thead>
      <tbody>
        <?php $ki = 0; foreach ($data['klassen'] as $kname => $kinfo): $ki++; ?>
        <tr class="klasse-row">
          <td><span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= htmlspecialchars($kinfo['farbe']) ?>"></span></td>
          <td><strong><?= htmlspecialchars($kname) ?></strong></td>
          <td style="font-size:0.8rem;color:var(--muted)">
            <?php foreach ($kinfo['faecher'] as $f => $s): ?>
            <span style="margin-right:0.5rem"><?= htmlspecialchars($f) ?>:&nbsp;<?= $s ?></span>
            <?php endforeach; ?>
          </td>
          <td style="white-space:nowrap">
            <button type="button" class="btn btn-secondary btn-sm"
              onclick="toggleKlasseEdit(<?= $ki ?>)">Bearbeiten</button>
            <form method="post" style="display:inline"
              onsubmit="return confirm('Klasse und alle ihre Einsätze löschen?')">
              <input type="hidden" name="action" value="delete_klasse">
              <input type="hidden" name="name" value="<?= htmlspecialchars($kname) ?>">
              <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
            </form>
          </td>
        </tr>
        <tr class="klasse-edit-row" id="kedit-<?= $ki ?>">
          <td colspan="4" style="padding:0.75rem">
            <?php
              $allSubjects = array_keys(array_merge(...array_values($stundentafeln)));
              foreach ($kinfo['faecher'] as $f => $_) {
                  if (!in_array($f, $allSubjects)) $allSubjects[] = $f;
              }
            ?>
            <form method="post">
              <input type="hidden" name="action" value="edit_klasse">
              <input type="hidden" name="old_name" value="<?= htmlspecialchars($kname) ?>">
              <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:0.75rem">
                <div class="form-group">
                  <label>Name</label>
                  <input type="text" name="name" value="<?= htmlspecialchars($kname) ?>" required>
                </div>
                <div class="form-group">
                  <label>Farbe</label>
                  <input type="color" name="farbe" value="<?= htmlspecialchars($kinfo['farbe']) ?>" style="height:38px;padding:2px 4px;border-radius:7px;border:1.5px solid var(--border)">
                  <?= colorSwatches($_usedColors) ?>
                </div>
              </div>
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.75rem">
                <?php foreach ($allSubjects as $f): ?>
                <div class="form-group" style="min-width:60px">
                  <label><?= htmlspecialchars($f) ?></label>
                  <input type="number" name="fach[<?= htmlspecialchars($f) ?>]"
                    min="0" max="30" style="width:60px"
                    value="<?= $kinfo['faecher'][$f] ?? 0 ?>">
                </div>
                <?php endforeach; ?>
              </div>
              <button type="submit" class="btn btn-primary">Speichern</button>
              <button type="button" class="btn btn-secondary"
                onclick="toggleKlasseEdit(<?= $ki ?>)">Abbrechen</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

  </section>

  <!-- Neue Klasse anlegen -->
  <section class="card">
    <h2>Neue Klasse anlegen</h2>
    <form method="post">
      <input type="hidden" name="action" value="add_klasse">
      <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem">
        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" placeholder="z.B. grün 1/2" required>
        </div>
        <div class="form-group">
          <label>Farbe</label>
          <input type="color" name="farbe" value="#888888" style="height:38px;padding:2px 4px;border-radius:7px;border:1.5px solid var(--border)">
          <?= colorSwatches($_usedColors) ?>
        </div>
        <div class="form-group">
          <label>Vorlage</label>
          <select onchange="applyKlasseVorlage(this.value)">
            <option value="">– keine –</option>
            <?php foreach ($stundentafeln as $stufe => $_): ?>
            <option value="<?= htmlspecialchars($stufe) ?>">Klasse <?= htmlspecialchars($stufe) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem" id="add-faecher">
        <?php $allSubjects = array_keys(array_merge(...array_values($stundentafeln))); ?>
        <?php foreach ($allSubjects as $f): ?>
        <div class="form-group" style="min-width:60px">
          <label><?= htmlspecialchars($f) ?></label>
          <input type="number" name="fach[<?= htmlspecialchars($f) ?>]"
            data-fach="<?= htmlspecialchars($f) ?>"
            min="0" max="30" style="width:60px" value="0">
        </div>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-primary">Klasse anlegen</button>
    </form>
  </section>

  <!-- Lehrkraft hinzufügen -->
  <section class="card">
    <h2>Lehrkraft hinzufügen</h2>
    <form method="post" class="form-row">
      <input type="hidden" name="action" value="add_lehrkraft">
      <div class="form-group">
        <label>Kürzel <span class="tip tip-right" data-tip="Eindeutige Abkürzung, max. 4 Zeichen (z.B. 'Bel'). Erster Buchstabe groß, Rest klein. Wird im Einsatzplan in die Zellen eingetragen.">?</span></label>
        <input type="text" name="kuerzel" maxlength="10" placeholder="z.B. Bel" required>
      </div>
      <div class="form-group">
        <label>Name</label>
        <input type="text" name="name" placeholder="Nachname, Vorname" required>
      </div>
      <div class="form-group">
        <label>Wochenstunden</label>
        <input type="number" name="stunden" min="1" max="40" placeholder="28" required>
      </div>
      <button type="submit" class="btn btn-primary">Hinzufügen</button>
    </form>
  </section>

  <!-- Lehrkräfte-Import -->
  <section class="card">
    <h2>Lehrkräfte aus Excel importieren <span class="tip" data-tip="In Excel die Zellen mit Name, Kürzel und Stundenzahl markieren, kopieren (Strg+C) und hier einfügen (Strg+V). Spalten werden automatisch erkannt und können per Dropdown angepasst werden.">?</span></h2>
    <p class="muted" style="margin-bottom:1rem;font-size:0.85rem">
      Zellen in Excel markieren (Name, Kürzel, Stunden – beliebige Reihenfolge), kopieren und hier einfügen.
      Bereits vorhandene Kürzel werden überschrieben.
    </p>
    <?php if (isset($_GET['imported'])): ?>
      <p style="color:#065f46;margin-bottom:1rem">
        ✓ <?= intval($_GET['imported']) ?> Lehrkraft<?= intval($_GET['imported']) !== 1 ? 'kräfte' : '' ?> importiert.
      </p>
    <?php endif; ?>
    <textarea id="import-paste" rows="4"
      placeholder="Hier Excel-Zellen einkopieren (Strg+V) …"
      style="width:100%;font-family:'DM Sans',sans-serif;font-size:0.85rem;
             padding:0.6rem 0.875rem;border:1.5px solid var(--border);border-radius:7px;
             background:var(--bg);resize:vertical;margin-bottom:0.75rem"></textarea>

    <div id="import-preview" style="display:none;margin-bottom:0.75rem;overflow-x:auto"></div>

    <form id="import-form" method="post" style="display:none">
      <input type="hidden" name="action" value="import_lehrkraefte">
      <input type="hidden" id="import-rows" name="rows">
      <input type="hidden" id="import-assigns" name="assigns">
      <button type="submit" class="btn btn-primary">Importieren</button>
      <button type="button" class="btn btn-secondary" onclick="importReset()">Zurücksetzen</button>
    </form>
  </section>

  <!-- Aktuelle Lehrkräfte -->
  <section class="card">
    <h2>Aktuelle Lehrkräfte</h2>
    <?php if (empty($data['lehrkraefte'])): ?>
      <p class="muted">Noch keine Lehrkräfte eingetragen.</p>
    <?php else: ?>
    <table class="table" id="lk-table">
      <thead>
        <tr><th>Kürzel</th><th>Name</th><th>Soll-Std.</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($data['lehrkraefte'] as $k => $lk): ?>
        <tr class="lk-row" id="row-<?= htmlspecialchars($k) ?>">
          <td><strong><?= htmlspecialchars($k) ?></strong></td>
          <td><?= htmlspecialchars($lk['name']) ?></td>
          <td><?= $lk['stunden'] ?></td>
          <td style="white-space:nowrap">
            <button type="button" class="btn btn-secondary btn-sm"
              onclick="toggleEdit('<?= htmlspecialchars($k, ENT_QUOTES) ?>')">Bearbeiten</button>
            <form method="post" style="display:inline"
              onsubmit="return confirm('Lehrkraft und alle ihre Einsätze löschen?')">
              <input type="hidden" name="action" value="delete_lehrkraft">
              <input type="hidden" name="kuerzel" value="<?= htmlspecialchars($k) ?>">
              <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
            </form>
          </td>
        </tr>
        <tr class="edit-row" id="edit-<?= htmlspecialchars($k) ?>">
          <td colspan="4">
            <form method="post" class="form-row">
              <input type="hidden" name="action" value="edit_lehrkraft">
              <input type="hidden" name="old_kuerzel" value="<?= htmlspecialchars($k) ?>">
              <div class="form-group">
                <label>Kürzel</label>
                <input type="text" name="kuerzel" maxlength="10"
                  value="<?= htmlspecialchars($k) ?>" required>
              </div>
              <div class="form-group">
                <label>Name</label>
                <input type="text" name="name"
                  value="<?= htmlspecialchars($lk['name']) ?>" required>
              </div>
              <div class="form-group">
                <label>Wochenstunden</label>
                <input type="number" name="stunden" min="1" max="40"
                  value="<?= $lk['stunden'] ?>" required>
              </div>
              <button type="submit" class="btn btn-primary">Speichern</button>
              <button type="button" class="btn btn-secondary"
                onclick="toggleEdit('<?= htmlspecialchars($k, ENT_QUOTES) ?>')">Abbrechen</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

  <!-- Datensicherung -->
  <section class="card">
    <h2>Datensicherung</h2>

    <?php
      $backupMsg = $_GET['backup'] ?? '';
      if ($backupMsg === 'ok'):
    ?>
      <p style="color:#065f46;margin-bottom:1rem">✓ Backup erfolgreich eingespielt.</p>
    <?php elseif ($backupMsg === 'invalid'): ?>
      <p style="color:var(--red);margin-bottom:1rem">Ungültige Backup-Datei – bitte eine Datei aus diesem Tool verwenden.</p>
    <?php elseif ($backupMsg === 'error'): ?>
      <p style="color:var(--red);margin-bottom:1rem">Fehler beim Hochladen der Datei.</p>
    <?php endif; ?>

    <div style="display:flex;gap:2rem;flex-wrap:wrap;align-items:flex-start">

      <div>
        <h3 style="font-size:0.85rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.6rem">Backup erstellen</h3>
        <p class="muted" style="font-size:0.85rem;margin-bottom:0.75rem">Lädt alle Daten (Klassen, Lehrkräfte, Einsätze, Einstellungen) als JSON-Datei herunter.</p>
        <form method="post">
          <input type="hidden" name="action" value="export">
          <button type="submit" class="btn btn-secondary">⬇ Backup herunterladen</button>
        </form>
      </div>

      <div>
        <h3 style="font-size:0.85rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.6rem">Backup einspielen</h3>
        <p class="muted" style="font-size:0.85rem;margin-bottom:0.75rem">Ersetzt alle aktuellen Daten durch den Stand der Backup-Datei.</p>
        <form method="post" enctype="multipart/form-data"
          onsubmit="return confirm('Alle aktuellen Daten werden durch das Backup ersetzt. Fortfahren?')">
          <input type="hidden" name="action" value="import">
          <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
            <input type="file" name="backup" accept=".json" required
              style="font-size:0.85rem">
            <button type="submit" class="btn btn-primary">⬆ Einspielen</button>
          </div>
        </form>
      </div>

    </div>
  </section>

  <!-- Einsätze zurücksetzen -->
  <section class="card card-danger">
    <h2>Einsätze zurücksetzen</h2>
    <p>Alle eingetragenen Unterrichtseinsätze löschen (Lehrkräfte und Zusatzaufgaben bleiben erhalten).</p>
    <form method="post" onsubmit="return confirm('Wirklich alle Einsätze löschen?')">
      <input type="hidden" name="action" value="reset_all">
      <button type="submit" class="btn btn-danger">Alle Einsätze zurücksetzen</button>
    </form>
  </section>
</main>

<script>
function toggleEdit(k) {
  const row = document.getElementById('edit-' + k);
  if (!row) return;
  const wasOpen = row.classList.contains('open');
  document.querySelectorAll('.edit-row').forEach(r => r.classList.remove('open'));
  if (!wasOpen) row.classList.add('open');
}

function toggleTafelEdit(idx) {
  const row = document.getElementById('tedit-' + idx);
  if (!row) return;
  const wasOpen = row.classList.contains('open');
  document.querySelectorAll('.tafel-edit-row').forEach(r => r.classList.remove('open'));
  if (!wasOpen) row.classList.add('open');
}

function toggleKlasseEdit(idx) {
  const row = document.getElementById('kedit-' + idx);
  if (!row) return;
  const wasOpen = row.classList.contains('open');
  document.querySelectorAll('.klasse-edit-row').forEach(r => r.classList.remove('open'));
  if (!wasOpen) row.classList.add('open');
}

// ── Lehrkräfte-Import aus Zwischenablage ─────────────────────────────────────
let _importRows = [];

function importReset() {
  _importRows = [];
  document.getElementById('import-paste').value = '';
  document.getElementById('import-preview').style.display = 'none';
  document.getElementById('import-form').style.display = 'none';
}

function importEscape(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function importAutoDetect(rows) {
  const ncols = Math.max(...rows.map(r => r.length));
  const assign = {};
  for (let c = 0; c < ncols; c++) {
    const vals = rows.map(r => (r[c] || '').trim()).filter(Boolean);
    if (!vals.length) continue;
    if (vals.every(v => /^\d+$/.test(v)) && !('stunden' in assign))
      assign.stunden = c;
    else if (vals.every(v => v.length <= 5) && !('kuerzel' in assign))
      assign.kuerzel = c;
    else if (!('name' in assign))
      assign.name = c;
  }
  return assign;
}

function importRender(rows) {
  if (!rows.length) return;
  _importRows = rows;
  const ncols = Math.max(...rows.map(r => r.length));
  const auto  = importAutoDetect(rows);
  const opts  = (col, sel) => ['', 'name', 'kuerzel', 'stunden']
    .map(v => `<option value="${v}" ${auto[v]===col&&v===sel?'selected':auto[v]===col&&!sel?'selected':''}>${
      v===''?'– ignorieren –':v==='name'?'Name':v==='kuerzel'?'Kürzel':'Stunden'
    }</option>`).join('');

  let html = '<table class="table"><thead><tr>';
  for (let c = 0; c < ncols; c++) {
    html += `<th><select class="col-assign" data-col="${c}"
      style="font-size:0.8rem;padding:0.2rem 0.4rem;border:1px solid var(--border);border-radius:4px">
      ${['','name','kuerzel','stunden'].map(v => {
        const label = v===''?'– ignorieren –':v==='name'?'Name':v==='kuerzel'?'Kürzel':'Stunden';
        const sel   = Object.entries(auto).find(([k,ci]) => ci===c)?.[0] === v ? 'selected' : '';
        return `<option value="${v}" ${sel}>${label}</option>`;
      }).join('')}
    </select></th>`;
  }
  html += '</tr></thead><tbody>';
  rows.slice(0, 6).forEach(row => {
    html += '<tr>' + Array.from({length: ncols}, (_,c) =>
      `<td style="font-size:0.85rem;padding:0.3rem 0.5rem">${importEscape(row[c]||'')}</td>`
    ).join('') + '</tr>';
  });
  if (rows.length > 6)
    html += `<tr><td colspan="${ncols}" class="muted" style="font-size:0.8rem;padding:0.3rem 0.5rem">… und ${rows.length-6} weitere Zeilen</td></tr>`;
  html += '</tbody></table>';

  const preview = document.getElementById('import-preview');
  preview.innerHTML = html;
  preview.style.display = 'block';
  document.getElementById('import-form').style.display = 'block';
}

document.getElementById('import-paste').addEventListener('paste', function() {
  setTimeout(() => {
    const text = this.value.trim();
    if (!text) return;
    const rows = text.split(/\r?\n/).map(l => l.split('\t'));
    importRender(rows);
  }, 30);
});

document.getElementById('import-form').addEventListener('submit', function(e) {
  const assigns = {};
  document.querySelectorAll('.col-assign').forEach(sel => {
    if (sel.value) assigns[sel.value] = parseInt(sel.dataset.col);
  });
  if (!('name' in assigns && 'kuerzel' in assigns && 'stunden' in assigns)) {
    alert('Bitte Name, Kürzel und Stunden einer Spalte zuweisen.');
    e.preventDefault();
    return;
  }
  document.getElementById('import-rows').value    = JSON.stringify(_importRows);
  document.getElementById('import-assigns').value = JSON.stringify(assigns);
});

// ── Vorlage für neue Klasse ───────────────────────────────────────────────────
const _vorlagen = <?= json_encode($stundentafeln) ?>;

function applySwatchColor(btn) {
  btn.closest('.form-group').querySelector('[name="farbe"]').value = btn.dataset.color;
}

function applyKlasseVorlage(stufe) {
  const tmpl = _vorlagen[stufe] || {};
  document.querySelectorAll('#add-faecher input[data-fach]').forEach(inp => {
    inp.value = tmpl[inp.dataset.fach] ?? 0;
  });
}
</script>
</body>
</html>
