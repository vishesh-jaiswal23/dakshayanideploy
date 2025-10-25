<?php
$tickets = $VIEW_CONTEXT['metrics']['tickets'] ?? [];
$tasks = $VIEW_CONTEXT['metrics']['tasks'] ?? [];
$customers = $VIEW_CONTEXT['metrics']['customers'] ?? [];
?>
<section class="space-y-6" x-data="analyticsView()" x-init="init(<?= json_encode([
    'tickets' => $tickets,
    'tasks' => $tasks,
    'customers' => $customers,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>)">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow">
    <header class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Operational Analytics</h2>
        <p class="text-sm text-slate-500">Rolling 30-day workload insights.</p>
      </div>
      <button class="rounded-lg border border-slate-200 px-3 py-1 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="exportCsv()">Export CSV</button>
    </header>
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
      <div class="rounded-2xl border border-slate-200 bg-white px-4 py-5 shadow">
        <h3 class="text-sm font-semibold text-slate-500">Ticket Volume</h3>
        <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="metrics.tickets"></p>
        <div class="mt-4" x-ref="ticketsChart"></div>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white px-4 py-5 shadow">
        <h3 class="text-sm font-semibold text-slate-500">Task Load</h3>
        <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="metrics.tasks"></p>
        <div class="mt-4" x-ref="tasksChart"></div>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white px-4 py-5 shadow">
        <h3 class="text-sm font-semibold text-slate-500">Customer Onboarding</h3>
        <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="metrics.customers"></p>
        <div class="mt-4" x-ref="customersChart"></div>
      </div>
    </div>
  </div>
</section>
<script>
  function analyticsView() {
    return {
      raw: {},
      metrics: { tickets: 0, tasks: 0, customers: 0 },
      init(payload) {
        this.raw = payload;
        this.metrics.tickets = payload.tickets.length;
        this.metrics.tasks = payload.tasks.length;
        this.metrics.customers = payload.customers.length;
        this.renderLine(this.$refs.ticketsChart, payload.tickets.map((item) => item.created_at));
        this.renderLine(this.$refs.tasksChart, payload.tasks.map((item) => item.due_on || item.created_at));
        this.renderLine(this.$refs.customersChart, payload.customers.map((item) => item.created_at));
      },
      renderLine(container, dates) {
        if (!container) return;
        const counts = {};
        dates.forEach((iso) => {
          if (!iso) return;
          const day = iso.substring(0, 10);
          counts[day] = (counts[day] || 0) + 1;
        });
        const entries = Object.entries(counts).sort((a, b) => a[0].localeCompare(b[0]));
        if (!entries.length) {
          container.innerHTML = '<p class="text-xs text-slate-400">No data</p>';
          return;
        }
        const width = 200;
        const height = 60;
        const maxValue = Math.max(...entries.map(([, value]) => value));
        const stepX = width / Math.max(entries.length - 1, 1);
        const path = entries.map(([key, value], index) => {
          const x = index * stepX;
          const y = height - (value / maxValue) * height;
          return `${index === 0 ? 'M' : 'L'}${x},${y}`;
        }).join(' ');
        container.innerHTML = `<svg viewBox="0 0 ${width} ${height}" width="100%" height="${height}"><path d="${path}" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round"/><polyline points="${path.replace(/M|L/g, '')}" fill="rgba(37,99,235,0.08)"/></svg>`;
      },
      exportCsv() {
        const rows = [['Date', 'Tickets', 'Tasks', 'Customers']];
        const days = new Set();
        ['tickets', 'tasks', 'customers'].forEach((key) => {
          (this.raw[key] || []).forEach((item) => {
            const day = (item.created_at || item.due_on || '').substring(0, 10);
            if (day) days.add(day);
          });
        });
        const sorted = Array.from(days).sort();
        sorted.forEach((day) => {
          rows.push([
            day,
            (this.raw.tickets || []).filter((item) => (item.created_at || '').startsWith(day)).length,
            (this.raw.tasks || []).filter((item) => ((item.due_on || item.created_at || '')).startsWith(day)).length,
            (this.raw.customers || []).filter((item) => (item.created_at || '').startsWith(day)).length,
          ]);
        });
        const csv = rows.map((row) => row.join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'analytics.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      }
    };
  }
</script>
