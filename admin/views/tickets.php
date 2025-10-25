<?php
$tickets = json_read(TICKETS_FILE, []);
$tasks = json_read(DATA_PATH . '/tasks.json', []);
?>
<section class="space-y-6">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow space-y-6">
    <header class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Tickets & Complaints</h2>
        <p class="text-sm text-slate-500">Track lifecycle, SLAs, and escalations.</p>
      </div>
      <a href="/admin/index.php?view=alerts" class="text-sm font-semibold text-blue-600 hover:underline">View alerts</a>
    </header>
    <div x-data="tableManager({
      columns: [
        { key: 'id', label: 'Ticket ID' },
        { key: 'title', label: 'Title' },
        { key: 'customer_name', label: 'Customer' },
        { key: 'status', label: 'Status' },
        { key: 'priority', label: 'Priority' },
        { key: 'created_at', label: 'Created' }
      ],
      rows: <?= json_encode($tickets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      filename: 'tickets'
    })">
      <?php include __DIR__ . '/../partials/table-shell.php'; ?>
    </div>
  </div>
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow space-y-6">
    <header>
      <h2 class="text-lg font-semibold text-slate-900">Task Board</h2>
      <p class="text-sm text-slate-500">Operational tasks aligned to complaints.</p>
    </header>
    <div x-data="tableManager({
      columns: [
        { key: 'title', label: 'Task' },
        { key: 'assignee', label: 'Assignee' },
        { key: 'status', label: 'Status' },
        { key: 'due_on', label: 'Due On' },
        { key: 'ticket_id', label: 'Linked Ticket' }
      ],
      rows: <?= json_encode($tasks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      filename: 'tasks'
    })">
      <?php include __DIR__ . '/../partials/table-shell.php'; ?>
    </div>
  </div>
</section>
