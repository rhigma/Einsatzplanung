<?php
require_once 'config.php';

if (!needsSetup()) {
    header('Location: ' . (isLoggedIn() ? 'einsatzplan.php' : 'index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Backup einspielen ──────────────────────────────────────────────────────
    if (($_POST['mode'] ?? '') === 'backup') {
        if (!isset($_FILES['backup']) || $_FILES['backup']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Fehler beim Hochladen der Datei.';
        } else {
            $json     = file_get_contents($_FILES['backup']['tmp_name']);
            $imported = json_decode($json, true);
            if (!is_array($imported) || (!isset($imported['lehrkraefte']) && !isset($imported['klassen']))) {
                $error = 'Ungültige Backup-Datei – bitte eine Datei aus diesem Tool verwenden.';
            } else {
                file_put_contents(DATA_FILE, $json);
                header('Location: index.php');
                exit;
            }
        }

    // ── Neu einrichten ─────────────────────────────────────────────────────────
    } else {
        $schulname = trim($_POST['schulname'] ?? '');
        $schuljahr = trim($_POST['schuljahr'] ?? '');
        $pw        = $_POST['password']         ?? '';
        $pwRepeat  = $_POST['password_confirm'] ?? '';

        if (!$schulname || !$schuljahr) {
            $error = 'Bitte Schulname und Schuljahr angeben.';
        } elseif (strlen($pw) < 6) {
            $error = 'Das Passwort muss mindestens 6 Zeichen haben.';
        } elseif ($pw !== $pwRepeat) {
            $error = 'Die Passwörter stimmen nicht überein.';
        } else {
            $data = loadData();
            $data['einstellungen']['schulname']     = $schulname;
            $data['einstellungen']['schuljahr']     = $schuljahr;
            $data['einstellungen']['passwort_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            saveData($data);
            $_SESSION['logged_in'] = true;
            header('Location: einsatzplan.php');
            exit;
        }
    }
}

$activeTab = ($_POST['mode'] ?? '') === 'backup' ? 'backup' : 'new';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ersteinrichtung – Einsatzplanung</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500&display=swap');

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --teal:   #2a7c6f;
    --orange: #e07b39;
    --blue:   #3a6ea5;
    --bg:     #f5f2ee;
    --card:   #ffffff;
    --text:   #1a1a1a;
    --muted:  #6b6b6b;
    --border: #e0dbd4;
    --teal-lt:#e8f4f2;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
  }

  .setup-card {
    background: var(--card);
    border-radius: 16px;
    padding: 3rem;
    width: 100%;
    max-width: 460px;
    box-shadow: 0 4px 40px rgba(0,0,0,0.08);
  }

  .logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 2rem;
  }

  .logo-dots {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px;
    width: 32px;
    height: 32px;
  }

  .logo-dots span { border-radius: 50%; display: block; }
  .logo-dots span:nth-child(1) { background: var(--teal); }
  .logo-dots span:nth-child(2) { background: var(--orange); }
  .logo-dots span:nth-child(3) { background: var(--blue); }
  .logo-dots span:nth-child(4) { background: var(--teal); opacity: 0.4; }

  .logo-text {
    font-family: 'DM Serif Display', serif;
    font-size: 1rem;
    line-height: 1.2;
  }
  .logo-text small {
    display: block;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.7rem;
    color: var(--muted);
    font-weight: 300;
  }

  h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 1.75rem;
    margin-bottom: 0.4rem;
  }

  .subtitle {
    color: var(--muted);
    font-size: 0.875rem;
    margin-bottom: 1.75rem;
    font-weight: 300;
    line-height: 1.5;
  }

  /* Tabs */
  .tabs {
    display: flex;
    gap: 0;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 1.75rem;
  }

  .tab-btn {
    flex: 1;
    padding: 0.6rem 0.75rem;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    font-weight: 500;
    border: none;
    background: transparent;
    color: var(--muted);
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
  }

  .tab-btn + .tab-btn { border-left: 1.5px solid var(--border); }
  .tab-btn.active { background: var(--teal-lt); color: var(--teal); }

  .tab-panel { display: none; }
  .tab-panel.active { display: block; }

  /* Form */
  .form-group { margin-bottom: 1.25rem; }

  label {
    display: block;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 0.4rem;
  }

  input[type="text"],
  input[type="password"],
  input[type="file"] {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 1rem;
    background: var(--bg);
    color: var(--text);
    transition: border-color 0.2s;
  }

  input[type="file"] { padding: 0.6rem 1rem; cursor: pointer; }
  input:focus { outline: none; border-color: var(--teal); }

  .divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 1.5rem 0;
  }

  .section-label {
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--muted);
    margin-bottom: 1rem;
  }

  .row { display: flex; gap: 0.75rem; }
  .row .form-group { flex: 1; }

  .hint {
    background: var(--teal-lt);
    border-left: 3px solid var(--teal);
    border-radius: 6px;
    padding: 0.65rem 0.875rem;
    font-size: 0.825rem;
    color: var(--text);
    line-height: 1.5;
    margin-bottom: 1.25rem;
  }

  .btn {
    width: 100%;
    padding: 0.875rem;
    background: var(--teal);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 0.25rem;
  }

  .btn:hover { background: #235f55; }

  .error {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #dc2626;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    margin-bottom: 1.25rem;
  }
</style>
</head>
<body>
<div class="setup-card">

  <div class="logo">
    <div class="logo-dots">
      <span></span><span></span><span></span><span></span>
    </div>
    <div class="logo-text">
      Einsatzplanung
      <small>Ersteinrichtung</small>
    </div>
  </div>

  <h1>Willkommen</h1>
  <p class="subtitle">Richten Sie das System neu ein oder spielen Sie ein bestehendes Backup ein.</p>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="tabs">
    <button type="button" class="tab-btn <?= $activeTab === 'new' ? 'active' : '' ?>"
            onclick="switchTab('new')">Neu einrichten</button>
    <button type="button" class="tab-btn <?= $activeTab === 'backup' ? 'active' : '' ?>"
            onclick="switchTab('backup')">Aus Backup wiederherstellen</button>
  </div>

  <!-- Tab: Neu einrichten -->
  <div class="tab-panel <?= $activeTab === 'new' ? 'active' : '' ?>" id="tab-new">
    <form method="post">
      <input type="hidden" name="mode" value="new">

      <p class="section-label">Schule</p>

      <div class="row">
        <div class="form-group">
          <label for="schulname">Schulname</label>
          <input type="text" name="schulname" id="schulname"
                 placeholder="z.B. 33. Grundschule"
                 value="<?= htmlspecialchars($_POST['schulname'] ?? '') ?>"
                 autofocus required>
        </div>
        <div class="form-group" style="flex:0 0 120px">
          <label for="schuljahr">Schuljahr</label>
          <input type="text" name="schuljahr" id="schuljahr"
                 placeholder="2025/26"
                 value="<?= htmlspecialchars($_POST['schuljahr'] ?? '') ?>"
                 required>
        </div>
      </div>

      <hr class="divider">

      <p class="section-label">Zugang</p>

      <div class="form-group">
        <label for="password">Passwort</label>
        <input type="password" name="password" id="password"
               placeholder="Mindestens 6 Zeichen" required>
      </div>

      <div class="form-group">
        <label for="password_confirm">Passwort bestätigen</label>
        <input type="password" name="password_confirm" id="password_confirm" required>
      </div>

      <button type="submit" class="btn">Einrichten &amp; loslegen</button>
    </form>
  </div>

  <!-- Tab: Backup einspielen -->
  <div class="tab-panel <?= $activeTab === 'backup' ? 'active' : '' ?>" id="tab-backup">
    <div class="hint">
      Das Backup enthält alle Daten (Klassen, Lehrkräfte, Einsätze) und das gespeicherte Passwort.
      Nach dem Einspielen werden Sie zur Anmeldung weitergeleitet.
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="mode" value="backup">
      <div class="form-group">
        <label for="backup">Backup-Datei (.json)</label>
        <input type="file" name="backup" id="backup" accept=".json" required>
      </div>
      <button type="submit" class="btn">Backup einspielen</button>
    </form>
  </div>

</div>

<script>
function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach((b, i) => {
    b.classList.toggle('active', (i === 0) === (name === 'new'));
  });
  document.querySelectorAll('.tab-panel').forEach(p => {
    p.classList.toggle('active', p.id === 'tab-' + name);
  });
}
</script>
</body>
</html>
