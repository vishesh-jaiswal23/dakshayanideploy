<?php
$warranty = json_read(WARRANTY_AMC_FILE, ['assets' => []]);
$assets = $warranty['assets'] ?? [];
?>
<section class="space-y-6">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow space-y-6">
    <header>
      <h2 class="text-lg font-semibold text-slate-900">Warranty & AMC</h2>
      <p class="text-sm text-slate-500">Asset coverage, renewals, and due reminders.</p>
    </header>
    <div x-data="tableManager({
      columns: [
        { key: 'asset_name', label: 'Asset' },
        { key: 'customer_name', label: 'Customer' },
        { key: 'amc_due_on', label: 'AMC Due' },
        { key: 'warranty_expires_on', label: 'Warranty Expires' },
        { key: 'status', label: 'Status' }
      ],
      rows: <?= json_encode($assets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      filename: 'warranty-amc'
    })">
      <?php include __DIR__ . '/../partials/table-shell.php'; ?>
    </div>
  </div>
</section>
