<?php
$portalState = json_read(__DIR__ . '/../data/portal-state.json', []);
$tickets = json_read(TICKETS_FILE, []);
$documents = json_read(DOCUMENTS_INDEX_FILE, ['documents' => []]);
?>
<section class="space-y-6">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow">
    <header>
      <h2 class="text-lg font-semibold text-slate-900">Customer Portal Overview</h2>
      <p class="text-sm text-slate-500">Monitor customer-facing modules and login activity.</p>
    </header>
    <dl class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4 text-sm">
      <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
        <dt class="text-slate-500">Active OTP Sessions</dt>
        <dd class="mt-2 text-2xl font-semibold text-slate-900"><?= count($portalState['active_otps'] ?? []); ?></dd>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
        <dt class="text-slate-500">Customer Tickets</dt>
        <dd class="mt-2 text-2xl font-semibold text-slate-900"><?= count(array_filter($tickets, static fn($t) => ($t['channel'] ?? '') === 'customer_portal')); ?></dd>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
        <dt class="text-slate-500">Documents Shared</dt>
        <dd class="mt-2 text-2xl font-semibold text-slate-900"><?= count($documents['documents'] ?? []); ?></dd>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
        <dt class="text-slate-500">Portal Notices</dt>
        <dd class="mt-2 text-2xl font-semibold text-slate-900"><?= count($portalState['announcements'] ?? []); ?></dd>
      </div>
    </dl>
  </div>
</section>
