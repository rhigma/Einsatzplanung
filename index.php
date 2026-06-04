<?php
require_once 'config.php';

if (needsSetup()) {
    header('Location: setup.php');
    exit;
}

// Login-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $loginData = loadData();
        if (checkPassword($_POST['password'] ?? '', $loginData)) {
            $_SESSION['logged_in'] = true;
            header('Location: einsatzplan.php');
            exit;
        } else {
            $error = 'Falsches Passwort.';
        }
    }
    if ($_POST['action'] === 'logout') {
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

if (isLoggedIn()) {
    header('Location: einsatzplan.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Einsatzplanung – <?= htmlspecialchars($schulname) ?></title>
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

  .login-card {
    background: var(--card);
    border-radius: 16px;
    padding: 3rem;
    width: 100%;
    max-width: 400px;
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

  .logo-dots span {
    border-radius: 50%;
    display: block;
  }
  .logo-dots span:nth-child(1) { background: var(--teal); }
  .logo-dots span:nth-child(2) { background: var(--orange); }
  .logo-dots span:nth-child(3) { background: var(--blue); }
  .logo-dots span:nth-child(4) { background: var(--teal); opacity: 0.4; }

  .logo-text {
    font-family: 'DM Serif Display', serif;
    font-size: 1rem;
    color: var(--text);
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
    color: var(--text);
    margin-bottom: 0.5rem;
  }

  .subtitle {
    color: var(--muted);
    font-size: 0.875rem;
    margin-bottom: 2rem;
    font-weight: 300;
  }

  label {
    display: block;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 0.4rem;
  }

  input[type="password"] {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid #e0dbd4;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 1rem;
    background: var(--bg);
    color: var(--text);
    transition: border-color 0.2s;
    margin-bottom: 1.25rem;
  }

  input[type="password"]:focus {
    outline: none;
    border-color: var(--teal);
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
  }

  .btn:hover { background: #235f55; }

  .error {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #dc2626;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    margin-bottom: 1rem;
  }
</style>
</head>
<body>
<div class="login-card">
  <div class="logo">
    <div class="logo-dots">
      <span></span><span></span><span></span><span></span>
    </div>
    <div class="logo-text">
      05G33
      <small><?= htmlspecialchars($schulname) ?></small>
    </div>
  </div>

  <h1>Einsatzplanung</h1>
  <p class="subtitle">Schuljahr <?= htmlspecialchars($schuljahr) ?></p>

  <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="action" value="login">
    <label for="password">Passwort</label>
    <input type="password" name="password" id="password" autofocus>
    <button type="submit" class="btn">Anmelden</button>
  </form>
</div>
</body>
</html>
