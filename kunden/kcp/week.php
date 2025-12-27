<?php
$customer = basename(__DIR__);
require __DIR__ . '/../../core/auth.php';
gr_require_access($customer);

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
// Quellen
// --------------------------------------------------
$cacheBuster = date('Ymd');

$sources = [
    'messe' => 'https://raw.githubusercontent.com/SaschaUherek/eventkalender/refs/heads/main/data/events_messe.json?cb=' . $cacheBuster,
    'arena' => 'https://raw.githubusercontent.com/SaschaUherek/eventkalender/refs/heads/main/data/events_arena.json?cb=' . $cacheBuster,
    'rb' => 'https://raw.githubusercontent.com/SaschaUherek/eventkalender/refs/heads/main/data/events_rb.json?cb=' . $cacheBuster,
    'city' => 'https://raw.githubusercontent.com/SaschaUherek/eventkalender/refs/heads/main/data/events_stadt.json?cb=' . $cacheBuster,
];

$weekdayMapShort = [
    'Mon' => 'Mo',
    'Tue' => 'Di',
    'Wed' => 'Mi',
    'Thu' => 'Do',
    'Fri' => 'Fr',
    'Sat' => 'Sa',
    'Sun' => 'So',
];

// --------------------------------------------------
// Feiertage laden (SN)
// --------------------------------------------------
$years = [(int)$weekStart->format('Y'), (int)$weekEnd->format('Y')];
$years = array_unique($years);

$holidayMap = [];

foreach ($years as $y) {
    $url = "https://raw.githubusercontent.com/SaschaUherek/eventkalender/main/data/holidays_sn_{$y}.json";
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

    foreach ($data['events'] as $event) {
        if (empty($event['startDate'])) continue;

        // City: Events ohne Location ausfiltern
        if ($key === 'city' && empty($event['location'])) {
            continue;
        }

        // City: deduplizieren
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

// --------------------------------------------------
// Woche vorbereiten
// --------------------------------------------------
$weekData = [];
$day = clone $weekStart;

while ($day <= $weekEnd) {
    $dateKey = $day->format('Y-m-d');
    $weekData[$dateKey] = [
        'date' => clone $day,
        'events' => [],
        'load' => 0,
        'holiday' => $holidayMap[$dateKey] ?? null
    ];
    $day->modify('+1 day');
}

// --------------------------------------------------
// Events einordnen + Belastung
// --------------------------------------------------
foreach ($allEvents as $event) {
    $dt = new DateTime($event['startDate']);
    if ($dt < $weekStart || $dt > $weekEnd) continue;

    $key = $dt->format('Y-m-d');
    $weekData[$key]['events'][] = $event;

    switch ($event['source']) {
        case 'rb':
            $weekData[$key]['load'] += 3;
            break;
        case 'messe':
        case 'arena':
            $weekData[$key]['load'] += 2;
            break;
    }
}

// Feiertage addieren
foreach ($weekData as $key => &$dayData) {
    if ($dayData['holiday']) {
        $dayData['load'] += 1;
    }
}
unset($dayData);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<title>Wochenübersicht · Leipzig</title>

<link rel="stylesheet" href="/assets/style.css">
<link rel="stylesheet" href="/assets/week.css">
</head>
<body>

<header class="app-header">
  <div class="header-top">

    <div class="header-brand">
      <a href="index.php?week=<?= $weekOffset ?>" title="Zurück zum Kalender">
        <img src="/img/gr-logo.png" alt="GastroRadar">
      </a>
    </div>

    <div class="header-badges">
      <a href="index.php?week=<?= $weekOffset ?>" class="week-alert week-link" title="Zurück zur Liste">
        ← Kalender
      </a>
    </div>

  </div>

  <nav class="week-nav">
    <?php if ($weekOffset > 0): ?>
      <a href="?week=<?= $weekOffset - 1 ?>" class="nav-btn">‹</a>
    <?php else: ?>
      <span class="nav-btn disabled">‹</span>
    <?php endif; ?>

    <span class="week-label">
      KW <?= $weekStart->format('W') ?>
      · <?= $weekStart->format('d.m.') ?> – <?= $weekEnd->format('d.m.Y') ?>
    </span>

    <a href="?week=<?= $weekOffset + 1 ?>" class="nav-btn">›</a>
  </nav>
</header>

<div class="legend">
  <div><span class="legend-box level-0"></span> ruhig</div>
  <div><span class="legend-box level-1"></span> wenig los</div>
  <div><span class="legend-box level-2"></span> belebt</div>
  <div><span class="legend-box level-3"></span> viel los</div>
  <div><span class="legend-box level-4"></span> sehr voll</div>
</div>

<main class="week-grid">

<?php foreach ($weekData as $d): ?>
  <?php
    $load = $d['load'];
    $level =
        $load === 0 ? 'level-0' :
        ($load <= 2 ? 'level-1' :
        ($load <= 4 ? 'level-2' :
        ($load <= 6 ? 'level-3' : 'level-4')));
  ?>

  <section class="day-box <?= $level ?>">
    <div class="day-head">
      <strong><?= $weekdayMapShort[$d['date']->format('D')] ?></strong>
      <span><?= $d['date']->format('d.m.') ?></span>
    </div>

    <?php if ($d['holiday']): ?>
      <div class="holiday">🎉 <?= htmlspecialchars($d['holiday']) ?></div>
    <?php endif; ?>

    <?php /* 
    <div class="load">
    Belastung: <?= $load ?>
    </div>
    */ ?>

    <ul class="events-mini">
      <?php foreach ($d['events'] as $e): ?>

        <?php
        $title = $e['title'];
        $shortTitle = mb_strlen($title) > 20
            ? mb_substr($title, 0, 20) . '…'
            : $title;
        ?>

        <li title="<?= htmlspecialchars($title) ?>">
          <?= htmlspecialchars($shortTitle) ?>
        </li>

      <?php endforeach; ?>
    </ul>
  </section>

<?php endforeach; ?>

</main>

</body>
</html>