<?php
$current = basename($_SERVER['PHP_SELF']);
$_navData = isset($data) ? $data : loadData();
$_ns = globalStats($_navData, $klassen);
$_verteiltGes = $_ns['verteilt'] + $_ns['verteiltKL'] + $_ns['zusatz'];
$_offen  = max(0, $_ns['soll'] - $_verteiltGes);
$_pct    = $_ns['soll'] > 0 ? min(100, round($_verteiltGes / $_ns['soll'] * 100)) : 0;
$_pctLk  = $_ns['verfuegbar'] > 0 ? min(100, round($_verteiltGes / $_ns['verfuegbar'] * 100)) : 0;
?>
<nav>
  <a href="einsatzplan.php" class="nav-brand">
    <?= htmlspecialchars($schuljahr) ?>
    <small><?= htmlspecialchars($schulname) ?></small>
  </a>
  <div class="nav-links">
    <a href="einsatzplan.php" class="<?= $current === 'einsatzplan.php' ? 'active' : '' ?>">Einsatzplan</a>
    <a href="lehrkraefte.php" class="<?= $current === 'lehrkraefte.php' ? 'active' : '' ?>">Lehrkräfte</a>
    <a href="verwaltung.php" class="<?= $current === 'verwaltung.php' ? 'active' : '' ?>">Verwaltung</a>
    <a href="druck.php" class="<?= $current === 'druck.php' ? 'active' : '' ?>">Druckansicht</a>
    <a href="historie.php" class="<?= $current === 'historie.php' ? 'active' : '' ?>">Historie</a>
    <a href="hilfe.php" class="<?= $current === 'hilfe.php' ? 'active' : '' ?>">Hilfe</a>
  </div>
  <div class="nav-stats no-print">

    <span class="nav-stat" title="Unterrichtsstunden + Zusatzaufgaben insgesamt vergeben">
      <span class="nav-stat-val" id="nav-verteilt-ges"><?= $_verteiltGes ?></span>
      <span class="nav-stat-lbl">ges. verteilt</span>
    </span>
    <span class="nav-stat-sep">/</span>
    <span class="nav-stat" title="Summe aller Lehrkraft-Sollstunden">
      <span class="nav-stat-val"><?= $_ns['verfuegbar'] ?></span>
      <span class="nav-stat-lbl">Soll Lehrkr.</span>
    </span>

    <span class="nav-stat-bar" title="<?= $_pctLk ?>% der Lehrkraft-Kapazität vergeben" id="nav-bar-wrap">
      <span class="nav-stat-fill" id="nav-bar-fill" style="width:<?= $_pctLk ?>%"></span>
    </span>

    <span class="nav-stat-sep">·</span>

    <span class="nav-stat" title="Im Unterricht verteilte Stunden">
      <span class="nav-stat-val" id="nav-verteilt-u"><?= $_ns['verteilt'] ?></span>
      <span class="nav-stat-lbl">Unterricht</span>
    </span>
    <span class="nav-stat-sep">/</span>
    <span class="nav-stat" title="Gesamter Unterrichtsbedarf aller Klassen und Fächer">
      <span class="nav-stat-val"><?= $_ns['soll'] ?></span>
      <span class="nav-stat-lbl">Bedarf U.</span>
    </span>

    <span class="nav-stat-sep">·</span>

    <span class="nav-stat" title="Klassenleitung: vergeben / benötigt">
      <span class="nav-stat-val" id="nav-verteilt-kl"><?= $_ns['verteiltKL'] ?>/<?= $_ns['sollKL'] ?></span>
      <span class="nav-stat-lbl">KL</span>
    </span>

    <span class="nav-stat-sep">·</span>

    <span class="nav-stat" title="In Zusatzaufgaben gebundene Stunden">
      <span class="nav-stat-val"><?= $_ns['zusatz'] ?></span>
      <span class="nav-stat-lbl">Zusatz</span>
    </span>

    <span class="nav-stat" id="nav-offen-stat"
      title="Summe der noch freien Lehrkraft-Kapazität (Soll − bisher eingetragen)"
      style="<?= $_ns['lkOffen'] > 0 ? 'color:var(--red)' : 'color:#065f46' ?>">
      <span class="nav-stat-val" id="nav-offen-val"><?= $_ns['lkOffen'] ?></span>
      <span class="nav-stat-lbl" id="nav-offen-lbl"><?= $_ns['lkOffen'] > 0 ? 'LK noch offen' : '✓ fertig' ?></span>
    </span>

  </div>
  <form method="post" action="index.php" class="nav-logout no-print">
    <input type="hidden" name="action" value="logout">
    <button type="submit" class="btn btn-secondary btn-sm">Abmelden</button>
  </form>
</nav>
