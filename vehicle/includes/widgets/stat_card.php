<?php
// Required variables:
// $icon, $iconColor, $value, $label, $link
// Optional: $subStats = [['value' => ..., 'label' => ...], ...]

?>
<div class="col-12 col-sm-6 col-md-4 col-lg-3">
  <div class="card p-3 text-center shadow-sm position-relative h-100">
    <div class="fs-3 text-<?= htmlspecialchars($iconColor) ?>">
      <i class="<?= htmlspecialchars($icon) ?>"></i>
    </div>
    <div class="fw-bold"><?= htmlspecialchars($value) ?></div>
    <div><?= htmlspecialchars($label) ?></div>

    <?php if (!empty($subStats)): ?>
      <hr>
      <div class="d-flex justify-content-center gap-4">
        <?php foreach ($subStats as $stat): ?>
          <div>
            <div class="fw-bold"><?= htmlspecialchars($stat['value']) ?></div>
            <div><?= htmlspecialchars($stat['label']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <a href="<?= htmlspecialchars($link) ?>" class="stretched-link"></a>
  </div>
</div>