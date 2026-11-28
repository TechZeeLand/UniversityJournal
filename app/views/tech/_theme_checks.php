<?php /** @var list<array{0:string,1:string,2:float,3:bool}> $checks */ ?>
<table class="tbl theme-checks">
  <thead><tr><th><?= e(t('theme.chk_pair')) ?></th><th><?= e(t('set.opt.light')) ?></th><th><?= e(t('set.opt.dark')) ?></th></tr></thead>
  <tbody>
  <?php
  $by = [];
  foreach ($checks as [$label, $mode, $ratio, $ok]) $by[$label][$mode] = [$ratio, $ok];
  foreach ($by as $label => $modes): ?>
    <tr><td><?= e(t($label)) ?></td>
      <?php foreach (['light', 'dark'] as $m): [$ratio, $ok] = $modes[$m]; ?>
        <td><span class="badge <?= $ok ? '' : 'badge-warn' ?>"><?= icon($ok ? 'circle-check' : 'triangle-alert') ?> <?= e(number_format($ratio, 1)) ?> : 1</span></td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
