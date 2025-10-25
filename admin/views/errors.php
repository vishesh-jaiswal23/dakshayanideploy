<?php
$errors = [];
if (file_exists(SYSTEM_ERROR_FILE)) {
    $lines = file(SYSTEM_ERROR_FILE, FILE_IGNORE_NEW_LINES);
    if ($lines !== false) {
        $errors = array_slice(array_reverse($lines), 0, 200);
    }
}
?>
<section class="space-y-6">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow">
    <header>
      <h2 class="text-lg font-semibold text-slate-900">Error Monitor</h2>
      <p class="text-sm text-slate-500">Grouped by type and file.</p>
    </header>
    <div class="mt-6 space-y-4">
      <?php if (!$errors): ?>
        <p class="rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-500">No errors recorded.</p>
      <?php else: ?>
        <?php foreach ($errors as $line): ?>
          <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 text-xs font-mono text-slate-600">
            <?= htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
