<?php declare(strict_types=1); ?>
</main>
<?php if (!empty($showNav)): ?>
<nav class="tabbar" aria-label="Navegação principal">
  <?php foreach ($tabs as $key => [$href, $iconName, $label]): ?>
    <a class="tabbar__item<?= $key === 'practice' ? ' tabbar__item--primary' : '' ?>" href="<?= e($href) ?>"
       <?= $key === $activeTab ? 'aria-current="page"' : '' ?>>
      <?php if ($key === 'practice'): ?>
        <span class="tabbar__bubble"><?= icon($iconName) ?></span>
      <?php else: ?>
        <?= icon($iconName) ?>
      <?php endif; ?>
      <span><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<footer class="footer">
  <a href="/privacy.php">Privacidade</a>
  <span aria-hidden="true">·</span>
  <a href="/terms.php">Termos</a>
</footer>
</body>
</html>
