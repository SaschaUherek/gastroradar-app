<?php
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

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

// --------------------------------------------------
// Suchbegriff
// --------------------------------------------------
$query = trim($_GET['q'] ?? '');
$results = [];

// --------------------------------------------------
// Events laden + filtern
// --------------------------------------------------
$seenCityEvents = [];

if ($query !== '') {
    foreach ($sources as $key => $url) {
        $json = @file_get_contents($url);
        if (!$json) continue;

        $data = json_decode($json, true);
        if (!isset($data['events'])) continue;

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

            // Volltext-Suche
            $haystack = implode(' ', [
                $event['title'] ?? '',
                $event['startDate'] ?? '',
                $event['location'] ?? '',
                $event['description'] ?? '',
            ]);

            if (stripos($haystack, $query) === false) {
                continue;
            }

            $event['source'] = $key;
            $results[] = $event;
        }
    }

    // Sortieren nach Datum
    usort($results, fn($a, $b) => strcmp($a['startDate'], $b['startDate']));
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<title>Events durchsuchen · Leipzig</title>

<link rel="stylesheet" href="/leipzig/assets/style.css">
</head>

<body>

<header class="app-header">
  <div class="header-top">

    <div class="header-brand">
      <a href="index.php?week=<?= $weekOffset ?>" title="Zurück zum Kalender">
        <img src="/leipzig/img/gr-logo.png" alt="GastroRadar">
      </a>
    </div>

    <div class="header-badges">
      <a href="index.php?week=<?= $weekOffset ?>" class="week-alert week-link" title="Zurück zur Liste">
        ← Kalender
      </a>
    </div>

  </div>
</header>

<main class="calendar">

<form method="get" class="search-form">
  <input
    type="search"
    name="q"
    placeholder="Event, Ort, Datum …"
    value="<?= htmlspecialchars($query) ?>"
    autofocus
  >
  <input type="hidden" name="week" value="<?= $weekOffset ?>">
  <button type="submit">Suchen</button>
</form>

<?php if ($query !== ''): ?>
  <p class="search-info">
    <?= count($results) ?> Treffer für <strong><?= htmlspecialchars($query) ?></strong>
  </p>
<?php endif; ?>

<?php foreach ($results as $event): ?>

  <?php
  // Bildlogik
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

  // Mehrtägige City-Events
    $dateInfo = '';

    if (!empty($event['startDate'])) {
        $start = new DateTime($event['startDate']);

        // Mehrtägige City-Events
        if (
            $event['source'] === 'city'
            && !empty($event['endDate'])
            && $event['startDate'] !== $event['endDate']
        ) {
            $end = new DateTime($event['endDate']);
            $dateInfo = $start->format('d.m.') . '–' . $end->format('d.m.');
        } else {
            // Eintägige Events
            $dateInfo = $start->format('d.m.Y');
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
        ?>
        <strong>
          <a href="https://www.google.com/search?q=<?= $googleQuery ?>" target="_blank" rel="noopener">
            <?= htmlspecialchars($event['title']) ?>
          </a>
        </strong>

        <span class="badge badge-<?= $event['source'] ?>">
          <?= strtoupper($event['source']) ?>
        </span>
      </div>

      <small>
        <?= htmlspecialchars($event['location'] ?? '') ?>
        <?= $dateInfo ?>
      </small>
    </div>
  </article>

<?php endforeach; ?>

<?php if ($query !== '' && empty($results)): ?>
  <p class="empty">Keine passenden Events gefunden.</p>
<?php endif; ?>

</main>

</body>
</html>
