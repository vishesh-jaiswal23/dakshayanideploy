<?php
$models = models_registry_list();
$rows = [];
foreach ($models['models'] ?? [] as $model) {
    $rows[] = [
        'nickname' => $model['nickname'] ?? '',
        'type' => $model['type'] ?? 'Text',
        'model_code' => $model['model_code'] ?? '',
        'status' => ucfirst((string) ($model['status'] ?? 'inactive')),
        'last_tested' => $model['last_tested'] ?? 'Not tested',
    ];
}
?>
<section class="space-y-6">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow">
    <header class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">AI Tools Registry</h2>
        <p class="text-sm text-slate-500">Manage connected LLMs and concierge assistants.</p>
      </div>
      <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500" @click="window.location='/admin/management.php'">Manage Keys</button>
    </header>
    <div class="mt-6" x-data="tableManager({
      columns: [
        { key: 'nickname', label: 'Nickname' },
        { key: 'type', label: 'Type' },
        { key: 'model_code', label: 'Model' },
        { key: 'status', label: 'Status' },
        { key: 'last_tested', label: 'Last Tested' }
      ],
      rows: <?= json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      filename: 'ai-tools'
    })">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
          <template x-for="column in columns" :key="column.key">
            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-medium">
              <span x-text="column.label"></span>
              <input type="search" class="w-32 rounded border border-slate-200 px-2 py-1 text-xs" @input="setFilter(column.key, $event.target.value)" placeholder="Filter" />
            </label>
          </template>
        </div>
        <div class="flex items-center gap-2">
          <button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50" @click="exportCsv()">Export CSV</button>
          <button x-show="lazy" class="rounded-lg bg-emerald-500 px-3 py-1 text-xs font-semibold text-white" @click="loadAll()">Load all rows</button>
        </div>
      </div>
      <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
        <div class="max-h-[28rem] overflow-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 sticky top-0">
              <tr>
                <template x-for="column in columns" :key="column.key">
                  <th class="px-4 py-3 text-left font-semibold text-slate-500" x-text="column.label"></th>
                </template>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <template x-for="row in paginatedRows" :key="row.nickname + row.model_code">
                <tr class="hover:bg-blue-50/50">
                  <td class="px-4 py-3 font-medium text-slate-700" x-text="row.nickname"></td>
                  <td class="px-4 py-3 text-slate-600" x-text="row.type"></td>
                  <td class="px-4 py-3 text-slate-600" x-text="row.model_code"></td>
                  <td class="px-4 py-3" :class="row.status === 'Active' ? 'text-emerald-600 font-semibold' : 'text-slate-500'" x-text="row.status"></td>
                  <td class="px-4 py-3 text-slate-500" x-text="row.last_tested"></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
      <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
        <span>Page <span class="font-semibold" x-text="page"></span> of <span class="font-semibold" x-text="totalPages"></span></span>
        <div class="flex items-center gap-2">
          <button class="rounded-lg border border-slate-200 px-3 py-1 hover:bg-slate-50" @click="prev()">Prev</button>
          <button class="rounded-lg border border-slate-200 px-3 py-1 hover:bg-slate-50" @click="next()">Next</button>
        </div>
      </div>
    </div>
  </div>
</section>
