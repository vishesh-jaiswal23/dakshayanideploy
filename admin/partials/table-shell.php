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
        <template x-for="row in paginatedRows" :key="JSON.stringify(row)">
          <tr class="hover:bg-blue-50/50">
            <template x-for="column in columns" :key="column.key + '-cell'">
              <td class="px-4 py-3 text-slate-600">
                <span x-text="row[column.key] ?? ''"></span>
              </td>
            </template>
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
