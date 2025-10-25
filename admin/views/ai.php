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
?>
<section class="space-y-6" x-data="aiTools()" x-init="init()">
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

  <div class="mt-8 rounded-3xl border border-slate-200 bg-white/90 p-6 shadow" x-data="autoBlogManager()" x-init="init()" x-cloak>
    <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Auto Blog Automation</h2>
        <p class="text-sm text-slate-500">Configure the daily Gemini-powered blog workflow and trigger manual runs on demand.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <button class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-emerald-300" @click="runNow" :disabled="running">
          <svg x-show="running" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8"></path></svg>
          <span>Run Auto-Blog Now</span>
        </button>
        <span class="text-xs text-slate-500" x-text="statusNote()"></span>
      </div>
    </header>

    <div class="mt-6" x-show="loading">
      <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-500">
        <svg class="h-4 w-4 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8"></path></svg>
        <span>Loading automation settings…</span>
      </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr,3fr]" x-show="!loading">
      <div class="space-y-5">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-slate-700">Automation status</p>
              <p class="text-xs text-slate-500" x-text="settings.enabled ? 'Scheduled daily at ' + settings.time_ist + ' IST' : 'Automation is paused until you enable it.'"></p>
            </div>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
              <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" x-model="settings.enabled" />
              Enabled
            </label>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="text-sm font-medium text-slate-700">Run time (IST)
            <input type="time" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" x-model="settings.time_ist" />
          </label>
          <label class="text-sm font-medium text-slate-700">Target word count
            <input type="number" min="400" max="2000" step="50" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" x-model.number="settings.default_word_count" />
          </label>
        </div>

        <div>
          <p class="text-sm font-medium text-slate-700">Active days</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <template x-for="day in daysOptions" :key="day.value">
              <button type="button" class="rounded-full border px-3 py-1 text-xs font-semibold transition" @click="toggleDay(day.value)" :class="settings.days.includes(day.value) ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-slate-200 text-slate-500 hover:border-slate-300'">
                <span x-text="day.label"></span>
              </button>
            </template>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="text-sm font-medium text-slate-700">Text model
            <input type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" x-model="settings.model_overrides.text" />
          </label>
          <label class="text-sm font-medium text-slate-700">Image model
            <input type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" x-model="settings.model_overrides.image" />
          </label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="text-sm font-medium text-slate-700">Publish status
            <select class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" x-model="settings.publish_status">
              <option value="published">Published</option>
              <option value="draft">Draft</option>
            </select>
          </label>
          <label class="text-sm font-medium text-slate-700">Max daily attempts
            <input type="number" min="1" max="5" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" x-model.number="settings.max_daily_attempts" />
          </label>
        </div>

        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
          <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" x-model="settings.generate_image" />
          Generate cover image with Gemini
        </label>

        <label class="block text-sm font-medium text-slate-700">Topics pool (one per line)
          <textarea class="mt-1 h-32 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Residential rooftop solar in Jharkhand" x-model="topicsText"></textarea>
        </label>

        <div class="flex flex-wrap gap-3">
          <button class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-blue-300" @click="save" :disabled="saving">
            <svg x-show="saving" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8"></path></svg>
            <span>Save settings</span>
          </button>
          <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="reset" :disabled="saving || running">Reset</button>
        </div>
      </div>

      <div class="space-y-5">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
          <h3 class="text-sm font-semibold text-slate-700">Run summary</h3>
          <dl class="mt-3 grid grid-cols-1 gap-2 text-sm text-slate-600">
            <div class="flex items-center justify-between">
              <dt>Last run</dt>
              <dd x-text="formatDate(state.last_run_at)"></dd>
            </div>
            <div class="flex items-center justify-between">
              <dt>Today's attempts</dt>
              <dd x-text="attemptsSummary()"></dd>
            </div>
            <div class="flex items-center justify-between">
              <dt>Publish mode</dt>
              <dd x-text="settings.publish_status === 'published' ? 'Publish immediately' : 'Save as draft'"></dd>
            </div>
          </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-700">Recent runs</h3>
            <button class="text-xs font-semibold text-blue-600 hover:underline" @click="fetchStatus" :disabled="loading">Refresh</button>
          </div>
          <template x-if="runs.length === 0">
            <p class="mt-3 text-sm text-slate-500">No auto blog runs recorded yet.</p>
          </template>
          <ul class="mt-3 space-y-3" x-show="runs.length > 0">
            <template x-for="run in runs" :key="run.id">
              <li class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div class="font-semibold text-slate-700" x-text="run.topic || '—'"></div>
                  <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="run.status === 'success' ? 'bg-emerald-100 text-emerald-600' : (run.status === 'error' ? 'bg-red-100 text-red-600' : 'bg-slate-200 text-slate-600')" x-text="run.status"></span>
                </div>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                  <span x-text="formatDate(run.timestamp)"></span>
                  <template x-if="run.blog_id">
                    <a class="text-blue-600 hover:underline" :href="run.link || ('/blog.php?id=' + encodeURIComponent(run.blog_id))" target="_blank" rel="noopener">View</a>
                  </template>
                </div>
                <template x-if="run.message">
                  <p class="mt-2 text-xs text-slate-500" x-text="run.message"></p>
                </template>
              </li>
            </template>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <script>
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
          if (path.startsWith('download.php')) {
            let url = '/' + path.replace(/^\/+/, '');
            if (url.includes('type=blog_image')) {
              const glue = url.includes('?') ? '&' : '?';
              return url + glue + 'inline=1';
            }
            const glue = url.includes('?') ? '&' : '?';
            return url + glue + 'token=' + encodeURIComponent(token) + '&inline=1';
          }
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

  <script>
    function autoBlogManager() {
      return {
        loading: true,
        saving: false,
        running: false,
        settings: {
          enabled: true,
          time_ist: '06:00',
          days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
          default_word_count: 900,
          generate_image: true,
          model_overrides: { text: 'gemini-2.5-flash', image: 'gemini-2.5-flash-image' },
          max_daily_attempts: 2,
          publish_status: 'published',
        },
        topicsText: '',
        runs: [],
        state: { today_attempts: 0, daily_attempts: {}, last_run_at: null },
        tokenValue: '',
        original: null,
        daysOptions: [
          { value: 'Mon', label: 'Mon' },
          { value: 'Tue', label: 'Tue' },
          { value: 'Wed', label: 'Wed' },
          { value: 'Thu', label: 'Thu' },
          { value: 'Fri', label: 'Fri' },
          { value: 'Sat', label: 'Sat' },
          { value: 'Sun', label: 'Sun' },
        ],
        init() {
          this.fetchStatus();
        },
        token() {
          if (this.tokenValue) {
            return this.tokenValue;
          }
          const meta = document.querySelector('meta[name="csrf-token"]');
          return meta ? meta.content : '';
        },
        async fetchStatus(silent = false) {
          if (!silent) {
            this.loading = true;
          }
          try {
            const response = await fetch('/admin/api.php?action=auto_blog_status', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const payload = await response.json();
            if (payload.status === 'ok') {
              this.applyStatus(payload);
            } else {
              this.toast(payload.message || 'Unable to load auto blog settings', 'error');
            }
          } catch (error) {
            this.toast('Network error while loading auto blog settings', 'error');
          } finally {
            this.loading = false;
          }
        },
        applyStatus(payload) {
          const data = payload.settings || {};
          this.settings.enabled = !!data.enabled;
          this.settings.time_ist = data.time_ist || '06:00';
          this.settings.days = Array.isArray(data.days) ? data.days.slice() : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
          this.settings.default_word_count = data.default_word_count || 900;
          this.settings.generate_image = !!data.generate_image;
          this.settings.model_overrides = {
            text: data.model_overrides?.text || 'gemini-2.5-flash',
            image: data.model_overrides?.image || 'gemini-2.5-flash-image',
          };
          this.settings.max_daily_attempts = data.max_daily_attempts || 2;
          this.settings.publish_status = data.publish_status || 'published';
          this.topicsText = Array.isArray(data.topics_pool) ? data.topics_pool.join('\n') : '';
          this.runs = Array.isArray(payload.runs) ? payload.runs : this.runs;
          this.state = payload.state || { today_attempts: 0, daily_attempts: {}, last_run_at: null };
          this.tokenValue = payload.csrf || this.tokenValue || this.token();
          this.original = {
            settings: JSON.parse(JSON.stringify(this.settings)),
            topicsText: this.topicsText,
          };
        },
        toggleDay(day) {
          const index = this.settings.days.indexOf(day);
          if (index === -1) {
            this.settings.days.push(day);
          } else {
            this.settings.days.splice(index, 1);
          }
        },
        async save() {
          this.saving = true;
          try {
            const topics = this.topicsText
              .split(/\r?\n/)
              .map((item) => item.trim())
              .filter((item) => item.length > 0);
            const payload = {
              enabled: this.settings.enabled,
              time_ist: this.settings.time_ist,
              days: this.settings.days,
              default_word_count: this.settings.default_word_count,
              generate_image: this.settings.generate_image,
              model_overrides: this.settings.model_overrides,
              topics_pool: topics,
              publish_status: this.settings.publish_status,
              max_daily_attempts: this.settings.max_daily_attempts,
            };
            const response = await fetch('/admin/api.php?action=auto_blog_save_settings', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({ settings: payload }),
            });
            const result = await response.json();
            if (result.status === 'ok') {
              this.toast('Auto blog settings saved', 'success');
              await this.fetchStatus(true);
            } else {
              this.toast(result.message || 'Unable to save settings', 'error');
            }
          } catch (error) {
            this.toast('Network error while saving settings', 'error');
          } finally {
            this.saving = false;
          }
        },
        reset() {
          if (!this.original) {
            return;
          }
          this.settings = JSON.parse(JSON.stringify(this.original.settings));
          this.topicsText = this.original.topicsText;
        },
        async runNow() {
          if (this.running) {
            return;
          }
          this.running = true;
          try {
            const response = await fetch('/admin/api.php?action=auto_blog_run_once', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.token(),
              },
              body: JSON.stringify({ respect_enabled: this.settings.enabled }),
            });
            const result = await response.json();
            if (result.status === 'ok') {
              const status = result.result?.status || 'success';
              if (status === 'success') {
                this.toast('Auto blog generated successfully', 'success');
              } else if (status === 'skipped') {
                this.toast('Auto blog run skipped: ' + (result.result?.reason || 'not eligible'), 'info');
              } else {
                this.toast(result.result?.message || 'Auto blog run failed', 'error');
              }
              await this.fetchStatus(true);
            } else {
              this.toast(result.message || 'Unable to trigger auto blog run', 'error');
            }
          } catch (error) {
            this.toast('Network error while triggering auto blog run', 'error');
          } finally {
            this.running = false;
          }
        },
        formatDate(value) {
          if (!value) {
            return '—';
          }
          try {
            const date = new Date(value);
            if (!Number.isFinite(date.getTime())) {
              return value;
            }
            return date.toLocaleString('en-IN', { timeZone: 'Asia/Kolkata', hour12: true });
          } catch (error) {
            return value;
          }
        },
        attemptsSummary() {
          const used = this.state?.today_attempts ?? 0;
          const max = this.settings.max_daily_attempts || 2;
          return used + ' / ' + max;
        },
        statusNote() {
          if (this.loading) {
            return 'Loading…';
          }
          return this.settings.enabled ? 'Automation is active' : 'Automation paused';
        },
        toast(message, type = 'info') {
          window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },
      };
    }
  </script>
</section>
