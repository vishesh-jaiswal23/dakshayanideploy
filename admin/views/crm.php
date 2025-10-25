<?php
$customers = json_read(DATA_PATH . '/customers.json', []);
$leads = json_read(POTENTIAL_CUSTOMERS_FILE, []);
$referrers = json_read(REFERRERS_FILE, []);
?>
<section class="space-y-6">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow space-y-6">
    <header>
      <h2 class="text-lg font-semibold text-slate-900">Customers</h2>
      <p class="text-sm text-slate-500">Master data with filters, pagination and exports.</p>
    </header>
    <div x-data="tableManager({
      columns: [
        { key: 'name', label: 'Name' },
        { key: 'email', label: 'Email' },
        { key: 'phone', label: 'Phone' },
        { key: 'state', label: 'State' },
        { key: 'created_at', label: 'Created' }
      ],
      rows: <?= json_encode($customers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      filename: 'customers'
    })">
      <?php include __DIR__ . '/../partials/table-shell.php'; ?>
    </div>
  </div>
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow space-y-6">
    <header>
      <h2 class="text-lg font-semibold text-slate-900">Leads Pipeline</h2>
      <p class="text-sm text-slate-500">Potential customers tracked via campaigns.</p>
    </header>
    <div x-data="tableManager({
      columns: [
        { key: 'full_name', label: 'Name' },
        { key: 'email', label: 'Email' },
        { key: 'phone', label: 'Phone' },
        { key: 'status', label: 'Status' },
        { key: 'source', label: 'Source' }
      ],
      rows: <?= json_encode($leads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      filename: 'leads'
    })">
      <?php include __DIR__ . '/../partials/table-shell.php'; ?>
    </div>
  </div>
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow space-y-6">
    <header>
      <h2 class="text-lg font-semibold text-slate-900">Referrer Directory</h2>
      <p class="text-sm text-slate-500">Channel partners and referral incentives.</p>
    </header>
    <div x-data="tableManager({
      columns: [
        { key: 'name', label: 'Name' },
        { key: 'email', label: 'Email' },
        { key: 'phone', label: 'Phone' },
        { key: 'city', label: 'City' },
        { key: 'status', label: 'Status' }
      ],
      rows: <?= json_encode($referrers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      filename: 'referrers'
    })">
      <?php include __DIR__ . '/../partials/table-shell.php'; ?>
    </div>
  </div>
</section>
