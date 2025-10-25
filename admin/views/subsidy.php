<?php
$subsidy = json_read(SUBSIDY_TRACKER_FILE, []);
?>
<section class="space-y-6">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow space-y-6">
    <header>
      <h2 class="text-lg font-semibold text-slate-900">PM Surya Ghar Tracker</h2>
      <p class="text-sm text-slate-500">Application status, disbursement windows, and alerts.</p>
    </header>
    <div x-data="tableManager({
      columns: [
        { key: 'application_no', label: 'Application #' },
        { key: 'customer_name', label: 'Customer' },
        { key: 'status', label: 'Status' },
        { key: 'submitted_at', label: 'Submitted' },
        { key: 'last_update', label: 'Last Update' }
      ],
      rows: <?= json_encode($subsidy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      filename: 'subsidy'
    })">
      <?php include __DIR__ . '/../partials/table-shell.php'; ?>
    </div>
  </div>
</section>
