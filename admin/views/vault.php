<?php
$documentsIndex = json_read(DOCUMENTS_INDEX_FILE, ['documents' => []]);
$documents = $documentsIndex['documents'] ?? [];
?>
<section class="space-y-6">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow space-y-6">
    <header>
      <h2 class="text-lg font-semibold text-slate-900">Document Vault</h2>
      <p class="text-sm text-slate-500">Secure storage for project artefacts.</p>
    </header>
    <div x-data="tableManager({
      columns: [
        { key: 'name', label: 'Name' },
        { key: 'category', label: 'Category' },
        { key: 'tags', label: 'Tags' },
        { key: 'uploaded_at', label: 'Uploaded' },
        { key: 'download_url', label: 'Download' }
      ],
      rows: <?= json_encode(array_map(static function ($doc) {
          $doc['download_url'] = '/download.php?id=' . urlencode((string) ($doc['id'] ?? ''));
          if (is_array($doc['tags'] ?? null)) {
              $doc['tags'] = implode(', ', $doc['tags']);
          }
          return $doc;
      }, $documents), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      filename: 'documents'
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
              <template x-for="row in paginatedRows" :key="row.id">
                <tr class="hover:bg-blue-50/50">
                  <td class="px-4 py-3 text-slate-700 font-medium" x-text="row.name"></td>
                  <td class="px-4 py-3 text-slate-600" x-text="row.category"></td>
                  <td class="px-4 py-3 text-slate-500" x-text="row.tags"></td>
                  <td class="px-4 py-3 text-slate-500" x-text="row.uploaded_at"></td>
                  <td class="px-4 py-3 text-blue-600">
                    <a :href="row.download_url" class="text-sm font-semibold hover:underline">Download</a>
                  </td>
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
