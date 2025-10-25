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
$placeholderBlogImage = htmlspecialchars(blog_placeholder_image(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$aiSettingsRaw = ai_settings_get();
$aiDefaults = ai_settings_defaults();
$aiPanelConfig = [
    'api_key' => $aiSettingsRaw['api_key'] ?? '',
    'models' => $aiSettingsRaw['models'] ?? $aiDefaults['models'],
    'last_test_results' => $aiSettingsRaw['last_test_results'] ?? [],
    'defaults' => [
        'api_key' => $aiDefaults['api_key'],
        'models' => $aiDefaults['models'],
    ],
    'masked' => mask_sensitive($aiSettingsRaw['api_key'] ?? ''),
];
?>
<section class="space-y-6" x-data="aiTools()" x-init="init()">
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow" x-data="aiSettingsPanel(<?= json_encode($aiPanelConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>)" x-init="init()">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">AI Settings</h2>
        <p class="text-sm text-slate-500">Configure Gemini credentials for text, image, and audio generation.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3 text-xs">
        <label class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 font-semibold text-slate-600">
          <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" x-model="includeKeyExport" />
          Include key on export
        </label>
        <label class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 font-semibold text-slate-600">
          <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" x-model="includeKeyImport" />
          Import key from file
        </label>
        <div class="flex items-center gap-2">
          <button class="rounded-lg border border-slate-200 px-3 py-1 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="exportSettings" :disabled="exporting">
            <span x-show="!exporting">Export JSON</span>
            <span x-show="exporting" class="flex items-center gap-2"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8"></path></svg>Exporting…</span>
          </button>
          <button class="rounded-lg border border-slate-200 px-3 py-1 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="$refs.importInput.click()" :disabled="importing">
            <span x-show="!importing">Import JSON</span>
            <span x-show="importing" class="flex items-center gap-2"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8"></path></svg>Importing…</span>
          </button>
          <input type="file" accept="application/json" class="hidden" x-ref="importInput" @change="handleImport" />
        </div>
      </div>
    </header>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
      <div class="lg:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Gemini API Key
          <div class="mt-2 flex items-center gap-2">
            <input :type="showKey ? 'text' : 'password'" class="w-full rounded-2xl border border-slate-200 px-4 py-2 text-sm" x-model="form.api_key" autocomplete="off" />
            <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50" @click="toggleKey">
              <span x-text="showKey ? 'Hide' : 'Reveal'"></span>
            </button>
          </div>
          <p class="mt-1 text-xs text-slate-500">Stored securely, masked as <?= htmlspecialchars($aiPanelConfig['masked'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>.</p>
        </label>
      </div>
      <label class="block text-sm font-medium text-slate-700">Text model code
        <input type="text" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-2 text-sm" x-model="form.text_model" />
        <p class="mt-1 text-xs text-slate-500">Used for blog and text generation flows.</p>
      </label>
      <label class="block text-sm font-medium text-slate-700">Image model code
        <input type="text" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-2 text-sm" x-model="form.image_model" />
        <p class="mt-1 text-xs text-slate-500">Used for AI-generated cover images.</p>
      </label>
      <label class="block text-sm font-medium text-slate-700">TTS model code
        <input type="text" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-2 text-sm" x-model="form.tts_model" />
        <p class="mt-1 text-xs text-slate-500">Used for audio narration clips.</p>
      </label>
      <div class="space-y-2">
        <p class="text-sm font-semibold text-slate-700">Test scope</p>
        <div class="flex flex-wrap gap-2">
          <label class="flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">
            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" x-model="testTargets.text" /> Text
          </label>
          <label class="flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">
            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" x-model="testTargets.image" /> Image
          </label>
          <label class="flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">
            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" x-model="testTargets.tts" /> TTS
          </label>
        </div>
      </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
      <button class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500" @click="save" :disabled="saving">
        <svg x-show="saving" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8"></path></svg>
        <span>Save</span>
      </button>
      <button class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="resetDefaults" :disabled="resetting">
        <svg x-show="resetting" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8"></path></svg>
        <span>Reset to Default</span>
      </button>
      <button class="inline-flex items-center gap-2 rounded-lg border border-blue-200 px-4 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50" @click="test" :disabled="testing">
        <svg x-show="testing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8"></path></svg>
        <span>Test Connection</span>
      </button>
    </div>

    <template x-if="testMessage">
      <p class="mt-4 text-sm" :class="testMessageType === 'error' ? 'text-red-600' : 'text-emerald-600'" x-text="testMessage"></p>
    </template>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-show="Object.keys(lastTestResults).length > 0">
      <template x-for="(result, key) in lastTestResults" :key="key">
        <div class="rounded-2xl border p-4" :class="result.status === 'pass' ? 'border-emerald-200 bg-emerald-50/50 text-emerald-700' : 'border-red-200 bg-red-50/50 text-red-700'">
          <p class="text-sm font-semibold uppercase" x-text="key.toUpperCase()"></p>
          <p class="mt-1 text-sm" x-text="result.message || (result.status === 'pass' ? 'Connection successful' : 'Check credentials')"></p>
          <p class="mt-2 text-xs opacity-80" x-text="formatTimestamp(result.tested_at)"></p>
        </div>
      </template>
    </div>
  </div>
  <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">AI Tools</h2>
        <p class="text-sm text-slate-500">Manage connected models and draft new blog stories in minutes.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <nav class="flex rounded-full border border-slate-200 bg-white p-1 text-xs font-semibold">
          <button type="button" class="rounded-full px-4 py-2 transition" :class="tab === 'registry' ? 'bg-blue-600 text-white shadow' : 'text-slate-600'" @click="tab = 'registry'">Model Registry</button>
          <button type="button" class="rounded-full px-4 py-2 transition" :class="tab === 'blog' ? 'bg-blue-600 text-white shadow' : 'text-slate-600'" @click="tab = 'blog'">Generate Blog</button>
        </nav>
        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500" @click="window.location='/admin/management.php'">Manage Keys</button>
      </div>
    </header>

    <div class="mt-6" x-show="tab === 'registry'" x-cloak x-data="tableManager({
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
            <thead class="sticky top-0 bg-slate-50">
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

    <div class="mt-6 space-y-6" x-show="tab === 'blog'" x-cloak>
      <div class="grid gap-6 lg:grid-cols-[2fr,3fr]">
        <div class="space-y-4">
          <label class="block text-sm font-medium text-slate-700">Blog Title
            <input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-2 text-sm" placeholder="Solar policy update for MSMEs" x-model="blog.title" />
          </label>
          <label class="block text-sm font-medium text-slate-700">Keywords (comma separated)
            <input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-2 text-sm" placeholder="solar rooftop, PM Surya Ghar" x-model="blog.keywordsText" />
          </label>
          <label class="block text-sm font-medium text-slate-700">Target word count
            <input type="number" min="300" step="50" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-2 text-sm" x-model.number="blog.word_count" />
          </label>
          <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" x-model="blog.generate_image" />
            Generate cover image with Gemini
          </label>
          <div class="flex flex-wrap gap-3">
            <button class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500" @click="generate()" :disabled="generating">
              <svg x-show="generating" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8"></path></svg>
              <span>Generate Now</span>
            </button>
            <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="resetDraft()" :disabled="generating">Reset</button>
          </div>
          <p class="text-xs text-slate-500">Text model: <span class="font-semibold" x-text="blog.model_used_text || 'gemini-2.5-flash'"></span></p>
        </div>
        <div class="space-y-3">
          <label class="block text-sm font-medium text-slate-700">Blog content (HTML friendly)</label>
          <textarea class="h-72 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm leading-relaxed" x-model="blog.content" placeholder="Generated content will appear here..."></textarea>
          <p class="text-xs text-slate-500">Tip: refine the tone or add call-outs before publishing.</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-[2fr,3fr]">
        <div class="space-y-4">
          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
            <div class="relative h-48 w-full bg-slate-100">
              <template x-if="imageLoading">
                <div class="absolute inset-0 flex items-center justify-center bg-white/80">
                  <svg class="h-6 w-6 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8"></path></svg>
                </div>
              </template>
              <img :src="coverPreview()" alt="Blog cover preview" class="h-full w-full object-cover" />
            </div>
          </div>
          <div class="flex flex-wrap gap-3">
            <button class="rounded-lg border border-blue-200 px-4 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50" @click="regenerateImage()" :disabled="imageLoading || !blog.title">Regenerate Image</button>
            <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="$refs.coverInput.click()" :disabled="!blog.id">Upload Image</button>
            <input type="file" accept="image/png,image/jpeg" x-ref="coverInput" class="hidden" @change="uploadCover" />
          </div>
          <p class="text-xs text-slate-500">Image model: <span class="font-semibold" x-text="blog.model_used_image || 'gemini-2.5-flash-image'"></span></p>
        </div>
        <div class="space-y-4">
          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <dl class="grid grid-cols-1 gap-3 text-sm text-slate-600">
              <div class="flex items-center justify-between">
                <dt class="font-medium">Blog ID</dt>
                <dd x-text="blog.id || 'Draft not saved yet'"></dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="font-medium">Generated by</dt>
                <dd x-text="blog.generated_by || '—'"></dd>
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Status
                  <select class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" x-model="blog.status">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                  </select>
                </label>
              </div>
            </dl>
            <div class="mt-4 flex flex-wrap gap-3">
              <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500" @click="save()" :disabled="saving || generating">Save Draft</button>
              <button class="rounded-lg border border-blue-200 px-4 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50" @click="publish()" :disabled="publishing || !blog.id">Publish</button>
            </div>
            <template x-if="message">
              <p class="mt-3 text-sm" :class="messageType === 'error' ? 'text-red-600' : 'text-emerald-600'" x-text="message"></p>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function aiSettingsPanel(initial) {
      return {
        form: {
          api_key: initial.api_key || '',
          text_model: '',
          image_model: '',
          tts_model: '',
        },
        defaults: initial.defaults || { api_key: '', models: { text: '', image: '', tts: '' } },
        showKey: false,
        saving: false,
        resetting: false,
        testing: false,
        exporting: false,
        importing: false,
        includeKeyExport: false,
        includeKeyImport: false,
        testTargets: { text: true, image: true, tts: true },
        lastTestResults: initial.last_test_results || {},
        testMessage: '',
        testMessageType: 'info',
        lastApplied: null,
        init() {
          this.applyModels(initial.models || this.defaults.models);
          this.lastApplied = this.snapshot();
        },
        applyModels(models) {
          const source = models || {};
          this.form.text_model = source.text || this.defaults.models.text;
          this.form.image_model = source.image || this.defaults.models.image;
          this.form.tts_model = source.tts || this.defaults.models.tts;
        },
        snapshot() {
          return {
            api_key: this.form.api_key,
            models: {
              text: this.form.text_model,
              image: this.form.image_model,
              tts: this.form.tts_model,
            },
          };
        },
        hasUnsavedChanges() {
          if (!this.lastApplied) {
            return true;
          }
          const current = this.snapshot();
          return (
            current.api_key !== this.lastApplied.api_key ||
            current.models.text !== this.lastApplied.models.text ||
            current.models.image !== this.lastApplied.models.image ||
            current.models.tts !== this.lastApplied.models.tts
          );
        },
        token() {
          const meta = document.querySelector('meta[name="csrf-token"]');
          return meta ? meta.content : '';
        },
        toggleKey() {
          this.showKey = !this.showKey;
        },
        validate() {
          if (!this.form.api_key || this.form.api_key.trim().length === 0) {
            this.toast('API key is required.', 'error');
            return false;
          }
          if (!this.form.text_model || !this.form.image_model || !this.form.tts_model) {
            this.toast('Provide all three model codes before saving.', 'error');
            return false;
          }
          return true;
        },
        async save(silent = false) {
          if (!this.validate()) {
            return false;
          }
          this.form.api_key = this.form.api_key.trim();
          this.form.text_model = this.form.text_model.trim();
          this.form.image_model = this.form.image_model.trim();
          this.form.tts_model = this.form.tts_model.trim();
          this.saving = !silent;
          try {
            const response = await fetch('/admin/api.php?action=ai_settings.save', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({
                api_key: this.form.api_key,
                models: {
                  text: this.form.text_model,
                  image: this.form.image_model,
                  tts: this.form.tts_model,
                },
              }),
            });
            const payload = await response.json();
            if (payload.status === 'ok') {
              this.lastApplied = this.snapshot();
              if (payload.settings && payload.settings.last_test_results) {
                this.lastTestResults = payload.settings.last_test_results;
              }
              if (!silent) {
                this.toast('AI settings saved', 'success');
              }
              return true;
            }
            this.toast(payload.message || 'Unable to save AI settings', 'error');
          } catch (error) {
            this.toast('Network error while saving AI settings', 'error');
          } finally {
            this.saving = false;
          }
          return false;
        },
        async resetDefaults() {
          if (!confirm('Reset AI settings to defaults?')) {
            return;
          }
          this.resetting = true;
          try {
            const response = await fetch('/admin/api.php?action=ai_settings.reset_defaults', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({}),
            });
            const payload = await response.json();
            if (payload.status === 'ok') {
              this.form.api_key = this.defaults.api_key;
              this.applyModels(this.defaults.models);
              this.lastApplied = this.snapshot();
              this.lastTestResults = payload.settings?.last_test_results || {};
              this.toast('AI settings restored to defaults', 'success');
            } else {
              this.toast(payload.message || 'Unable to reset AI settings', 'error');
            }
          } catch (error) {
            this.toast('Network error while resetting AI settings', 'error');
          } finally {
            this.resetting = false;
          }
        },
        async test() {
          if (this.hasUnsavedChanges()) {
            this.toast('Save settings before running a connection test.', 'error');
            return;
          }
          const selected = this.selectedTypes();
          if (selected.length === 0) {
            this.toast('Choose at least one model to test.', 'error');
            return;
          }
          this.testing = true;
          this.testMessage = '';
          try {
            const response = await fetch('/admin/api.php?action=ai_settings.test', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({ models: selected }),
            });
            const payload = await response.json();
            if (payload.status === 'ok') {
              this.lastTestResults = Object.assign({}, this.lastTestResults, payload.results || {});
              const failures = Object.values(payload.results || {}).filter((item) => item.status !== 'pass');
              if (failures.length > 0) {
                this.testMessage = 'Connection test completed with issues.';
                this.testMessageType = 'error';
              } else {
                this.testMessage = 'All selected models responded successfully.';
                this.testMessageType = 'success';
              }
              this.toast('AI connection test completed', 'success');
            } else {
              this.testMessage = payload.message || 'Unable to run connection test.';
              this.testMessageType = 'error';
              this.toast(this.testMessage, 'error');
            }
          } catch (error) {
            this.testMessage = 'Network error during connection test.';
            this.testMessageType = 'error';
            this.toast(this.testMessage, 'error');
          } finally {
            this.testing = false;
          }
        },
        selectedTypes() {
          return Object.entries(this.testTargets)
            .filter(([, enabled]) => enabled)
            .map(([key]) => key);
        },
        async exportSettings() {
          this.exporting = true;
          try {
            const query = this.includeKeyExport ? '&include_key=1' : '';
            const response = await fetch('/admin/api.php?action=ai_settings.export' + query, {
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json();
            if (payload.status === 'ok' && payload.export) {
              const json = atob(payload.export);
              const blob = new Blob([json], { type: 'application/json' });
              const url = URL.createObjectURL(blob);
              const link = document.createElement('a');
              link.href = url;
              link.download = 'ai-settings.json';
              link.click();
              URL.revokeObjectURL(url);
              this.toast('AI settings exported', 'success');
            } else {
              this.toast(payload.message || 'Unable to export settings', 'error');
            }
          } catch (error) {
            this.toast('Network error while exporting settings', 'error');
          } finally {
            this.exporting = false;
          }
        },
        async handleImport(event) {
          const file = event.target.files[0];
          event.target.value = '';
          if (!file) {
            return;
          }
          this.importing = true;
          try {
            const text = await file.text();
            const parsed = JSON.parse(text);
            const includeKey = this.includeKeyImport && typeof parsed.api_key === 'string' && parsed.api_key.length > 0;
            const response = await fetch('/admin/api.php?action=ai_settings.import', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({ settings: parsed, include_key: includeKey }),
            });
            const payload = await response.json();
            if (payload.status === 'ok') {
              if (parsed.models) {
                this.applyModels(parsed.models);
              }
              if (includeKey && parsed.api_key) {
                this.form.api_key = parsed.api_key;
              }
              this.lastApplied = this.snapshot();
              this.lastTestResults = payload.settings?.last_test_results || {};
              this.toast('AI settings imported', 'success');
            } else {
              this.toast(payload.message || 'Unable to import settings', 'error');
            }
          } catch (error) {
            this.toast('Invalid or unreadable JSON file.', 'error');
          } finally {
            this.importing = false;
          }
        },
        formatTimestamp(value) {
          if (!value) {
            return 'Not tested yet';
          }
          const date = new Date(value);
          if (Number.isNaN(date.getTime())) {
            return value;
          }
          return date.toLocaleString('en-IN', { hour12: true });
        },
        toast(message, type = 'info') {
          window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },
      };
    }

    function aiTools() {
      return {
        tab: 'registry',
        generating: false,
        saving: false,
        publishing: false,
        imageLoading: false,
        message: '',
        messageType: 'info',
        blog: {
          id: null,
          title: '',
          keywordsText: '',
          word_count: 900,
          generate_image: true,
          content: '',
          cover_image: null,
          status: 'draft',
          generated_by: '',
          model_used_text: 'gemini-2.5-flash',
          model_used_image: 'gemini-2.5-flash-image',
          meta: {},
        },
        init() {
          const params = new URLSearchParams(window.location.search);
          if (params.get('tab') === 'blog') {
            this.tab = 'blog';
          }
          const postId = params.get('post');
          if (postId) {
            this.tab = 'blog';
            this.loadExisting(postId);
          }
        },
        token() {
          const meta = document.querySelector('meta[name="csrf-token"]');
          return meta ? meta.content : '';
        },
        keywordsArray() {
          return this.blog.keywordsText
            .split(',')
            .map((item) => item.trim())
            .filter((item) => item.length > 0);
        },
        coverPreview() {
          const token = this.token();
          const path = this.blog.cover_image || '<?= $placeholderBlogImage; ?>';
          return '/download.php?file=' + encodeURIComponent(path) + '&token=' + encodeURIComponent(token) + '&inline=1';
        },
        resetDraft() {
          this.blog.id = null;
          this.blog.title = '';
          this.blog.keywordsText = '';
          this.blog.word_count = 900;
          this.blog.generate_image = true;
          this.blog.content = '';
          this.blog.cover_image = null;
          this.blog.status = 'draft';
          this.blog.generated_by = '';
          this.blog.model_used_text = 'gemini-2.5-flash';
          this.blog.model_used_image = 'gemini-2.5-flash-image';
          this.blog.meta = {};
        },
        async generate() {
          if (!this.blog.title || this.blog.title.trim().length === 0) {
            this.toast('Please provide a blog title.', 'error');
            return;
          }
          this.generating = true;
          this.message = '';
          try {
            const response = await fetch('/admin/api.php?action=blog_generate', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({
                title: this.blog.title,
                keywords: this.keywordsArray(),
                word_count: this.blog.word_count,
                generate_image: this.blog.generate_image,
              }),
            });
            const payload = await response.json();
            if (payload.status === 'ok') {
              this.applyPost(payload.post);
              this.toast('Blog draft created with Gemini', 'success');
            } else {
              this.toast(payload.message || 'Unable to generate blog', 'error');
            }
          } catch (error) {
            this.toast('Network error while generating blog', 'error');
          } finally {
            this.generating = false;
          }
        },
        async loadExisting(id) {
          try {
            const response = await fetch('/admin/api.php?action=blog_get&id=' + encodeURIComponent(id), {
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json();
            if (payload.status === 'ok') {
              this.applyPost(payload.post);
              this.tab = 'blog';
            } else {
              this.toast(payload.message || 'Unable to load blog details', 'error');
            }
          } catch (error) {
            this.toast('Network error while loading blog', 'error');
          }
        },
        applyPost(post) {
          this.blog.id = post.id;
          this.blog.title = post.title || this.blog.title;
          this.blog.content = post.content || '';
          this.blog.cover_image = post.cover_image || null;
          this.blog.status = post.status || 'draft';
          this.blog.generated_by = post.generated_by || '';
          this.blog.model_used_text = post.model_used_text || this.blog.model_used_text;
          this.blog.model_used_image = post.model_used_image || this.blog.model_used_image;
          this.blog.meta = post.meta || {};
          this.blog.word_count = post.meta?.word_count_target || this.blog.word_count;
          const keywords = post.keywords || [];
          this.blog.keywordsText = Array.isArray(keywords) ? keywords.join(', ') : '';
        },
        async save() {
          if (!this.blog.title || !this.blog.content) {
            this.toast('Title and content are required before saving.', 'error');
            return;
          }
          this.saving = true;
          this.message = '';
          try {
            const response = await fetch('/admin/api.php?action=blog_save', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({
                id: this.blog.id,
                title: this.blog.title,
                content: this.blog.content,
                status: this.blog.status,
                cover_image: this.blog.cover_image,
                keywords: this.keywordsArray(),
                generated_by: this.blog.generated_by,
                model_used_text: this.blog.model_used_text,
                model_used_image: this.blog.model_used_image,
                meta: Object.assign({}, this.blog.meta, { word_count_target: this.blog.word_count }),
              }),
            });
            const payload = await response.json();
            if (payload.status === 'ok') {
              this.applyPost(payload.post);
              this.message = 'Draft saved successfully.';
              this.messageType = 'success';
              this.toast('Blog draft saved', 'success');
            } else {
              this.message = payload.message || 'Unable to save blog draft.';
              this.messageType = 'error';
              this.toast(this.message, 'error');
            }
          } catch (error) {
            this.message = 'Network error while saving draft.';
            this.messageType = 'error';
            this.toast(this.message, 'error');
          } finally {
            this.saving = false;
          }
        },
        async publish() {
          if (!this.blog.id) {
            this.toast('Save the draft before publishing.', 'error');
            return;
          }
          this.publishing = true;
          try {
            await this.save();
            if (this.messageType === 'error') {
              this.publishing = false;
              return;
            }
            const response = await fetch('/admin/api.php?action=blog_publish', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({ id: this.blog.id }),
            });
            const payload = await response.json();
            if (payload.status === 'ok') {
              this.applyPost(payload.post);
              this.blog.status = 'published';
              this.message = 'Blog published successfully.';
              this.messageType = 'success';
              this.toast('Blog published', 'success');
            } else {
              this.toast(payload.message || 'Unable to publish blog', 'error');
            }
          } catch (error) {
            this.toast('Network error while publishing blog', 'error');
          } finally {
            this.publishing = false;
          }
        },
        async regenerateImage() {
          if (!this.blog.title) {
            this.toast('Add a title before regenerating the image.', 'error');
            return;
          }
          this.imageLoading = true;
          try {
            const response = await fetch('/admin/api.php?action=blog_generate_cover', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({
                id: this.blog.id,
                title: this.blog.title,
                keywords: this.keywordsArray(),
              }),
            });
            const payload = await response.json();
            if (payload.status === 'ok') {
              if (payload.image.post) {
                this.applyPost(payload.image.post);
              } else if (payload.image.path) {
                this.blog.cover_image = payload.image.path;
              }
              this.toast('Cover image updated', 'success');
            } else {
              this.toast(payload.message || 'Unable to generate image', 'error');
            }
          } catch (error) {
            this.toast('Network error while generating image', 'error');
          } finally {
            this.imageLoading = false;
          }
        },
        async uploadCover(event) {
          const file = event.target.files[0];
          event.target.value = '';
          if (!file) return;
          if (!['image/png', 'image/jpeg'].includes(file.type)) {
            this.toast('Only JPEG or PNG images are allowed.', 'error');
            return;
          }
          if (file.size > 5 * 1024 * 1024) {
            this.toast('Image must be under 5 MB.', 'error');
            return;
          }
          if (!this.blog.id) {
            this.toast('Save the draft before uploading a cover.', 'error');
            return;
          }
          this.imageLoading = true;
          try {
            const base64 = await this.fileToBase64(file);
            const response = await fetch('/admin/api.php?action=blog_cover_upload', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({ id: this.blog.id, data: base64, mime: file.type }),
            });
            const payload = await response.json();
            if (payload.status === 'ok' && payload.image.post) {
              this.applyPost(payload.image.post);
              this.toast('Cover image uploaded', 'success');
            } else {
              this.toast(payload.message || 'Unable to upload image', 'error');
            }
          } catch (error) {
            this.toast('Network error while uploading image', 'error');
          } finally {
            this.imageLoading = false;
          }
        },
        fileToBase64(file) {
          return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => {
              const result = reader.result;
              if (typeof result === 'string') {
                const base64 = result.split(',').pop() || '';
                resolve(base64);
              } else {
                reject(new Error('Invalid file data.'));
              }
            };
            reader.onerror = () => reject(reader.error || new Error('Unable to read file.'));
            reader.readAsDataURL(file);
          });
        },
        toast(message, type = 'info') {
          window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },
      };
    }
  </script>
</section>
