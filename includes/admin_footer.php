<?php declare(strict_types=1); ?>
</main>
<?php if (($admin ?? null) !== null): ?>
<nav class="tabbar admin-tabbar" aria-label="Navegação do painel">
  <?php foreach ($adminTabs as $key => [$href, $iconName, $label]): ?>
    <a class="tabbar__item" href="<?= e($href) ?>" <?= $key === $adminTab ? 'aria-current="page"' : '' ?>>
      <?= icon($iconName) ?>
      <span><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<script type="module" src="<?= e(asset('/assets/js/admin.js')) ?>"></script>
</body>
</html>
