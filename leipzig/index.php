<?php
define('APP_BASE_PATH', '/');

$config = [];
$configPath = __DIR__ . '/config/app.json';
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
}

$appName = $config['product'] ?? 'GastroRadar';
$appCity = $config['city'] ?? '';
// --------------------------------------------------
// Quellen (GitHub RAW)
// --------------------------------------------------
$cacheBuster = date('Ymd');

$sources = [
    'messe' => 'https://raw.githubusercontent.com/SaschaUherek/eventkalender/refs/heads/main/data/events_messe.json?cb=' . $cacheBuster,
    'arena' => 'https://raw.githubusercontent.com/SaschaUherek/eventkalender/refs/heads/main/data/events_arena.json?cb=' . $cacheBuster,
    'rb' => 'https://raw.githubusercontent.com/SaschaUherek/eventkalender/refs/heads/main/data/events_rb.json?cb=' . $cacheBuster,
    'city' => 'https://raw.githubusercontent.com/SaschaUherek/eventkalender/refs/heads/main/data/events_stadt.json?cb=' . $cacheBuster,
];

// Basis-URLs für Bilder
$imageBase = [
    'messe' => 'https://www.leipziger-messe.de',
    'arena' => 'https://www.quarterback-immobilien-arena.de',
    'city'  => 'https://www.leipzig.de',
];

$weekdayMap = [
    'Monday'    => 'Montag',
    'Tuesday'   => 'Dienstag',
    'Wednesday' => 'Mittwoch',
    'Thursday'  => 'Donnerstag',
    'Friday'    => 'Freitag',
    'Saturday'  => 'Samstag',
    'Sunday'    => 'Sonntag',
];

// --------------------------------------------------
// Woche bestimmen
// --------------------------------------------------
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

$weekStart = new DateTime('monday this week');
if ($weekOffset !== 0) {
    $weekStart->modify(($weekOffset > 0 ? '+' : '') . $weekOffset . ' week');
}
$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');

// --------------------------------------------------
// Feiertage laden (jahresabhängig)
// --------------------------------------------------
$yearsToLoad = [];
$yearsToLoad[] = (int)$weekStart->format('Y');
if ($weekEnd->format('Y') !== $weekStart->format('Y')) {
    $yearsToLoad[] = (int)$weekEnd->format('Y');
}

$holidayMap = [];

foreach ($yearsToLoad as $year) {
    $url = "https://raw.githubusercontent.com/SaschaUherek/eventkalender/main/data/holidays_sn_{$year}.json";
    $json = @file_get_contents($url);
    if (!$json) continue;

    $data = json_decode($json, true);
    if (empty($data['holidays'])) continue;

    foreach ($data['holidays'] as $h) {
        $holidayMap[$h['date']] = $h['name'];
    }
}

// --------------------------------------------------
// Events laden
// --------------------------------------------------
$allEvents = [];
$seenCityEvents = [];

foreach ($sources as $key => $url) {
    $json = @file_get_contents($url);
    if (!$json) continue;

    $data = json_decode($json, true);
    if (empty($data['events'])) continue;

    /*
    foreach ($data['events'] as $event) {
        if (empty($event['startDate'])) continue;

        // City-Events ohne Location ausfiltern
        if ($key === 'city' && empty($event['location'])) {
            continue;
        }

        $event['source'] = $key;
        $allEvents[] = $event;
    }
    */

    foreach ($data['events'] as $event) {
        if (empty($event['startDate'])) continue;

        // City: Events ohne Location ausfiltern
        if ($key === 'city' && empty($event['location'])) {
            continue;
        }

        // City: Mehrfacheinträge deduplizieren
        if ($key === 'city') {
            $dedupeKey = md5(
                mb_strtolower(trim($event['title'])) . '|' .
                ($event['startDate'] ?? '') . '|' .
                ($event['endDate'] ?? '')
            );

            if (isset($seenCityEvents[$dedupeKey])) {
                continue;
            }

            $seenCityEvents[$dedupeKey] = true;
        }

        $event['source'] = $key;
        $allEvents[] = $event;
    }
}

usort($allEvents, fn($a, $b) => strcmp($a['startDate'], $b['startDate']));

// --------------------------------------------------
// Events der Woche gruppieren
// --------------------------------------------------
$eventsByDay = [];

foreach ($allEvents as $event) {
    $dt = new DateTime($event['startDate']);
    if ($dt < $weekStart || $dt > $weekEnd) continue;

    $key = $dt->format('Y-m-d');
    $eventsByDay[$key][] = $event;
}

// ---------------------------------------------
// Wochen-Badges berechnen
// ---------------------------------------------
$eventsThisWeek = 0;
$rbThisWeek = false;
$holidayThisWeek = false;

// 1) Events & RB nur aus Event-Tagen
foreach ($eventsByDay as $events) {
    $eventsThisWeek += count($events);

    foreach ($events as $e) {
        if ($e['source'] === 'rb') {
            $rbThisWeek = true;
        }
    }
}

// 2) Feiertage unabhängig von Events (ganze Woche prüfen)
$checkDay = clone $weekStart;
while ($checkDay <= $weekEnd) {
    $checkDate = $checkDay->format('Y-m-d');
    if (isset($holidayMap[$checkDate])) {
        $holidayThisWeek = true;
        break;
    }
    $checkDay->modify('+1 day');
}

$manyEvents = $eventsThisWeek >= 4;
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<title>GastroRadar · Leipzig</title>
<link rel="stylesheet" href="/leipzig/assets/style.css">
</head>

<body>

<header class="app-header">

  <!-- LOGO / APP NAME -->
  <div class="header-brand">
    <img src="/leipzig/img/gr-logo.png">
  </div>

  <!-- BADGES -->
  <div class="header-badges">

    <a href="search.php?week=<?= $weekOffset ?>" class="search-link" title="Events durchsuchen">
      🔍
    </a>

    <?php if ($manyEvents): ?>
      <span class="week-alert">🔥 Events</span>
    <?php endif; ?>

    <?php if ($rbThisWeek): ?>
      <span class="week-alert rb-alert">⚽ Heimspiel</span>
    <?php endif; ?>

    <?php if ($holidayThisWeek): ?>
      <span class="week-alert holiday-alert">🎉 Feiertag</span>
    <?php endif; ?>

    <a href="week.php?week=<?= $weekOffset ?>" class="week-alert week-switch has-hint">
       WochenRadar
      <span class="hint-dot"></span>
    </a>  
  </div>

  <!-- VIEW SWITCH -->
  <!-- <div class="view-switch">
    <a href="week.php?week=<?= $weekOffset ?>" class="week-link">
      Auslastung diese Woche
    </a>
  </div> -->

  <!-- WEEK NAV -->
<nav class="week-nav">

  <?php if ($weekOffset > 0): ?>
    <a href="?week=<?= $weekOffset - 1 ?>" class="nav-btn prev" aria-label="Vorige Woche">
      ‹
    </a>
  <?php else: ?>
    <span class="nav-btn placeholder"></span>
  <?php endif; ?>

  <span class="week-label">
    KW <?= $weekStart->format('W') ?>
    · <?= $weekStart->format('d.m.') ?> – <?= $weekEnd->format('d.m.') ?>
  </span>

  <a href="?week=<?= $weekOffset + 1 ?>" class="nav-btn next" aria-label="Nächste Woche">
    ›
  </a>

</nav>

</header>

<?php //include __DIR__ . '/partials/adslot.php'; ?>

<main class="calendar">

<?php
$day = clone $weekStart;

while ($day <= $weekEnd):

    $date = $day->format('Y-m-d');
    $events = $eventsByDay[$date] ?? [];
    $weekdayEn = $day->format('l');
    $weekdayDe = $weekdayMap[$weekdayEn] ?? $weekdayEn;
?>

<section class="day<?= empty($events) ? ' day-empty' : '' ?>">
  <h2>
    <?= $weekdayDe ?>, <?= $day->format('d.m.') ?>

    <?php if (isset($holidayMap[$date])): ?>
      <span class="holiday-badge">
        🎉 <?= htmlspecialchars($holidayMap[$date]) ?>
      </span>
    <?php endif; ?>
  </h2>

  <?php if (empty($events)): ?>
    <p class="no-events">Keine Events</p>
  <?php else: ?>
    <?php foreach ($events as $event): ?>

      <?php
      $img = null;

      if ($event['source'] === 'rb') {
          $img = '/leipzig/img/RBLogo.png';
      } elseif (!empty($event['image'])) {
          $path = trim($event['image']);
          if (preg_match('#^https?://#i', $path)) {
              $img = $path;
          } elseif (isset($imageBase[$event['source']])) {
              $img = rtrim($imageBase[$event['source']], '/') . '/' . ltrim($path, '/');
          }
      }
      ?>

      <article class="event event-<?= $event['source'] ?>">
        <?php if ($img): ?>
          <img src="<?= htmlspecialchars($img) ?>" loading="lazy" alt="">
        <?php endif; ?>

        <div class="event-info">
          <div class="event-header">
          <?php
          $googleQuery = urlencode($event['title'] . ' Leipzig');
          $googleUrl = "https://www.google.com/search?q={$googleQuery}";
          ?>

          <strong>
            <a href="<?= $googleUrl ?>" target="_blank" rel="noopener">
              <?= htmlspecialchars($event['title']) ?>
            </a>
          </strong>
            <span class="badge badge-<?= $event['source'] ?>">
              <?= strtoupper($event['source']) ?>
            </span>
          </div>

          <!-- <small><?= htmlspecialchars($event['location'] ?? '') ?></small> -->

          <small>
            <?php if (!empty($event['location'])): ?>
              <?= htmlspecialchars($event['location']) ?>
            <?php endif; ?>

            <?php
            if (
                $event['source'] === 'city'
                && !empty($event['startDate'])
                && !empty($event['endDate'])
                && $event['startDate'] !== $event['endDate']
            ):
                $from = (new DateTime($event['startDate']))->format('d.m.');
                $to   = (new DateTime($event['endDate']))->format('d.m.');
            ?>
              <span class="event-range">· <?= $from ?>–<?= $to ?></span>
            <?php endif; ?>
          </small>

        </div>
      </article>

    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php
  $day->modify('+1 day');
endwhile;
?>

</main>

<script>
  window.GASTRO_OVERLAY_EVERY = 5; // später aus config/app.json
</script>
<script src="/leipzig/assets/app.js"></script>

<?php //include __DIR__ . '/partials/ad_overlay.php'; ?>
</body>
</html>