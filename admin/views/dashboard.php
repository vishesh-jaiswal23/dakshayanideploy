<?php
$metrics = $VIEW_CONTEXT['metrics'] ?? [];
$tickets = $metrics['tickets'] ?? [];
$tasks = $metrics['tasks'] ?? [];
$subsidy = $metrics['subsidy'] ?? [];
$customers = $metrics['customers'] ?? [];
$themeModeSetting = $VIEW_CONTEXT['siteSettings']['Theme']['mode'] ?? 'light';
?>
<section class="space-y-6" x-data="dashboardMetrics()" x-init="init(<?= json_encode([
    'customers' => array_map(static function ($item) {
        return strtotime($item['created_at'] ?? 'now');
    }, $customers),
    'tickets' => array_map(static function ($item) {
        return strtotime($item['created_at'] ?? 'now');
    }, $tickets),
    'tasks' => array_map(static function ($item) {
        return strtotime($item['due_on'] ?? ($item['created_at'] ?? 'now'));
    }, $tasks),
    'subsidy' => array_map(static function ($item) {
        return strtotime($item['submitted_at'] ?? 'now');
    }, $subsidy),
]); ?>)">
  <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
    <div class="rounded-2xl border border-slate-200 bg-white/80 p-5 shadow">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-slate-500">Total Customers</span>
        <span class="text-xs font-semibold text-emerald-600">+<?= $metrics['totals']['customers'] ?? 0; ?></span>
      </div>
      <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= $metrics['totals']['customers'] ?? 0; ?></p>
      <div class="mt-4" x-ref="sparkCustomers"></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white/80 p-5 shadow">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-slate-500">Total Leads</span>
        <span class="text-xs font-semibold text-blue-600">Pipeline</span>
      </div>
      <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= $metrics['totals']['leads'] ?? 0; ?></p>
      <div class="mt-4" x-ref="sparkLeads"></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white/80 p-5 shadow">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-slate-500">New Complaints (7d)</span>
        <span class="text-xs font-semibold text-rose-600">Critical</span>
      </div>
      <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= $metrics['new_complaints'] ?? 0; ?></p>
      <div class="mt-4" x-ref="sparkComplaints"></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white/80 p-5 shadow">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-slate-500">Open Tasks</span>
        <span class="text-xs font-semibold text-amber-600">Action</span>
      </div>
      <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= $metrics['open_tasks'] ?? 0; ?></p>
      <div class="mt-4" x-ref="sparkTasks"></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white/80 p-5 shadow">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-slate-500">PM Surya Ghar (Month)</span>
        <span class="text-xs font-semibold text-indigo-600">Applications</span>
      </div>
      <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= $metrics['subsidy_month'] ?? 0; ?></p>
      <div class="mt-4" x-ref="sparkSubsidy"></div>
    </div>
  </div>
  <div class="grid gap-6 lg:grid-cols-2">
    <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">System Health</h2>
        <span class="text-xs text-slate-500">Automated checks</span>
      </div>
      <dl class="mt-4 grid gap-4 text-sm" x-data="{ integrity: '<?= htmlspecialchars(($VIEW_CONTEXT['context']['integrity']['backups'] ?? 'Pending'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>', pendingAlerts: window.__ADMIN_CONTEXT__.notificationSummary.unread }">
        <div class="flex items-center justify-between">
          <dt>Last backup time</dt>
          <dd class="font-semibold" x-text="integrity"></dd>
        </div>
        <div class="flex items-center justify-between">
          <dt>Disk space remaining</dt>
          <dd class="font-semibold" x-text="diskUsage"></dd>
        </div>
        <div class="flex items-center justify-between">
          <dt>Errors (24h)</dt>
          <dd class="font-semibold text-rose-500" x-text="errors24h"></dd>
        </div>
        <div class="flex items-center justify-between">
          <dt>Pending alerts</dt>
          <dd class="font-semibold" x-text="pendingAlerts"></dd>
        </div>
        <div class="flex items-center justify-between">
          <dt>Records</dt>
          <dd class="font-semibold" x-text="totalRecords"></dd>
        </div>
      </dl>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Activity Log</h2>
        <a href="/admin/index.php?view=analytics" class="text-sm font-semibold text-blue-600 hover:underline">View analytics</a>
      </div>
      <?php $activity = array_slice(json_read(ACTIVITY_LOG_FILE, []), -6); ?>
      <ul class="mt-4 space-y-3 text-sm">
        <?php foreach (array_reverse($activity) as $entry): ?>
          <li class="flex items-start justify-between rounded-2xl border border-slate-100 bg-white/70 px-4 py-3" x-bind:class="$store.theme === 'dark' ? 'border-slate-700 bg-slate-800/60' : ''">
            <div>
              <p class="font-medium text-slate-700" x-bind:class="$store.theme === 'dark' ? 'text-slate-100' : ''"><?= htmlspecialchars($entry['event'] ?? 'activity', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
              <p class="text-xs text-slate-500" x-bind:class="$store.theme === 'dark' ? 'text-slate-400' : ''"><?= htmlspecialchars($entry['description'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
            </div>
            <span class="text-xs text-slate-400" x-bind:class="$store.theme === 'dark' ? 'text-slate-300' : ''"><?= htmlspecialchars($entry['created_at'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>
<script>
  function dashboardMetrics() {
    return {
      data: {},
      diskUsage: 'calculating…',
      errors24h: '0',
      totalRecords: '0',
      init(payload) {
        this.data = payload;
        this.renderSparkline(this.$refs.sparkCustomers, payload.customers);
        this.renderSparkline(this.$refs.sparkLeads, payload.customers);
        this.renderSparkline(this.$refs.sparkComplaints, payload.tickets);
        this.renderSparkline(this.$refs.sparkTasks, payload.tasks);
        this.renderSparkline(this.$refs.sparkSubsidy, payload.subsidy);
        fetch('/admin/api.php?action=system_health', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then((res) => res.json())
          .then((payload) => {
            if (payload.status === 'ok') {
              this.diskUsage = payload.disk;
              this.errors24h = payload.errors;
              this.totalRecords = payload.records;
            }
          });
      },
      renderSparkline(el, list) {
        if (!el) return;
        const points = (list || []).slice(-20).map((value, index) => ({ x: index, y: value || 0 }));
        if (points.length === 0) {
          el.innerHTML = '<div class="text-xs text-slate-400">No data</div>';
          return;
        }
        const minY = Math.min(...points.map((p) => p.y));
        const maxY = Math.max(...points.map((p) => p.y));
        const width = 120;
        const height = 40;
        const scaleY = maxY === minY ? 1 : height / (maxY - minY);
        const scaleX = points.length > 1 ? width / (points.length - 1) : width;
        const path = points
          .map((point, i) => {
            const x = i * scaleX;
            const y = height - (point.y - minY) * scaleY;
            return `${i === 0 ? 'M' : 'L'}${x},${y}`;
          })
          .join(' ');
        el.innerHTML = `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" class="text-blue-500"><path d="${path}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>`;
      }
    };
  }
</script>
