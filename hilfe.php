<?php
require_once 'config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Hilfe – Einsatzplanung</title>
<?php include 'partials/head.php'; ?>
<style>
  .hilfe-wrap {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 2rem;
    align-items: start;
  }

  /* Sidebar */
  .hilfe-nav {
    position: sticky;
    top: 1.5rem;
    background: var(--card);
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 1px 8px rgba(0,0,0,0.06);
  }
  .hilfe-nav h3 {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--muted);
    font-weight: 500;
    margin-bottom: 0.5rem;
    font-family: 'DM Sans', sans-serif;
  }
  .hilfe-nav a {
    display: block;
    padding: 0.3rem 0.5rem;
    font-size: 0.85rem;
    color: var(--muted);
    text-decoration: none;
    border-radius: 5px;
    transition: all 0.1s;
  }
  .hilfe-nav a:hover { background: var(--teal-lt); color: var(--teal); }
  .hilfe-nav .nav-sub { padding-left: 1rem; font-size: 0.8rem; }

  /* Inhalt */
  .hilfe-content { min-width: 0; }
  .hilfe-section {
    background: var(--card);
    border-radius: 12px;
    padding: 1.75rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 8px rgba(0,0,0,0.06);
    scroll-margin-top: 1rem;
  }
  .hilfe-section h2 {
    font-family: 'DM Serif Display', serif;
    font-size: 1.4rem;
    margin-bottom: 1rem;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .hilfe-section h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 1.25rem 0 0.5rem;
    color: var(--text);
  }
  .hilfe-section p {
    font-size: 0.9rem;
    line-height: 1.65;
    color: var(--text);
    margin-bottom: 0.75rem;
  }
  .hilfe-section ul, .hilfe-section ol {
    font-size: 0.9rem;
    line-height: 1.65;
    color: var(--text);
    margin-bottom: 0.75rem;
    padding-left: 1.4rem;
  }
  .hilfe-section li { margin-bottom: 0.2rem; }

  /* Farbige Hinweisboxen */
  .hint {
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    margin: 0.75rem 0;
    line-height: 1.5;
  }
  .hint-tip  { background: var(--teal-lt); border-left: 3px solid var(--teal); }
  .hint-warn { background: #fff7ed; border-left: 3px solid #e07b39; }
  .hint-info { background: #f0f4ff; border-left: 3px solid #3a6ea5; }

  /* Farbcodes-Demo */
  .color-demo {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 0.75rem 0;
  }
  .color-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
  }
  .color-swatch {
    width: 80px;
    height: 28px;
    border-radius: 5px;
    border: 1px solid var(--border);
    flex-shrink: 0;
  }

  /* Tastaturkürzel */
  .kbd-table { width: 100%; border-collapse: collapse; }
  .kbd-table td { padding: 0.4rem 0.5rem; font-size: 0.875rem; border-bottom: 1px solid var(--border); }
  .kbd-table tr:last-child td { border-bottom: none; }
  kbd {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 0.1rem 0.4rem;
    font-family: monospace;
    font-size: 0.82rem;
  }

  .icon { font-size: 1.2rem; }

  @media print { .hilfe-nav { display: none; } .hilfe-wrap { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<?php include 'partials/nav.php'; ?>

<main class="container" style="padding-top:2rem">
  <h1 style="margin-bottom:1.5rem">Hilfe &amp; Dokumentation</h1>

  <div class="hilfe-wrap">

    <!-- Sidebar -->
    <div class="hilfe-nav no-print">
      <h3>Inhalt</h3>
      <a href="#ueberblick">Überblick</a>
      <a href="#einsatzplan">Einsatzplan</a>
      <a class="nav-sub" href="#eingabe">Lehrkräfte eintragen</a>
      <a class="nav-sub" href="#mehrere">Mehrere Lehrkräfte</a>
      <a class="nav-sub" href="#farben">Farbcodes</a>
      <a class="nav-sub" href="#notizen">Notizen</a>
      <a class="nav-sub" href="#chips">Lehrkraft-Chips</a>
      <a href="#lehrkraefte">Lehrkräfte-Übersicht</a>
      <a href="#verwaltung">Verwaltung</a>
      <a class="nav-sub" href="#klassen">Klassen</a>
      <a class="nav-sub" href="#vorlagen">Stundentafeln</a>
      <a class="nav-sub" href="#import">Excel-Import</a>
      <a class="nav-sub" href="#einstellungen">Einstellungen</a>
      <a href="#druck">Druckansicht</a>
      <a href="#historie">Änderungshistorie</a>
      <a href="#tastatur">Tastaturkürzel</a>
    </div>

    <!-- Inhalt -->
    <div class="hilfe-content">

      <!-- ÜBERBLICK -->
      <section class="hilfe-section" id="ueberblick">
        <h2><span class="icon">🗺</span> Überblick</h2>
        <p>
          Die <strong>Einsatzplanung</strong> hilft dabei, Lehrkräfte übersichtlich
          den Unterrichtsstunden der einzelnen Klassen zuzuordnen. Das Tool läuft
          vollständig im Browser und speichert alle Daten lokal in einer JSON-Datei –
          keine Cloud, keine Fremddienste.
        </p>
        <p>Die Navigation besteht aus fünf Bereichen:</p>
        <ul>
          <li><strong>Einsatzplan</strong> – die zentrale Planungstabelle</li>
          <li><strong>Lehrkräfte</strong> – Übersicht über Auslastung und Einsätze</li>
          <li><strong>Verwaltung</strong> – Klassen, Lehrkräfte, Einstellungen</li>
          <li><strong>Druckansicht</strong> – druckoptimierte Ausgabe für Aushang oder Aushändigung</li>
          <li><strong>Historie</strong> – Protokoll aller Änderungen mit Zäsur-Markierungen</li>
        </ul>
        <p>
          Oben rechts in der Navigationsleiste zeigt ein kleiner Zähler jederzeit,
          wie viele Stunden bereits verteilt sind, wie viele insgesamt benötigt werden
          und wie viele Lehrkraft-Stunden verfügbar sind.
        </p>
      </section>

      <!-- EINSATZPLAN -->
      <section class="hilfe-section" id="einsatzplan">
        <h2><span class="icon">📋</span> Einsatzplan</h2>
        <p>
          Die Hauptseite zeigt eine Tabelle mit <strong>Klassen als Zeilen</strong>
          und <strong>Fächern als Spalten</strong>. Jede Zelle steht für einen
          Unterrichtseinsatz (eine Klasse in einem Fach).
        </p>

        <h3 id="eingabe">Lehrkraft eintragen</h3>
        <ol>
          <li>In das Kürzel-Feld einer Zelle klicken (zeigt „–")</li>
          <li>Kürzel der Lehrkraft tippen – <strong>Vorschläge</strong> erscheinen automatisch</li>
          <li>Mit <kbd>↓</kbd> / <kbd>↑</kbd> navigieren, mit <kbd>Enter</kbd> auswählen</li>
          <li>In das Stundenfeld (rechts davon) wechseln und die Stundenzahl eingeben</li>
          <li>Mit <kbd>Tab</kbd> oder Klick außerhalb wird automatisch gespeichert</li>
        </ol>
        <div class="hint hint-tip">
          <strong>Tipp:</strong> Nach der Auswahl aus dem Vorschlag springt der Fokus
          automatisch ins Stundenfeld – einfach die Zahl tippen und <kbd>Tab</kbd> drücken.
        </div>

        <h3 id="mehrere">Mehrere Lehrkräfte pro Unterricht</h3>
        <p>
          Wenn sich mehrere Lehrkräfte den Unterricht teilen, können mehrere Einträge
          pro Zelle gemacht werden. Nach dem Speichern der ersten Lehrkraft erscheint
          ein „+"-Feld für weitere. Jede Lehrkraft bekommt ihre eigene Stundenzahl.
        </p>
        <div class="hint hint-warn">
          <strong>Doppelsteckung:</strong> Wenn die Summe der eingetragenen Stunden die
          Sollstundenzahl des Fachs überschreitet, wird die Zelle <strong>orange</strong>
          hinterlegt – die Lehrkräfte sind in mindestens einer Stunde gleichzeitig eingeplant.
        </div>

        <h3>Klassenleitung (KL)</h3>
        <p>
          Die KL-Spalte ist vereinfacht: Es gibt nur ein Kürzel-Feld, keine Stundenzahl.
          Entweder ist eine Lehrkraft als Klassenleitung eingetragen oder nicht.
          Die Stunde zählt automatisch als 1 Std.
        </p>

        <h3 id="farben">Farbcodes der Zellen</h3>
        <div class="color-demo">
          <div class="color-row">
            <div class="color-swatch" style="background:#fff5f5"></div>
            <span><strong>Rot (hell)</strong> – Sollstunden noch nicht erreicht, noch Bedarf</span>
          </div>
          <div class="color-row">
            <div class="color-swatch" style="background:#fff7ed"></div>
            <span><strong>Orange (hell)</strong> – Doppelsteckung: Stunden übersteigen das Soll</span>
          </div>
          <div class="color-row">
            <div class="color-swatch" style="background:white;"></div>
            <span><strong>Weiß</strong> – Sollstunden genau erfüllt</span>
          </div>
          <div class="color-row">
            <div class="color-swatch" style="background:#f9f9f9;"></div>
            <span><strong>Grau</strong> – Fach wird in dieser Klasse nicht unterrichtet</span>
          </div>
        </div>
        <p>Die Sollstundenzahl steht klein am unteren Rand jeder Zelle (z.&nbsp;B. „3/8 Std.").</p>

        <h3 id="notizen">Notizen</h3>
        <p>
          Unterhalb des Eintragsfelds jeder Zelle befindet sich ein freies Textfeld
          für Notizen – z.&nbsp;B. „Vertretungsregelung klären" oder „auf Wunsch der LK".
          Notizen werden automatisch gespeichert, sobald das Feld verlassen wird,
          und erscheinen in der Druckansicht unterhalb der eingetragenen Lehrkraft.
          Eine Zelle mit Notiz zeigt einen gelben Akzentrahmen.
        </p>
        <div class="hint hint-tip">
          Notizänderungen werden auch in der <a href="historie.php">Änderungshistorie</a>
          protokolliert.
        </div>

        <h3 id="chips">Lehrkraft-Chips</h3>
        <p>
          Die Chip-Leiste oberhalb der Tabelle zeigt alle Lehrkräfte mit ihrer aktuellen
          Stundenauslastung. Sie bietet drei Funktionen:
        </p>

        <h3>Hover-Popover</h3>
        <p>
          Wenn die Maus über einen Chip bewegt wird, erscheint eine Karte mit
          vollständigem Namen, Fortschrittsbalken, Stunden-Status und einer Liste
          aller aktuell eingetragenen Einsätze inklusive Zusatzaufgaben.
          Die Liste aktualisiert sich live bei jeder Änderung in der Tabelle.
        </p>

        <h3>Sortierung</h3>
        <p>
          Mit den Buttons <strong>A–Z</strong> und <strong>Offen ↓</strong> links
          der Chip-Leiste lässt sich die Reihenfolge der Chips umschalten:
        </p>
        <ul>
          <li><strong>A–Z</strong> – alphabetisch nach Kürzel (Standard)</li>
          <li><strong>Offen ↓</strong> – absteigend nach noch offenen Stunden –
          nützlich, um schnell zu sehen, wer noch am meisten Kapazität hat</li>
        </ul>

        <h3>Lehrkraft hervorheben &amp; Zeilen filtern</h3>
        <p>
          Ein Klick auf einen Chip hebt alle Einsätze dieser Lehrkraft in der
          Tabelle hervor (Kürzel-Feld leuchtet teal auf).
          Erneuter Klick hebt die Markierung wieder auf.
        </p>
        <p>
          Ist ein Chip aktiv, lässt sich der Toggle <strong>„Nur Klassen mit Einsatz"</strong>
          einschalten. Dann werden alle Klassen ausgeblendet, in denen die markierte
          Lehrkraft <em>nicht</em> unterrichtet – hilfreich bei vielen Klassen.
          Beim Abwählen des Chips oder Ausschalten des Toggles erscheinen alle Zeilen
          wieder.
        </p>

        <h3>Zäsur setzen</h3>
        <p>
          Mit dem Eingabefeld oben rechts lässt sich ein benannter Zeitstempel in die
          <a href="historie.php">Änderungshistorie</a> einfügen – z.&nbsp;B.
          „Wünsche der Kollegen erfasst" oder „Finale Planung".
          So lassen sich Planungsphasen später klar voneinander abgrenzen.
        </p>

        <h3>Zusatzaufgaben</h3>
        <p>
          Unterhalb der Tabelle können Aufgaben außerhalb des regulären Unterrichts
          eingetragen werden (AGs, Förderkurse, Schulleitung usw.). Sie fließen in
          die Stunden-Auslastung der jeweiligen Lehrkraft ein.
        </p>
      </section>

      <!-- LEHRKRÄFTE -->
      <section class="hilfe-section" id="lehrkraefte">
        <h2><span class="icon">👩‍🏫</span> Lehrkräfte-Übersicht</h2>
        <p>
          Zeigt für jede Lehrkraft eine Karte mit Fortschrittsbalken, dem Verhältnis
          eingetragener zu Soll-Stunden sowie allen einzelnen Einsätzen inklusive
          Zusatzaufgaben.
        </p>
        <div class="color-demo">
          <div class="color-row">
            <div class="color-swatch" style="background:#6ee7b7;border-color:#6ee7b7"></div>
            <span><strong>Grüner Rand</strong> – Sollstunden genau erfüllt</span>
          </div>
          <div class="color-row">
            <div class="color-swatch" style="background:#fca5a5;border-color:#fca5a5"></div>
            <span><strong>Roter Rand</strong> – Stunden überschreiten das Soll</span>
          </div>
          <div class="color-row">
            <div class="color-swatch"></div>
            <span><strong>Neutraler Rand</strong> – Stunden noch nicht vollständig vergeben</span>
          </div>
        </div>
        <p>
          Geteilte Unterrichtstunden (mehrere Lehrkräfte in einer Zelle) werden mit
          den individuell eingetragenen Stunden berücksichtigt.
        </p>
      </section>

      <!-- VERWALTUNG -->
      <section class="hilfe-section" id="verwaltung">
        <h2><span class="icon">⚙️</span> Verwaltung</h2>

        <h3 id="klassen">Klassen</h3>
        <p>
          Jede Klasse hat einen Namen, eine Farbe (für die Zeilenköpfe) und ein
          Fach-Stunden-Raster. Über <strong>Bearbeiten</strong> lassen sich Name,
          Farbe und Stundenzahlen pro Fach anpassen. Mit <strong>Löschen</strong>
          werden auch alle Unterrichtseinsätze dieser Klasse entfernt.
        </p>
        <div class="hint hint-warn">
          <strong>Achtung:</strong> Das Löschen einer Klasse entfernt unwiderruflich
          alle zugehörigen Einsätze aus der Datenbank.
        </div>

        <h3 id="vorlagen">Stundentafeln-Vorlagen</h3>
        <p>
          Vorlagen definieren das Standard-Stundengitter für eine Klassenstufe.
          Sie werden beim Anlegen einer neuen Klasse als Ausgangspunkt angeboten.
          Vorhandene Vorlagen lassen sich bearbeiten, neue anlegen.
        </p>
        <p>
          Im Bearbeitungsformular kann zusätzlich ein neues Fach (z.&nbsp;B.
          „Ethik") direkt mit Stundenzahl hinzugefügt werden. Felder mit dem Wert
          0 werden nicht gespeichert.
        </p>

        <h3 id="import">Excel-Import</h3>
        <p>
          Lehrkräfte können direkt aus Excel eingefügt werden – ohne Datei-Upload:
        </p>
        <ol>
          <li>In Excel die Zellen mit Name, Kürzel und Stundenzahl markieren</li>
          <li><kbd>Strg</kbd>+<kbd>C</kbd> kopieren</li>
          <li>Im Textfeld unter „Lehrkräfte aus Excel importieren" einfügen (<kbd>Strg</kbd>+<kbd>V</kbd>)</li>
          <li>Spalten per Dropdown zuweisen (werden automatisch erkannt)</li>
          <li>„Importieren" klicken</li>
        </ol>
        <div class="hint hint-tip">
          Bereits vorhandene Kürzel werden überschrieben – der Import lässt sich also
          auch zum Aktualisieren bestehender Daten nutzen.
        </div>

        <h3 id="einstellungen">Einstellungen</h3>
        <p>
          <strong>Schulname</strong> und <strong>Schuljahr</strong> erscheinen in der
          Navigation, auf der Login-Seite und in der Druckansicht.
          Das <strong>Passwort</strong> lässt sich hier ändern; es wird als sicherer
          Hash gespeichert. Das Klartext-Passwort in <code>config.php</code> gilt nur
          noch als Erststart-Fallback.
        </p>
      </section>

      <!-- DRUCK -->
      <section class="hilfe-section" id="druck">
        <h2><span class="icon">🖨</span> Druckansicht</h2>
        <p>Es gibt zwei Druckmodi, umschaltbar über die Toolbar:</p>
        <ul>
          <li>
            <strong>Unterrichtstabelle</strong> (A4 quer) – die vollständige
            Planungstabelle mit bereits eingetragenen Lehrkräften und Schreiblinien
            für handschriftliche Ergänzungen. Gut geeignet zum Aushängen.
          </li>
          <li>
            <strong>Pro Lehrkraft</strong> (A4 hoch) – je eine Seite pro Lehrkraft
            mit allen Einsätzen, Stunden-Fortschrittsbalken und Zusatzaufgaben.
            Gut geeignet zum Aushändigen an die jeweilige Person.
          </li>
        </ul>
        <p>
          Beim Druck werden Toolbar und Navigation automatisch ausgeblendet.
          Der Browser-Druckdialog erscheint nach Klick auf „🖨 Drucken".
        </p>
      </section>

      <!-- HISTORIE -->
      <section class="hilfe-section" id="historie">
        <h2><span class="icon">📜</span> Änderungshistorie</h2>
        <p>
          Jede Änderung im Einsatzplan wird automatisch mit Zeitstempel protokolliert –
          Unterrichtseinsätze (Vorher → Nachher) sowie Zusatzaufgaben.
          Die Geschichte ist auf 2.000 Einträge begrenzt, ältere fallen heraus.
        </p>
        <h3>Zäsur-Punkte</h3>
        <p>
          Über das Eingabefeld oben rechts im Einsatzplan lassen sich benannte
          Markierungen in die Geschichte einfügen. Sie erscheinen als farbige
          Trennlinie mit Flag-Symbol und helfen dabei, verschiedene Planungsphasen
          zu unterscheiden.
        </p>
        <div class="hint hint-tip">
          <strong>Empfohlener Workflow:</strong><br>
          1. Wünsche der Kollegen eintragen → Zäsur „Wünsche erfasst"<br>
          2. Finale Planung vornehmen → Zäsur „Finale Version"<br>
          3. In der Historie lässt sich später jederzeit nachvollziehen, was sich
          zwischen beiden Phasen geändert hat.
        </div>
      </section>

      <!-- TASTATUR -->
      <section class="hilfe-section" id="tastatur">
        <h2><span class="icon">⌨️</span> Tastaturkürzel im Einsatzplan</h2>
        <table class="kbd-table">
          <tr>
            <td><kbd>↓</kbd> / <kbd>↑</kbd></td>
            <td>Im Vorschlag-Dropdown navigieren</td>
          </tr>
          <tr>
            <td><kbd>Enter</kbd></td>
            <td>Vorschlag übernehmen oder Eingabe bestätigen (speichert)</td>
          </tr>
          <tr>
            <td><kbd>Escape</kbd></td>
            <td>Dropdown schließen / Eingabe auf den letzten gespeicherten Wert zurücksetzen</td>
          </tr>
          <tr>
            <td><kbd>Tab</kbd></td>
            <td>Zum nächsten Feld wechseln (speichert automatisch)</td>
          </tr>
        </table>
      </section>

    </div><!-- /hilfe-content -->
  </div><!-- /hilfe-wrap -->
</main>
</body>
</html>
