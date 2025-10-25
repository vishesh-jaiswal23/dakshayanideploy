<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$settings = load_site_settings();
$defaults = default_site_settings();
$sections = array_keys($settings);
?>
<!DOCTYPE html>
<html lang="en" x-data="settingsManager()" class="h-full">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Site Settings | Dakshayani Enterprises</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($CSRF_TOKEN, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.7/dist/cdn.min.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css" rel="stylesheet" />
    <script>
      window.__SITE_SETTINGS__ = <?= json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      window.__SITE_DEFAULTS__ = <?= json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      window.__SETTINGS_SECTIONS__ = <?= json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
  </head>
  <body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="min-h-screen flex flex-col">
      <header class="bg-white border-b border-slate-200">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
          <div>
            <h1 class="text-xl font-semibold text-slate-900">Portal Site Settings</h1>
            <p class="text-sm text-slate-500">Manage branding, content defaults, theme, and AI assistants.</p>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-sm text-slate-500">Signed in as <span class="font-medium text-slate-700"><?= htmlspecialchars($CURRENT_USER['email'] ?? 'admin', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></span>
            <a href="/admin/management.php" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Management Layer</a>
            <a href="/admin/logout.php" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Logout</a>
          </div>
        </div>
      </header>
      <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col px-6 py-8">
        <div class="flex flex-1 gap-6" x-init="init()">
          <nav class="w-60 shrink-0 space-y-1">
            <template x-for="tab in tabs" :key="tab">
              <button type="button" @click="selectTab(tab)" :class="activeTab === tab ? 'bg-blue-600 text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-50'" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-left text-sm font-medium transition">
                <span x-text="tab"></span>
              </button>
            </template>
          </nav>
          <section class="flex-1">
            <div class="rounded-2xl bg-white shadow border border-slate-200">
              <div class="border-b border-slate-100 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900" x-text="activeTab"></h2>
                <p class="text-sm text-slate-500" x-text="sectionDescription"></p>
              </div>
              <form @submit.prevent="saveSection" class="space-y-6 px-6 py-6" x-ref="formContainer">
                <template x-for="(value, key) in data[activeTab]" :key="key">
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700" x-text="labelFor(key)"></label>
                    <template x-if="isTextArea(key)">
                      <textarea x-model="data[activeTab][key]" rows="3" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </template>
                    <template x-if="!isTextArea(key)">
                      <input type="text" x-model="data[activeTab][key]" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500" />
                    </template>
                  </div>
                </template>
                <div class="flex items-center justify-between gap-3 pt-2">
                  <button type="button" @click="resetSection" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50" x-bind:disabled="busy" x-bind:class="busy ? 'opacity-50 cursor-not-allowed' : ''">Reset to Default</button>
                  <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500" x-bind:disabled="busy" x-bind:class="busy ? 'opacity-50 cursor-not-allowed' : ''">
                    <span x-show="!busy">Save Changes</span>
                    <span x-show="busy" class="flex items-center gap-2"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>Saving…</span>
                  </button>
                </div>
              </form>
            </div>
          </section>
        </div>
      </main>
    </div>
    <div x-show="toast.visible" x-transition class="fixed inset-x-0 top-5 flex justify-center px-4">
      <div :class="toast.type === 'success' ? 'bg-emerald-500' : 'bg-red-500'" class="flex items-center gap-3 rounded-full px-5 py-3 text-sm font-medium text-white shadow-lg">
        <svg x-show="toast.type === 'success'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path stroke="currentColor" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <svg x-show="toast.type === 'error'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path stroke="currentColor" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        <span x-text="toast.message"></span>
      </div>
    </div>
    <script>
      function settingsManager() {
        return {
          tabs: window.__SETTINGS_SECTIONS__ || [],
          data: JSON.parse(JSON.stringify(window.__SITE_SETTINGS__ || {})),
          defaults: window.__SITE_DEFAULTS__ || {},
          descriptions: {
            'Global': 'Contact, identity, and primary business details visible across the portal.',
            'Homepage': 'Hero messaging and key call to actions for the landing page.',
            'Blog Defaults': 'Fallback metadata applied to new blog posts.',
            'Case Study Defaults': 'Baseline content for new case studies and success stories.',
            'Testimonial Defaults': 'Display defaults for testimonials on landing pages.',
            'Theme': 'Portal theming tokens including colors and radiuses.',
            'AI Settings': 'Control AI concierge providers, models, and behavior.'
          },
          busy: false,
          activeTab: window.__SETTINGS_SECTIONS__ ? window.__SETTINGS_SECTIONS__[0] : null,
          toast: { visible: false, message: '', type: 'success' },
          init() {
            if (!this.activeTab && this.tabs.length) {
              this.activeTab = this.tabs[0];
            }
          },
          selectTab(tab) {
            this.activeTab = tab;
          },
          labelFor(key) {
            return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
          },
          isTextArea(key) {
            return ['system_prompt', 'hero_subtitle', 'cta_text', 'address'].includes(key);
          },
          get sectionDescription() {
            return this.descriptions[this.activeTab] || '';
          },
          showToast(message, type = 'success') {
            this.toast = { visible: true, message, type };
            setTimeout(() => { this.toast.visible = false; }, 2500);
          },
          async saveSection() {
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
                throw new Error(payload.message || 'Failed to save settings');
              }
              this.showToast('Settings saved successfully.');
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Unexpected error while saving.', 'error');
            } finally {
              this.busy = false;
            }
          },
          async resetSection() {
            if (!this.activeTab) return;
            if (!confirm('Reset ' + this.activeTab + ' to defaults?')) {
              return;
            }
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
                throw new Error(payload.message || 'Failed to reset settings');
              }
              this.data[this.activeTab] = JSON.parse(JSON.stringify(this.defaults[this.activeTab]));
              this.showToast(this.activeTab + ' reset to defaults.');
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Unexpected error while resetting.', 'error');
            } finally {
              this.busy = false;
            }
          }
        };
      }
    </script>
  </body>
</html>
