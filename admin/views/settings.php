<?php
$settings = $VIEW_CONTEXT['siteSettings'] ?? [];
$defaults = default_site_settings();
$sections = array_keys($settings);
?>
<section x-data="settingsManager()" x-init="init()" class="space-y-6">
  <div class="rounded-3xl border border-slate-200 bg-white/90 shadow">
    <div class="border-b border-slate-100 px-6 py-5">
      <h2 class="text-lg font-semibold text-slate-900">Portal Site Settings</h2>
      <p class="text-sm text-slate-500">Manage branding, content defaults, theme and AI assistants.</p>
    </div>
    <div class="flex flex-col gap-6 px-6 py-6 lg:flex-row">
      <nav class="lg:w-60">
        <ul class="space-y-2">
          <template x-for="tab in tabs" :key="tab">
            <li>
              <button type="button" @click="selectTab(tab)" :class="activeTab === tab ? 'bg-blue-600 text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-50'" class="w-full rounded-xl border border-slate-200 px-4 py-2 text-left text-sm font-medium transition">
                <span x-text="tab"></span>
              </button>
            </li>
          </template>
        </ul>
      </nav>
      <form @submit.prevent="save" class="flex-1 space-y-6" x-show="activeTab" x-transition>
        <template x-if="activeTab">
          <div class="rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900" x-text="activeTab"></h3>
            <p class="text-xs text-slate-500" x-text="sectionDescription" class="mt-1"></p>
            <div class="mt-4 space-y-4">
              <template x-for="(value, key) in data[activeTab]" :key="key">
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-slate-700" x-text="formatKey(key)"></label>
                  <template x-if="isLongField(key)">
                    <textarea rows="3" x-model="data[activeTab][key]" class="w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                  </template>
                  <template x-if="!isLongField(key)">
                    <input type="text" x-model="data[activeTab][key]" class="w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" />
                  </template>
                </div>
              </template>
            </div>
            <div class="mt-6 flex items-center justify-between gap-3">
              <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50" :disabled="busy" @click="resetSection">Reset to default</button>
              <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500" :disabled="busy">
                <span x-show="!busy">Save changes</span>
                <span x-show="busy" class="flex items-center gap-2"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>Saving…</span>
              </button>
            </div>
          </div>
        </template>
      </form>
    </div>
  </div>
</section>
<script>
  function settingsManager() {
    return {
      tabs: <?= json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      data: <?= json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      defaults: <?= json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      activeTab: null,
      busy: false,
      toast(message, type = 'success') {
        window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
      },
      init() {
        if (!this.activeTab && this.tabs.length) {
          this.activeTab = this.tabs[0];
        }
      },
      selectTab(tab) {
        this.activeTab = tab;
      },
      formatKey(key) {
        return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
      },
      isLongField(key) {
        return ['system_prompt', 'hero_subtitle', 'cta_text', 'address', 'notes'].includes(key);
      },
      get sectionDescription() {
        const messages = {
          'Global': 'Contact, identity, and primary business details visible across the portal.',
          'Homepage': 'Hero messaging and key call to actions for the landing page.',
          'Blog Defaults': 'Fallback metadata applied to new blog posts.',
          'Case Study Defaults': 'Baseline content for success stories.',
          'Testimonial Defaults': 'Display defaults for testimonials on landing pages.',
          'Theme': 'Portal theming tokens including colours and radiuses.',
          'AI Settings': 'Control AI concierge providers, models, and behaviour.',
          'Complaints': 'Ticket defaults and intake automation.',
          'Warranty & AMC': 'Reminder windows and submission controls.',
          'Document Vault': 'Upload limits and security defaults.',
          'Data Quality': 'Validation rules for structured data.'
        };
        return messages[this.activeTab] || '';
      },
      async save() {
        if (!this.activeTab) return;
        this.busy = true;
        try {
          const response = await fetch('/admin/api.php?action=save_site_settings', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ section: this.activeTab, data: this.data[this.activeTab] })
          });
          const payload = await response.json();
          if (!response.ok || payload.status !== 'ok') {
            throw new Error(payload.message || 'Unable to save settings');
          }
          this.toast('Settings saved successfully');
        } catch (error) {
          console.error(error);
          this.toast(error.message || 'Unexpected error', 'error');
        } finally {
          this.busy = false;
        }
      },
      async resetSection() {
        if (!this.activeTab) return;
        if (!confirm('Reset ' + this.activeTab + ' to default values?')) return;
        this.busy = true;
        try {
          const response = await fetch('/admin/api.php?action=reset_site_settings', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ section: this.activeTab })
          });
          const payload = await response.json();
          if (!response.ok || payload.status !== 'ok') {
            throw new Error(payload.message || 'Unable to reset section');
          }
          this.data[this.activeTab] = JSON.parse(JSON.stringify(this.defaults[this.activeTab]));
          this.toast(this.activeTab + ' reset to defaults');
        } catch (error) {
          console.error(error);
          this.toast(error.message || 'Unexpected error', 'error');
        } finally {
          this.busy = false;
        }
      }
    };
  }
</script>
