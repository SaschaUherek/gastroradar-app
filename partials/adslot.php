<?php
// ------------------------------
// Free / Pro Schalter
// ------------------------------
$adsEnabled = true; // später pro Kunde steuerbar

if (!$adsEnabled) return;

// ------------------------------
// Ads laden
// ------------------------------
$adsFile = __DIR__ . '/../data/ads.json';
if (!file_exists($adsFile)) return;

$adsData = json_decode(file_get_contents($adsFile), true);
if (empty($adsData['ads'])) return;

// nur aktive Ads
$activeAds = array_values(array_filter($adsData['ads'], fn($a) => !empty($a['active'])));
if (empty($activeAds)) return;

// ------------------------------
// Rotation (pro Tag stabil)
// ------------------------------
$index = random_int(0, count($activeAds) - 1);
$ad = $activeAds[$index];

$adImage = null;

if (!empty($ad['image'])) {
    $img = trim($ad['image']);

    // absolute URL
    if (preg_match('#^https?://#i', $img)) {
        $adImage = $img;
    } else {
        // relativer Pfad → an App-Base hängen
        $adImage = rtrim(APP_BASE_PATH, '/') . '/' . ltrim($img, '/');
    }
}
?>

<!-- <section class="ad-slot" aria-label="Werbung">
  <div class="ad-card">
    <div class="ad-label">Anzeige</div>
    <a class="ad-link" href="<?= htmlspecialchars($ad['url']) ?>" target="_blank" rel="noopener">
      <strong><?= htmlspecialchars($ad['title']) ?></strong>
      <span><?= htmlspecialchars($ad['text']) ?></span>
    </a>
  </div>
</section> -->

<section class="ad-slot" aria-label="Werbung">
  <div class="ad-card <?= !empty($ad['image']) ? 'has-image' : '' ?>">
    <div class="ad-label">Anzeige</div>

    <a class="ad-link" href="<?= htmlspecialchars($ad['url']) ?>" target="_blank" rel="noopener">
    <?php if ($adImage): ?>
    <img
        src="<?= htmlspecialchars($adImage) ?>"
        alt=""
        class="ad-image"
        loading="lazy"
    >
    <?php endif; ?>
      <div class="ad-text">
        <strong><?= htmlspecialchars($ad['title']) ?></strong>
        <span><?= htmlspecialchars($ad['text']) ?></span>
      </div>
    </a>
  </div>
</section>