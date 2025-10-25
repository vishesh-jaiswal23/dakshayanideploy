<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib.php';

$availableViews = admin_views();
$requestedView = $_GET['view'] ?? 'dashboard';
if (!isset($availableViews[$requestedView])) {
    $requestedView = 'dashboard';
}

$context = admin_portal_bootstrap();
$alerts = $context['alerts'];
$notificationSummary = admin_notification_summary($alerts);
$activeSessions = admin_active_sessions();
$themeMode = $context['site_settings']['Theme']['mode'] ?? 'auto';

$viewPath = __DIR__ . '/views/' . $requestedView . '.php';
if (!file_exists($viewPath)) {
    $viewPath = __DIR__ . '/views/dashboard.php';
}

$PAGE_TITLE = $availableViews[$requestedView]['title'] ?? 'Dashboard Overview';
$CSRF_TOKEN = issue_csrf_token();

?>
<!DOCTYPE html>
<html lang="en" x-data="adminShell()" x-init="init()" x-bind:data-theme="theme">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($PAGE_TITLE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> | Dakshayani Portal</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($CSRF_TOKEN, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.7/dist/cdn.min.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css" rel="stylesheet" />
    <script>
      window.__ADMIN_CONTEXT__ = <?= json_encode([
          'alerts' => $alerts,
          'notificationSummary' => $notificationSummary,
          'themeMode' => $themeMode,
          'view' => $requestedView,
          'integrity' => $context['integrity'],
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
  </head>
  <body class="min-h-screen bg-slate-100 text-slate-800" x-bind:class="theme === 'dark' ? 'bg-slate-900 text-slate-100' : ''">
    <div class="flex min-h-screen">
      <aside class="hidden w-72 shrink-0 flex-col border-r border-slate-200 bg-white/90 px-6 py-6 shadow-lg lg:flex" x-bind:class="theme === 'dark' ? 'bg-slate-950 border-slate-800' : ''">
        <div class="mb-8 flex items-center justify-between">
          <div>
            <span class="block text-sm font-semibold uppercase tracking-wide text-blue-600">Dakshayani</span>
            <span class="block text-lg font-semibold" x-bind:class="theme === 'dark' ? 'text-white' : 'text-slate-900'">Enterprise Portal</span>
          </div>
          <span class="rounded-xl bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700" x-bind:class="theme === 'dark' ? 'bg-blue-500/20 text-blue-200' : ''">Admin</span>
        </div>
        <nav class="space-y-2">
          <?php foreach ($availableViews as $slug => $meta): ?>
            <a href="/admin/index.php?view=<?= urlencode($slug); ?>" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all"
              x-bind:class="currentView === '<?= $slug; ?>' ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30' : theme === 'dark' ? 'text-slate-300 hover:bg-slate-800/60' : 'text-slate-600 hover:bg-slate-100'"
              x-on:click="navigate($event, '<?= $slug; ?>')">
              <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600"
                x-bind:class="currentView === '<?= $slug; ?>' ? 'bg-white/20 text-white' : theme === 'dark' ? 'bg-slate-800 text-slate-200' : 'bg-blue-50 text-blue-600'">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <use href="#icon-<?= htmlspecialchars($meta['icon']); ?>"></use>
                </svg>
              </span>
              <span><?= htmlspecialchars($meta['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
              <template x-if="notifications.unread > 0 && '<?= $slug; ?>' === 'alerts'">
                <span class="ml-auto inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-red-500 px-2 text-xs font-semibold text-white">{{ notifications.unread }}</span>
              </template>
            </a>
          <?php endforeach; ?>
        </nav>
        <div class="mt-auto space-y-4 pt-10 text-xs text-slate-500" x-bind:class="theme === 'dark' ? 'text-slate-400' : ''">
          <p>Last backup: <span x-text="integrity.backups ?? 'Pending'" class="font-medium"></span></p>
          <p>Signed in as <span class="font-semibold text-slate-700" x-bind:class="theme === 'dark' ? 'text-slate-100' : ''"><?= htmlspecialchars($CURRENT_USER['email'] ?? 'admin', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></p>
          <a href="/admin/logout.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 font-medium text-slate-600 transition hover:bg-slate-50"
            x-bind:class="theme === 'dark' ? 'border-slate-700 text-slate-200 hover:bg-slate-800/60' : ''">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Logout
          </a>
        </div>
      </aside>
      <div class="flex min-h-screen flex-1 flex-col">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/80 backdrop-blur lg:border-b-0 lg:bg-transparent"
          x-bind:class="theme === 'dark' ? 'border-slate-800 bg-slate-900/80' : ''">
          <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-4 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div class="flex items-center gap-3">
              <button class="inline-flex lg:hidden" x-on:click="sidebar = !sidebar">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
              </button>
              <div>
                <h1 class="text-xl font-semibold text-slate-900" x-bind:class="theme === 'dark' ? 'text-white' : ''" x-text="pageTitle"></h1>
                <p class="text-sm text-slate-500" x-bind:class="theme === 'dark' ? 'text-slate-400' : ''">Unified command centre</p>
              </div>
            </div>
            <div class="flex flex-1 flex-col gap-3 lg:flex-row lg:items-center lg:justify-end">
              <div class="relative w-full max-w-lg" x-data="searchBox()" x-init="init()">
                <button type="button" class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
                </button>
                <input type="search" x-model="term" x-on:keydown.escape="close()" x-on:input.debounce.300ms="search" x-on:focus="open = true"
                  placeholder="Search customers, tickets, documents, referrers" class="w-full rounded-2xl border border-slate-200 bg-white/80 py-2.5 pl-10 pr-12 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                  x-bind:class="theme === 'dark' ? 'border-slate-700 bg-slate-800 text-slate-200 placeholder:text-slate-400' : ''" />
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[10px] font-semibold uppercase tracking-wide text-slate-400">Ctrl + K</span>
                <div x-show="open" x-transition class="absolute left-0 right-0 top-full mt-2 rounded-2xl border border-slate-200 bg-white shadow-xl" x-bind:class="theme === 'dark' ? 'border-slate-700 bg-slate-900 text-slate-100' : ''">
                  <template x-if="term && hasResults">
                    <div class="max-h-80 overflow-y-auto p-4">
                      <template x-for="group in grouped" :key="group.key">
                        <div class="mb-4 last:mb-0">
                          <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400" x-text="group.label"></h3>
                          <ul class="space-y-2">
                            <template x-for="item in group.items" :key="item.id">
                              <li class="rounded-xl border border-slate-100 px-3 py-2 text-sm hover:border-blue-500 hover:bg-blue-50" x-bind:class="theme === 'dark' ? 'border-slate-700 hover:bg-slate-800' : ''">
                                <a :href="item.href" class="flex flex-col" @click="close()">
                                  <span class="font-medium" x-text="item.title"></span>
                                  <span class="text-xs text-slate-500" x-text="item.meta" x-bind:class="theme === 'dark' ? 'text-slate-400' : ''"></span>
                                </a>
                              </li>
                            </template>
                          </ul>
                        </div>
                      </template>
                    </div>
                  </template>
                  <template x-if="term && !hasResults">
                    <div class="p-6 text-center text-sm text-slate-500" x-bind:class="theme === 'dark' ? 'text-slate-400' : ''">No matching records found.</div>
                  </template>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <button class="relative rounded-full bg-white p-2 shadow hover:bg-blue-50" x-bind:class="theme === 'dark' ? 'bg-slate-800 hover:bg-slate-700' : ''" x-on:click="toggleNotifications()">
                  <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5" />
                    <path d="M9 21h6" />
                  </svg>
                  <template x-if="notifications.unread > 0">
                    <span class="absolute -right-1 -top-1 inline-flex h-5 min-w-[1.2rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white" x-text="notifications.unread"></span>
                  </template>
                </button>
                <div class="relative" x-data="themeToggle()">
                  <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide shadow hover:bg-slate-50"
                    x-on:click="cycle()" x-text="label"
                    x-bind:class="theme === 'dark' ? 'border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700' : ''"></button>
                </div>
              </div>
            </div>
          </div>
        </header>
        <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 pb-10 pt-6 lg:px-8">
          <?php
            $VIEW_CONTEXT = [
                'context' => $context,
                'metrics' => $context['metrics'],
                'alerts' => $alerts,
                'activeSessions' => $activeSessions,
                'siteSettings' => $context['site_settings'],
            ];
            include $viewPath;
          ?>
        </main>
      </div>
    </div>

    <div x-show="notificationPanel" x-transition class="fixed inset-0 z-40 flex items-start justify-end bg-slate-900/40 p-4" x-on:click.self="notificationPanel = false">
      <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl" x-bind:class="theme === 'dark' ? 'border-slate-700 bg-slate-900 text-slate-100' : ''">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4" x-bind:class="theme === 'dark' ? 'border-slate-800' : ''">
          <div>
            <h2 class="text-base font-semibold">Notification Centre</h2>
            <p class="text-xs text-slate-500" x-bind:class="theme === 'dark' ? 'text-slate-400' : ''">Alerts, reminders, AMC notices</p>
          </div>
          <button class="rounded-full p-2 text-slate-400 hover:bg-slate-100" x-bind:class="theme === 'dark' ? 'hover:bg-slate-800' : ''" x-on:click="notificationPanel = false">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <div class="max-h-[24rem] overflow-y-auto px-5 py-4 space-y-4">
          <template x-if="notifications.items.length === 0">
            <p class="text-sm text-slate-500" x-bind:class="theme === 'dark' ? 'text-slate-400' : ''">All caught up! No alerts requiring attention.</p>
          </template>
          <template x-for="item in notifications.items" :key="item.id">
            <div class="rounded-2xl border border-slate-200 p-4" x-bind:class="[item.read ? 'opacity-70' : 'shadow', theme === 'dark' ? 'border-slate-700 bg-slate-800/60' : 'bg-white shadow-sm']">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold" x-text="item.title"></p>
                  <p class="mt-1 text-sm text-slate-500" x-bind:class="theme === 'dark' ? 'text-slate-300' : ''" x-text="item.message"></p>
                  <p class="mt-2 text-xs text-slate-400" x-text="item.time"></p>
                </div>
                <div class="flex flex-col gap-2 text-xs font-semibold">
                  <button class="rounded-lg bg-blue-600 px-3 py-1 text-white shadow hover:bg-blue-500" x-on:click="acknowledge(item.id)">Acknowledge</button>
                  <button class="rounded-lg border border-slate-200 px-3 py-1 text-slate-600 hover:bg-slate-100" x-bind:class="theme === 'dark' ? 'border-slate-600 text-slate-200 hover:bg-slate-700' : ''" x-on:click="dismiss(item.id)">Dismiss</button>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <div x-show="toast.visible" x-transition class="fixed inset-x-0 bottom-6 z-50 flex justify-center px-4">
      <div :class="toast.type === 'success' ? 'bg-emerald-500 text-white' : toast.type === 'error' ? 'bg-red-500 text-white' : 'bg-slate-800 text-white'" class="flex items-center gap-3 rounded-full px-5 py-3 text-sm shadow-xl">
        <span x-text="toast.message"></span>
      </div>
    </div>

    <svg xmlns="http://www.w3.org/2000/svg" class="hidden">
      <symbol id="icon-chart-pie" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="1.8" fill="none" d="M21 12A9 9 0 0012 3v9z"/><path stroke="currentColor" stroke-width="1.8" fill="none" d="M12 12l6.36 6.36A9 9 0 1112 3z"/></symbol>
      <symbol id="icon-sparkles" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="1.8" fill="none" d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5z"/><path stroke="currentColor" stroke-width="1.8" fill="none" d="M19 15l.75 2.25L22 18l-2.25.75L19 21l-.75-2.25L16 18l2.25-.75zM5 6l.5 1.5L7 8l-1.5.5L5 10l-.5-1.5L3 8l1.5-.5z"/></symbol>
      <symbol id="icon-document-text" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="1.8" fill="none" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a2 2 0 01-2-2V5a2 2 0 012-2z"/><path stroke="currentColor" stroke-width="1.8" d="M14 3v4a1 1 0 001 1h4"/><path stroke="currentColor" stroke-width="1.8" d="M8 13h8M8 17h5"/></symbol>
      <symbol id="icon-users" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="1.8" fill="none" d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.8" fill="none"/></symbol>
      <symbol id="icon-ticket" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="1.8" fill="none" d="M4 8V6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 010 4v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2a2 2 0 010-4z"/><path stroke="currentColor" stroke-width="1.8" d="M12 6v12"/></symbol>
      <symbol id="icon-shield-check" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="1.8" fill="none" d="M12 3l7 3v6c0 4.75-3.4 9-7 9s-7-4.25-7-9V6z"/><path stroke="currentColor" stroke-width="1.8" d="M9 12l2 2 4-4"/></symbol>
      <symbol id="icon-archive" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="4" rx="1" stroke="currentColor" stroke-width="1.8" fill="none"/><path stroke="currentColor" stroke-width="1.8" fill="none" d="M5 8h14v9a2 2 0 01-2 2H7a2 2 0 01-2-2z"/><path stroke="currentColor" stroke-width="1.8" d="M9 12h6"/></symbol>
      <symbol id="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" stroke="currentColor" stroke-width="1.8" fill="none"/><path stroke="currentColor" stroke-width="1.8" d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></symbol>
      <symbol id="icon-presentation-chart-line" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="1.8" fill="none" d="M3 4h18M3 4v11a2 2 0 002 2h14a2 2 0 002-2V4"/><path stroke="currentColor" stroke-width="1.8" d="M9 10l2 2 4-4"/></symbol>
      <symbol id="icon-globe-alt" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" fill="none"/><path stroke="currentColor" stroke-width="1.8" d="M3 12h18"/><path stroke="currentColor" stroke-width="1.8" d="M12 3a15 15 0 010 18"/><path stroke="currentColor" stroke-width="1.8" d="M12 3a15 15 0 000 18"/></symbol>
      <symbol id="icon-bell" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="1.8" fill="none" d="M18 16v-5a6 6 0 10-12 0v5l-2 2h16z"/><path stroke="currentColor" stroke-width="1.8" d="M9 20a3 3 0 006 0"/></symbol>
      <symbol id="icon-cog" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="1.8" fill="none" d="M10.325 4.317L9.9 2h4.2l-.425 2.317a6.97 6.97 0 011.95 1.127L17.9 4.9l2.828 2.828-1.544 1.95A6.97 6.97 0 0120.683 11.675L23 12.1v4.2l-2.317-.425a6.97 6.97 0 01-1.127 1.95l1.544 1.544L18.283 22.1l-1.95-1.544a6.97 6.97 0 01-1.95 1.127L14.1 23h-4.2l.425-2.317a6.97 6.97 0 01-1.95-1.127L6.1 21.1 3.272 18.272l1.544-1.95a6.97 6.97 0 01-1.127-1.95L2 14.1V9.9l2.317.425a6.97 6.97 0 011.127-1.95L3.9 6.1 6.728 3.272l1.95 1.544a6.97 6.97 0 011.95-1.127z"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8" fill="none"/></symbol>
      <symbol id="icon-exclamation-triangle" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="1.8" fill="none" d="M12 3l10 18H2z"/><path stroke="currentColor" stroke-width="1.8" d="M12 9v4"/><circle cx="12" cy="17" r="1" fill="currentColor"/></symbol>
    </svg>

    <script>
      function adminShell() {
        return {
          sidebar: false,
          theme: '<?= $themeMode; ?>',
          notifications: Object.assign({ items: window.__ADMIN_CONTEXT__.alerts || [] }, window.__ADMIN_CONTEXT__.notificationSummary),
          notificationPanel: false,
          currentView: window.__ADMIN_CONTEXT__.view,
          pageTitle: '<?= htmlspecialchars($PAGE_TITLE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>',
          toast: { visible: false, message: '', type: 'info' },
          integrity: window.__ADMIN_CONTEXT__.integrity,
          init() {
            const stored = localStorage.getItem('dakshayani-theme');
            if (stored) {
              this.theme = stored;
            } else {
              this.theme = window.__ADMIN_CONTEXT__.themeMode || 'auto';
            }
            window.addEventListener('toast', (event) => {
              if (!event || !event.detail) return;
              this.toastMessage(event.detail.message, event.detail.type || 'info');
            });
            document.addEventListener('keydown', (event) => {
              if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                const input = document.querySelector('[x-data\\=\"searchBox()\"] input[type=search]');
                if (input) {
                  input.focus();
                }
              }
            });
            this.refreshNotifications();
          },
          setTheme(mode) {
            this.theme = mode;
            localStorage.setItem('dakshayani-theme', mode);
            this.toastMessage('Theme updated to ' + mode, 'success');
          },
          toggleNotifications() {
            this.notificationPanel = !this.notificationPanel;
            if (this.notificationPanel) {
              this.refreshNotifications();
            }
          },
          refreshNotifications() {
            fetch('/admin/api.php?action=alerts_list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
              .then((res) => res.json())
              .then((payload) => {
                if (payload.status === 'ok') {
                  this.notifications = payload.summary;
                  this.notifications.items = payload.alerts;
                }
              });
          },
          acknowledge(id) {
            this.alertAction('alerts_acknowledge', id);
          },
          dismiss(id) {
            this.alertAction('alerts_dismiss', id);
          },
          alertAction(action, id) {
            fetch('/admin/api.php?action=' + action, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
              },
              body: JSON.stringify({ id })
            })
              .then((res) => res.json())
              .then((payload) => {
                if (payload.status === 'ok') {
                  this.toastMessage('Notification updated', 'success');
                  this.refreshNotifications();
                } else {
                  this.toastMessage(payload.message || 'Unable to update notification', 'error');
                }
              })
              .catch(() => this.toastMessage('Unable to connect to server', 'error'));
          },
          toastMessage(message, type = 'info') {
            this.toast = { visible: true, message, type };
            setTimeout(() => (this.toast.visible = false), 2200);
          },
          navigate(event, view) {
            if (event) {
              event.preventDefault();
            }
            window.location.href = '/admin/index.php?view=' + view;
          },
        };
      }

      function searchBox() {
        return {
          open: false,
          term: '',
          results: { customers: [], tickets: [], documents: [], referrers: [] },
          init() {
            document.addEventListener('click', (event) => {
              if (!event.target.closest('[x-data="searchBox()"]')) {
                this.open = false;
              }
            });
          },
          get hasResults() {
            return Object.values(this.results).some((items) => items.length > 0);
          },
          get grouped() {
            return [
              { key: 'customers', label: 'Customers', items: this.results.customers.map((item) => ({ id: item.id || item.email, title: item.name || 'Customer', meta: (item.email || '') + ' ' + (item.phone || ''), href: '/admin/index.php?view=crm#customer-' + (item.id || '') })) },
              { key: 'tickets', label: 'Tickets', items: this.results.tickets.map((item) => ({ id: item.id, title: item.title || 'Ticket', meta: (item.status || 'open').toUpperCase(), href: '/admin/index.php?view=tickets#ticket-' + (item.id || '') })) },
              { key: 'documents', label: 'Documents', items: this.results.documents.map((item) => ({ id: item.id || item.name, title: item.name || 'Document', meta: (item.tags || []).join ? item.tags.join(', ') : (item.tags || ''), href: '/admin/index.php?view=vault#doc-' + (item.id || '') })) },
              { key: 'referrers', label: 'Referrers', items: this.results.referrers.map((item) => ({ id: item.id || item.email, title: item.name || 'Referrer', meta: (item.email || '') + ' ' + (item.phone || ''), href: '/admin/index.php?view=crm#referrer-' + (item.id || '') })) },
            ];
          },
          search() {
            if (!this.term) {
              this.results = { customers: [], tickets: [], documents: [], referrers: [] };
              return;
            }
            fetch('/admin/api.php?action=search&q=' + encodeURIComponent(this.term), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
              .then((res) => res.json())
              .then((payload) => {
                if (payload.status === 'ok') {
                  this.results = payload.results;
                  this.open = true;
                }
              });
          },
          close() {
            this.open = false;
          },
        };
      }

      function themeToggle() {
        const storeKey = 'dakshayani-theme';
        const modes = ['light', 'dark', 'auto'];
        return {
          index: 0,
          label: 'Theme',
          init() {
            const stored = localStorage.getItem(storeKey);
            this.index = stored ? modes.indexOf(stored) : 0;
            if (this.index < 0) this.index = 0;
            this.setLabel();
          },
          cycle() {
            this.index = (this.index + 1) % modes.length;
            localStorage.setItem(storeKey, modes[this.index]);
            document.querySelector('html').dataset.theme = modes[this.index];
            window.dispatchEvent(new CustomEvent('theme-change', { detail: { theme: modes[this.index] } }));
            this.setLabel();
          },
          setLabel() {
            this.label = 'Mode: ' + modes[this.index].charAt(0).toUpperCase() + modes[this.index].slice(1);
          },
        };
      }

      function tableManager(config) {
        return {
          columns: config.columns || [],
          originalRows: config.rows || [],
          lazy: (config.rows || []).length > 200,
          rows: (config.rows || []).slice(0, 200),
          page: 1,
          perPage: 10,
          filters: {},
          get totalPages() {
            return Math.max(1, Math.ceil(this.filteredRows.length / this.perPage));
          },
          get filteredRows() {
            const activeFilters = this.filters;
            return this.rows.filter((row) => {
              return Object.entries(activeFilters).every(([key, value]) => {
                if (!value) return true;
                const haystack = String(row[key] ?? '').toLowerCase();
                return haystack.includes(String(value).toLowerCase());
              });
            });
          },
          get paginatedRows() {
            const start = (this.page - 1) * this.perPage;
            return this.filteredRows.slice(start, start + this.perPage);
          },
          setFilter(column, value) {
            this.filters[column] = value;
            this.page = 1;
          },
          next() {
            if (this.page < this.totalPages) {
              this.page += 1;
            }
          },
          prev() {
            if (this.page > 1) {
              this.page -= 1;
            }
          },
          exportCsv() {
            const header = this.columns.map((col) => '"' + col.label.replace(/"/g, '""') + '"').join(',');
            const rows = this.filteredRows.map((row) => this.columns.map((col) => '"' + String(row[col.key] ?? '').replace(/"/g, '""') + '"').join(','));
            const csv = [header, ...rows].join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = (config.filename || 'export') + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
          },
          loadAll() {
            if (!this.lazy) return;
            this.rows = this.originalRows;
            this.lazy = false;
          }
        };
      }
    </script>
  </body>
</html>
