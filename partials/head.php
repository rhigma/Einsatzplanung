<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --teal:    #2a7c6f;
    --teal-lt: #e8f4f2;
    --orange:  #e07b39;
    --blue:    #3a6ea5;
    --red:     #dc2626;
    --bg:      #f5f2ee;
    --card:    #ffffff;
    --text:    #1a1a1a;
    --muted:   #6b6b6b;
    --border:  #e0dbd4;

    /* Klassenfarben */
    --blau:  #3a6ea5;
    --gelb:  #d4a017;
    --rot:   #c0392b;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
  }

  /* NAV */
  nav {
    background: var(--card);
    border-bottom: 1px solid var(--border);
    padding: 0.75rem 2rem;
    display: flex;
    align-items: center;
    gap: 2rem;
  }

  .nav-brand {
    font-family: 'DM Serif Display', serif;
    font-size: 1rem;
    color: var(--text);
    text-decoration: none;
    white-space: nowrap;
  }

  .nav-brand small {
    display: block;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.65rem;
    color: var(--muted);
    font-weight: 300;
  }

  .nav-links {
    display: flex;
    gap: 0.25rem;
    flex: 1;
  }

  .nav-links a {
    padding: 0.4rem 0.875rem;
    border-radius: 6px;
    text-decoration: none;
    color: var(--muted);
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.15s;
  }

  .nav-links a:hover, .nav-links a.active {
    background: var(--teal-lt);
    color: var(--teal);
  }

  .nav-stats {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.75rem;
    white-space: nowrap;
  }

  .nav-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.15;
  }

  .nav-stat-val {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--text);
  }

  .nav-stat-lbl {
    font-size: 0.6rem;
    font-weight: 300;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--muted);
  }

  .nav-stat-sep {
    color: var(--border);
    font-size: 1.1rem;
    padding-bottom: 2px;
  }

  .nav-stat-bar {
    width: 48px;
    height: 6px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden;
    align-self: center;
  }

  .nav-stat-fill {
    display: block;
    height: 100%;
    background: var(--teal);
    border-radius: 3px;
    transition: width 0.3s;
  }

  .nav-logout {
    margin-left: auto;
  }

  /* LAYOUT */
  .container {
    max-width: calc(100vw - 2rem);
    margin: 0 auto;
    padding: 2rem;
  }

  h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 1.75rem;
    margin-bottom: 1.5rem;
  }

  h2 {
    font-size: 1rem;
    font-weight: 500;
    margin-bottom: 1rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: 0.8rem;
  }

  /* CARDS */
  .card {
    background: var(--card);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 8px rgba(0,0,0,0.06);
  }

  .card-danger { border-left: 3px solid var(--red); }

  /* FORMS */
  .form-row {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
    flex-wrap: wrap;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
  }

  label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  input[type="text"], input[type="number"], select {
    padding: 0.6rem 0.875rem;
    border: 1.5px solid var(--border);
    border-radius: 7px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    background: var(--bg);
    color: var(--text);
    transition: border-color 0.15s;
  }

  input:focus, select:focus {
    outline: none;
    border-color: var(--teal);
  }

  /* BUTTONS */
  .btn {
    padding: 0.6rem 1.25rem;
    border: none;
    border-radius: 7px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    text-decoration: none;
    display: inline-block;
  }

  .btn-primary { background: var(--teal); color: white; }
  .btn-primary:hover { background: #235f55; }
  .btn-secondary { background: var(--bg); color: var(--text); border: 1.5px solid var(--border); }
  .btn-secondary:hover { border-color: var(--teal); color: var(--teal); }
  .btn-danger { background: #fef2f2; color: var(--red); border: 1.5px solid #fca5a5; }
  .btn-danger:hover { background: var(--red); color: white; }
  .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }

  /* TABLE */
  .table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
  }

  .table th {
    text-align: left;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    border-bottom: 1px solid var(--border);
  }

  .table td {
    padding: 0.6rem 0.75rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
  }

  .table tr:last-child td { border-bottom: none; }
  .table tr:hover td { background: var(--bg); }

  .muted { color: var(--muted); font-size: 0.875rem; }

  /* ── Inline-Tooltip ── */
  .tip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--border);
    color: var(--muted);
    font-size: 0.65rem;
    font-weight: 700;
    cursor: help;
    position: relative;
    vertical-align: middle;
    flex-shrink: 0;
    transition: background 0.15s, color 0.15s;
    user-select: none;
    text-decoration: none;
  }
  .tip:hover { background: var(--teal); color: white; }
  .tip::after {
    content: attr(data-tip);
    position: absolute;
    bottom: calc(100% + 7px);
    left: 50%;
    transform: translateX(-50%);
    background: #1a1a1a;
    color: #fff;
    padding: 0.5rem 0.75rem;
    border-radius: 7px;
    font-size: 0.78rem;
    font-weight: 400;
    line-height: 1.45;
    white-space: normal;
    width: 240px;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 9999;
    text-align: left;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18);
  }
  .tip::before {
    content: '';
    position: absolute;
    bottom: calc(100% + 1px);
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1a1a1a;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 9999;
  }
  .tip:hover::after, .tip:hover::before { opacity: 1; }
  /* Tooltip nach rechts verschieben wenn am linken Rand */
  .tip.tip-right::after { left: 0; transform: none; }
  .tip.tip-right::before { left: 8px; transform: none; }

  /* BADGES */
  .badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
  }

  .badge-ok { background: #d1fae5; color: #065f46; }
  .badge-warn { background: #fef3c7; color: #92400e; }
  .badge-over { background: #fee2e2; color: #991b1b; }

  /* PRINT */
  @media print {
    nav, .no-print { display: none !important; }
    body { background: white; }
    .container { padding: 0; max-width: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
  }
</style>
