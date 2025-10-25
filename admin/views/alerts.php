<?php
$alerts = $VIEW_CONTEXT['alerts'] ?? [];
?>
<section class="space-y-6">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow">
    <header class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Alert Centre</h2>
        <p class="text-sm text-slate-500">System anomalies, disk warnings, AMC reminders.</p>
      </div>
      <button class="rounded-lg border border-slate-200 px-3 py-1 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="$dispatch('toast', { message: 'Alerts refreshed', type: 'info' })" x-on:click="window.location.reload()">Refresh</button>
    </header>
    <div class="mt-6 space-y-4">
      <?php foreach ($alerts as $alert): ?>
        <article class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
          <header class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($alert['title'] ?? 'Alert', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h3>
              <p class="text-xs text-slate-500"><?= htmlspecialchars($alert['category'] ?? 'general', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
            </div>
            <span class="text-xs text-slate-400"><?= htmlspecialchars($alert['created_at'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
          </header>
          <p class="mt-3 text-sm text-slate-600"><?= htmlspecialchars($alert['message'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
          <div class="mt-4 flex items-center gap-3 text-xs font-semibold">
            <form method="post" action="/admin/api.php?action=alerts_acknowledge" class="inline" @submit.prevent="acknowledge('<?= htmlspecialchars($alert['id'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>')">
              <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-white">Acknowledge</button>
            </form>
            <form method="post" action="/admin/api.php?action=alerts_dismiss" class="inline" @submit.prevent="dismiss('<?= htmlspecialchars($alert['id'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>')">
              <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-600">Dismiss</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$alerts): ?>
        <p class="rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-500">No alerts at the moment.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
