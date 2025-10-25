<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

?>
<!DOCTYPE html>
<html lang="en" x-data="managementApp()" class="h-full">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Management Layer | Dakshayani Enterprises</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($CSRF_TOKEN, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.7/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  </head>
  <body class="min-h-screen bg-slate-100 text-slate-800" x-init="init()">
    <div class="min-h-screen flex flex-col">
      <header class="bg-white border-b border-slate-200">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
          <div>
            <h1 class="text-xl font-semibold text-slate-900">Management Layer</h1>
            <p class="text-sm text-slate-500">Consolidated analytics, audit, and control surfaces for the Dakshayani operations team.</p>
          </div>
          <div class="flex items-center gap-3">
            <a href="/admin/settings.php" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Site Settings</a>
            <a href="/admin/logout.php" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Logout</a>
          </div>
        </div>
      </header>
      <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col px-6 py-8">
        <div class="flex flex-1 gap-6">
          <nav class="w-56 shrink-0 space-y-2">
            <template x-for="tab in tabs" :key="tab.id">
              <button type="button" @click="selectTab(tab.id)" :class="activeTab === tab.id ? 'bg-blue-600 text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-50'" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-left text-sm font-medium transition">
                <div class="flex items-center justify-between">
                  <span x-text="tab.label"></span>
                  <span x-show="tab.badge" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500" x-text="tab.badge"></span>
                </div>
              </button>
            </template>
          </nav>
          <section class="flex-1 space-y-8 overflow-y-auto pb-12" x-ref="content">
            <template x-if="activeTab === 'analytics'">
              <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                      <h2 class="text-lg font-semibold text-slate-900">KPI &amp; Analytics</h2>
                      <p class="text-sm text-slate-500">Complaint turnaround, FCR rate, conversions, installer productivity, and defect trends.</p>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                      <div>
                        <label class="text-xs font-medium text-slate-500">Start</label>
                        <input type="date" x-model="filters.analytics.start" class="mt-1 w-44 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" />
                      </div>
                      <div>
                        <label class="text-xs font-medium text-slate-500">End</label>
                        <input type="date" x-model="filters.analytics.end" class="mt-1 w-44 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" />
                      </div>
                      <button type="button" @click="loadAnalytics" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500" x-bind:disabled="loading.analytics" x-bind:class="loading.analytics ? 'opacity-50 cursor-not-allowed' : ''">
                        <span x-show="!loading.analytics">Refresh</span>
                        <span x-show="loading.analytics" class="flex items-center gap-2"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>Loading…</span>
                      </button>
                      <button type="button" @click="downloadAnalyticsCsv" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50" x-bind:disabled="loading.analytics">
                        Export CSV
                      </button>
                    </div>
                  </div>
                  <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                      <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Complaint TAT (hrs)</p>
                      <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="formatNumber(analytics.complaint_tat_hours)"></p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                      <p class="text-xs font-medium uppercase tracking-wide text-slate-500">FCR Rate</p>
                      <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="formatPercent(analytics.fcr_rate)"></p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                      <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Lead → Customer</p>
                      <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="formatPercent(analytics.lead_to_customer_rate)"></p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                      <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Defect Rate</p>
                      <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="formatPercent(analytics.defect_rate)"></p>
                    </div>
                  </div>
                  <div class="mt-8">
                    <div id="analytics-chart" class="h-72 w-full"></div>
                  </div>
                  <div class="mt-8 grid gap-6 md:grid-cols-2">
                    <div>
                      <h3 class="text-sm font-semibold text-slate-700">Top technicians</h3>
                      <ul class="mt-3 space-y-2">
                        <template x-if="analytics.installer_productivity.top_technicians.length === 0">
                          <li class="text-sm text-slate-500">No visits recorded in this range.</li>
                        </template>
                        <template x-for="tech in analytics.installer_productivity.top_technicians" :key="tech.technician">
                          <li class="flex items-center justify-between rounded-lg border border-slate-100 bg-white px-3 py-2 text-sm">
                            <span class="font-medium text-slate-700" x-text="tech.technician"></span>
                            <span class="text-slate-500" x-text="tech.visits + ' visits'"></span>
                          </li>
                        </template>
                      </ul>
                    </div>
                    <div>
                      <h3 class="text-sm font-semibold text-slate-700">PMSGY funnel</h3>
                      <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <template x-for="(value, key) in analytics.pmsg_funnel" :key="key">
                          <div class="rounded-lg border border-slate-100 bg-white px-3 py-2">
                            <dt class="text-xs uppercase text-slate-500" x-text="key.replace(/_/g, ' ')"></dt>
                            <dd class="text-lg font-semibold text-slate-800" x-text="value"></dd>
                          </div>
                        </template>
                      </dl>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <template x-if="activeTab === 'audit'">
              <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                  <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                      <h2 class="text-lg font-semibold text-slate-900">Access audit &amp; alerts</h2>
                      <p class="text-sm text-slate-500">Investigate elevated activity, bulk operations, and admin login bursts.</p>
                    </div>
                    <button type="button" @click="loadAudit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50" x-bind:disabled="loading.audit" x-bind:class="loading.audit ? 'opacity-50 cursor-not-allowed' : ''">
                      <span x-show="!loading.audit">Refresh</span>
                      <span x-show="loading.audit" class="flex items-center gap-2"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>Loading…</span>
                    </button>
                  </div>
                  <div class="mt-6 grid gap-4 lg:grid-cols-3">
                    <div class="lg:col-span-1">
                      <h3 class="text-sm font-semibold text-slate-700">Alerts</h3>
                      <template x-if="alerts.length === 0">
                        <p class="mt-3 text-sm text-slate-500">All clear. No open alerts.</p>
                      </template>
                      <div class="mt-3 space-y-3">
                        <template x-for="alert in alerts" :key="alert.id">
                          <div class="rounded-xl border px-4 py-3" :class="alert.severity === 'critical' ? 'border-red-200 bg-red-50/60' : alert.severity === 'high' ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-slate-50'">
                            <div class="flex items-start justify-between gap-3">
                              <div>
                                <p class="text-sm font-semibold text-slate-800" x-text="alert.message"></p>
                                <p class="mt-1 text-xs text-slate-500" x-text="formatTimestamp(alert.detected_at)"></p>
                              </div>
                              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="alert.status === 'closed' ? 'bg-emerald-100 text-emerald-700' : alert.status === 'acknowledged' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700'" x-text="alert.status"></span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                              <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="ackAlert(alert)" x-show="alert.status === 'open'">Acknowledge</button>
                              <button type="button" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="closeAlert(alert)" x-show="alert.status !== 'closed'">Close</button>
                            </div>
                          </div>
                        </template>
                      </div>
                    </div>
                    <div class="lg:col-span-2">
                      <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                          <h3 class="text-sm font-semibold text-slate-700">Recent logins</h3>
                          <ul class="mt-3 space-y-2 text-sm text-slate-600">
                            <template x-for="entry in audit.logins" :key="entry.id">
                              <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                                <span x-text="entry.actor || 'unknown'"></span>
                                <span class="text-xs text-slate-500" x-text="formatTimestamp(entry.timestamp)"></span>
                              </li>
                            </template>
                            <template x-if="audit.logins.length === 0">
                              <li class="text-sm text-slate-500">No logins in the selected range.</li>
                            </template>
                          </ul>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                          <h3 class="text-sm font-semibold text-slate-700">Bulk deletes</h3>
                          <ul class="mt-3 space-y-2 text-sm text-slate-600">
                            <template x-for="entry in audit.deletes" :key="entry.id">
                              <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                                <span x-text="entry.details"></span>
                                <span class="text-xs text-slate-500" x-text="formatTimestamp(entry.timestamp)"></span>
                              </li>
                            </template>
                            <template x-if="audit.deletes.length === 0">
                              <li class="text-sm text-slate-500">No delete actions in the selected range.</li>
                            </template>
                          </ul>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:col-span-2">
                          <h3 class="text-sm font-semibold text-slate-700">Large imports</h3>
                          <table class="mt-3 w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-wide text-slate-500">
                              <tr>
                                <th class="pb-2">Actor
                                <th class="pb-2">Details</th>
                                <th class="pb-2">Timestamp</th>
                              </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-600">
                              <template x-for="entry in audit.imports" :key="entry.id">
                                <tr>
                                  <td class="py-2" x-text="entry.actor || 'system'"></td>
                                  <td class="py-2" x-text="entry.details"></td>
                                  <td class="py-2 text-xs text-slate-500" x-text="formatTimestamp(entry.timestamp)"></td>
                                </tr>
                              </template>
                              <template x-if="audit.imports.length === 0">
                                <tr>
                                  <td colspan="3" class="py-3 text-sm text-slate-500">No large imports detected.</td>
                                </tr>
                              </template>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <template x-if="activeTab === 'logs'">
              <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                  <div class="flex items-center justify-between">
                    <div>
                      <h2 class="text-lg font-semibold text-slate-900">Log retention &amp; export</h2>
                      <p class="text-sm text-slate-500">Archive oversized logs, monitor retention policy and export artefacts for investigations.</p>
                    </div>
                    <button type="button" @click="loadLogs" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50" x-bind:disabled="loading.logs" x-bind:class="loading.logs ? 'opacity-50 cursor-not-allowed' : ''">Refresh</button>
                  </div>
                  <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                      <thead class="text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                          <th class="pb-2">Log</th>
                          <th class="pb-2">Size</th>
                          <th class="pb-2">Updated</th>
                          <th class="pb-2">Latest archive</th>
                          <th class="pb-2"></th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100 text-slate-600">
                        <template x-for="log in logs" :key="log.name">
                          <tr>
                            <td class="py-2 font-medium text-slate-800" x-text="log.name"></td>
                            <td class="py-2" x-text="formatBytes(log.size)"></td>
                            <td class="py-2 text-xs text-slate-500" x-text="formatTimestamp(log.modified_at)"></td>
                            <td class="py-2 text-xs text-slate-500" x-text="log.latest_archive ? log.latest_archive + ' (' + formatTimestamp(log.latest_archive_at) + ')' : '—'"></td>
                            <td class="py-2">
                              <div class="flex gap-2">
                                <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="runArchive(log.name)">Archive now</button>
                                <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="exportLog(log.name, false)">Download</button>
                                <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="exportLog(log.latest_archive, true)" x-bind:disabled="!log.latest_archive">Latest archive</button>
                              </div>
                            </td>
                          </tr>
                        </template>
                        <template x-if="logs.length === 0">
                          <tr>
                            <td colspan="5" class="py-4 text-sm text-slate-500">No logs found.</td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                  <template x-if="archivesCreated.length">
                    <div class="mt-6 rounded-xl bg-emerald-50/70 p-4 text-sm text-emerald-700">
                      <p class="font-semibold">Archives created</p>
                      <ul class="mt-2 space-y-1">
                        <template x-for="archive in archivesCreated" :key="archive.name">
                          <li x-text="archive.name + ' (' + formatBytes(archive.size) + ')'" class="text-xs"></li>
                        </template>
                      </ul>
                    </div>
                  </template>
                </div>
              </div>
            </template>

            <template x-if="activeTab === 'errors'">
              <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                  <div class="flex items-center justify-between">
                    <div>
                      <h2 class="text-lg font-semibold text-slate-900">Error monitoring dashboard</h2>
                      <p class="text-sm text-slate-500">Group recurring stack traces, watch first/last seen, and monitor disk availability.</p>
                    </div>
                    <button type="button" @click="loadErrors" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50" x-bind:disabled="loading.errors" x-bind:class="loading.errors ? 'opacity-50 cursor-not-allowed' : ''">Refresh</button>
                  </div>
                  <div class="mt-6 grid gap-6 lg:grid-cols-3">
                    <div class="lg:col-span-2 overflow-x-auto">
                      <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                          <tr>
                            <th class="pb-2">Signature</th>
                            <th class="pb-2">Occurrences</th>
                            <th class="pb-2">First seen</th>
                            <th class="pb-2">Last seen</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600">
                          <template x-for="group in errorDashboard.groups" :key="group.signature">
                            <tr>
                              <td class="py-2">
                                <p class="font-medium text-slate-800" x-text="group.message"></p>
                                <p class="text-xs text-slate-500" x-text="group.examples.join(' | ')"></p>
                              </td>
                              <td class="py-2" x-text="group.count"></td>
                              <td class="py-2 text-xs text-slate-500" x-text="formatTimestamp(group.first_seen)"></td>
                              <td class="py-2 text-xs text-slate-500" x-text="formatTimestamp(group.last_seen)"></td>
                            </tr>
                          </template>
                          <template x-if="errorDashboard.groups.length === 0">
                            <tr>
                              <td colspan="4" class="py-4 text-sm text-slate-500">No errors logged.</td>
                            </tr>
                          </template>
                        </tbody>
                      </table>
                    </div>
                    <div class="space-y-4">
                      <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-sm font-semibold text-slate-700">Disk health</h3>
                        <template x-if="errorDashboard.disk">
                          <div class="mt-3 text-sm text-slate-600">
                            <p>Total: <span x-text="formatBytes(errorDashboard.disk.total_bytes)"></span></p>
                            <p>Free: <span x-text="formatBytes(errorDashboard.disk.free_bytes)"></span></p>
                            <p class="font-medium text-slate-800">Free %: <span x-text="formatPercent(errorDashboard.disk.percent_free)"></span></p>
                          </div>
                        </template>
                        <template x-if="!errorDashboard.disk">
                          <p class="mt-3 text-sm text-slate-500">Disk statistics unavailable.</p>
                        </template>
                      </div>
                      <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-slate-700">Recent log tail</h3>
                        <ul class="mt-3 space-y-2 text-xs text-slate-500">
                          <template x-for="line in errorDashboard.recent" :key="line">
                            <li class="rounded bg-slate-50 px-3 py-2" x-text="line"></li>
                          </template>
                          <template x-if="errorDashboard.recent.length === 0">
                            <li>No entries.</li>
                          </template>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <template x-if="activeTab === 'shell'">
              <div class="space-y-8">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                  <h2 class="text-lg font-semibold text-slate-900">Admin shell</h2>
                  <p class="text-sm text-slate-500">Users, approvals, tasks and high-level metrics.</p>
                  <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                      <p class="text-xs uppercase text-slate-500">Users</p>
                      <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="cards.users"></p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                      <p class="text-xs uppercase text-slate-500">Open complaints</p>
                      <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="cards.complaints_open"></p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                      <p class="text-xs uppercase text-slate-500">Approvals pending</p>
                      <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="cards.approvals_pending"></p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                      <p class="text-xs uppercase text-slate-500">Active tasks</p>
                      <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="cards.tasks_active"></p>
                    </div>
                  </div>

                  <div class="mt-8 grid gap-6 lg:grid-cols-2">
                    <div class="space-y-4">
                      <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-700">Users</h3>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="loadUsers">Refresh</button>
                      </div>
                      <form class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3" @submit.prevent="createUser">
                        <div class="flex gap-3">
                          <input type="text" placeholder="Name" x-model="newUser.name" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" required />
                          <input type="email" placeholder="Email" x-model="newUser.email" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" required />
                        </div>
                        <div class="flex gap-3">
                          <select x-model="newUser.role" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="admin">Admin</option>
                            <option value="employee">Employee</option>
                            <option value="installer">Installer</option>
                          </select>
                          <input type="password" placeholder="Password" x-model="newUser.password" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" required />
                          <label class="inline-flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" x-model="newUser.force_reset" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />Force reset</label>
                        </div>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500" x-bind:disabled="loading.users">Add user</button>
                      </form>
                      <div class="space-y-2">
                        <template x-for="user in users" :key="user.id">
                          <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <div class="flex items-center justify-between">
                              <div>
                                <p class="font-semibold text-slate-800" x-text="user.name"></p>
                                <p class="text-xs text-slate-500" x-text="user.email"></p>
                              </div>
                              <span class="text-xs font-medium text-slate-500" x-text="user.role"></span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                              <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="toggleUserStatus(user)">Set {{ user.status === 'active' ? 'inactive' : 'active' }}</button>
                              <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="promptReset(user)">Reset password</button>
                              <button type="button" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="removeUser(user)">Delete</button>
                            </div>
                          </div>
                        </template>
                        <template x-if="users.length === 0">
                          <p class="text-sm text-slate-500">No users found.</p>
                        </template>
                      </div>
                    </div>
                    <div class="space-y-6">
                      <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between">
                          <h3 class="text-sm font-semibold text-slate-700">Pending approvals</h3>
                          <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="loadApprovals">Refresh</button>
                        </div>
                        <div class="mt-3 space-y-3">
                          <template x-for="approval in approvals" :key="approval.id">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                              <p class="font-medium text-slate-800" x-text="approval.target_type + ' → ' + approval.target_id"></p>
                              <p class="mt-1 text-xs text-slate-500" x-text="formatTimestamp(approval.requested_at)"></p>
                              <div class="mt-2 text-xs text-slate-600">
                                <template x-for="(change, field) in approval.changes" :key="field">
                                  <p><span class="font-semibold" x-text="field"></span>: <span x-text="JSON.stringify(change)"></span></p>
                                </template>
                              </div>
                              <div class="mt-2 flex gap-2">
                                <button type="button" class="rounded-lg border border-emerald-200 px-3 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50" @click="updateApproval(approval, 'approved')">Approve</button>
                                <button type="button" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="updateApproval(approval, 'rejected')">Reject</button>
                              </div>
                            </div>
                          </template>
                          <template x-if="approvals.length === 0">
                            <p class="text-sm text-slate-500">No pending approvals.</p>
                          </template>
                        </div>
                      </div>
                      <div>
                        <div class="flex items-center justify-between">
                          <h3 class="text-sm font-semibold text-slate-700">Tasks board</h3>
                          <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="loadTasks">Refresh</button>
                        </div>
                        <div class="mt-3 grid gap-4 sm:grid-cols-3">
                          <template x-for="(items, column) in tasksBoard" :key="column">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                              <p class="font-semibold text-slate-700" x-text="column"></p>
                              <div class="mt-3 space-y-2">
                                <template x-for="task in items" :key="task.id">
                                  <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                    <p class="font-medium text-slate-800" x-text="task.title"></p>
                                    <p class="text-xs text-slate-500" x-text="task.assignee || 'Unassigned'"></p>
                                    <div class="mt-2 flex gap-2 text-xs">
                                      <select x-model="task.status" class="rounded border border-slate-200 px-2 py-1" @change="changeTaskStatus(task)">
                                        <option>To Do</option>
                                        <option>In Progress</option>
                                        <option>Done</option>
                                      </select>
                                      <button type="button" class="rounded border border-red-200 px-2 py-1 text-red-600" @click="removeTask(task)">Delete</button>
                                    </div>
                                  </div>
                                </template>
                                <template x-if="items.length === 0">
                                  <p class="text-xs text-slate-500">No tasks.</p>
                                </template>
                              </div>
                            </div>
                          </template>
                        </div>
                        <form class="mt-4 rounded-xl border border-slate-200 bg-white p-4 space-y-3" @submit.prevent="createTask">
                          <input type="text" placeholder="New task title" x-model="newTask.title" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" required />
                          <textarea placeholder="Description" x-model="newTask.description" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                          <div class="flex gap-3">
                            <select x-model="newTask.priority" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                              <option value="medium">Medium</option>
                              <option value="high">High</option>
                              <option value="low">Low</option>
                            </select>
                            <input type="text" placeholder="Assignee" x-model="newTask.assignee" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" />
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500" x-bind:disabled="loading.tasks">Add task</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <template x-if="activeTab === 'activity'">
              <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                      <h2 class="text-lg font-semibold text-slate-900">Activity log</h2>
                      <p class="text-sm text-slate-500">Filter portal activity for investigations.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                      <input type="text" placeholder="Actor" x-model="filters.activity.actor" class="w-40 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" />
                      <input type="text" placeholder="Action" x-model="filters.activity.action" class="w-40 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" />
                      <input type="date" x-model="filters.activity.from" class="w-36 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" />
                      <input type="date" x-model="filters.activity.to" class="w-36 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" />
                      <button type="button" @click="loadActivity" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50" x-bind:disabled="loading.activity">Filter</button>
                    </div>
                  </div>
                  <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                      <thead class="text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                          <th class="pb-2">Timestamp</th>
                          <th class="pb-2">Actor</th>
                          <th class="pb-2">Action</th>
                          <th class="pb-2">Details</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100 text-slate-600">
                        <template x-for="entry in activity" :key="entry.id">
                          <tr>
                            <td class="py-2 text-xs text-slate-500" x-text="formatTimestamp(entry.timestamp)"></td>
                            <td class="py-2" x-text="entry.actor || 'system'"></td>
                            <td class="py-2" x-text="entry.action"></td>
                            <td class="py-2" x-text="entry.details"></td>
                          </tr>
                        </template>
                        <template x-if="activity.length === 0">
                          <tr>
                            <td colspan="4" class="py-4 text-sm text-slate-500">No entries found.</td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </template>
          </section>
        </div>
      </main>
    </div>

    <div x-show="toast.visible" x-transition class="fixed inset-x-0 bottom-6 flex justify-center px-4">
      <div :class="toast.type === 'success' ? 'bg-emerald-500' : 'bg-red-500'" class="flex items-center gap-3 rounded-full px-5 py-3 text-sm font-medium text-white shadow-lg">
        <span x-text="toast.message"></span>
      </div>
    </div>

    <script>
      let analyticsChart = null;
      function renderAnalyticsChart(series) {
        const categories = Object.keys(series || {});
        const data = categories.map((day) => series[day].tickets_closed);
        const tat = categories.map((day) => series[day].avg_tat_hours || 0);
        const fcr = categories.map((day) => series[day].fcr_rate || 0);
        if (analyticsChart) {
          analyticsChart.updateOptions({
            xaxis: { categories },
            series: [
              { name: 'Tickets closed', type: 'column', data },
              { name: 'Avg TAT (hrs)', type: 'line', data: tat },
              { name: 'FCR %', type: 'line', data: fcr },
            ],
          });
          return;
        }
        analyticsChart = new ApexCharts(document.querySelector('#analytics-chart'), {
          chart: { type: 'line', height: 300, toolbar: { show: false } },
          stroke: { width: [0, 3, 3] },
          dataLabels: { enabled: false },
          series: [
            { name: 'Tickets closed', type: 'column', data },
            { name: 'Avg TAT (hrs)', type: 'line', data: tat },
            { name: 'FCR %', type: 'line', data: fcr },
          ],
          xaxis: { categories },
          yaxis: [
            { title: { text: 'Tickets' } },
            { opposite: true, title: { text: 'Hours / Percent' } },
          ],
          colors: ['#1d4ed8', '#0f172a', '#22c55e'],
        });
        analyticsChart.render();
      }

      function managementApp() {
        return {
          tabs: [
            { id: 'analytics', label: 'KPI & Analytics' },
            { id: 'audit', label: 'Access Audit & Alerts' },
            { id: 'logs', label: 'Log Retention' },
            { id: 'errors', label: 'Error Monitoring' },
            { id: 'shell', label: 'Admin Shell' },
            { id: 'activity', label: 'Activity Log' },
          ],
          activeTab: 'analytics',
          analytics: { installer_productivity: { top_technicians: [] }, pmsg_funnel: {} },
          alerts: [],
          audit: { logins: [], deletes: [], imports: [] },
          logs: [],
          archivesCreated: [],
          errorDashboard: { groups: [], recent: [], disk: null },
          users: [],
          newUser: { name: '', email: '', password: '', role: 'employee', force_reset: true },
          approvals: [],
          tasksBoard: { 'To Do': [], 'In Progress': [], 'Done': [] },
          newTask: { title: '', description: '', priority: 'medium', assignee: '' },
          cards: { users: 0, complaints_open: 0, approvals_pending: 0, tasks_active: 0 },
          activity: [],
          filters: {
            analytics: { start: '', end: '' },
            activity: { actor: '', action: '', from: '', to: '' },
          },
          loading: { analytics: false, audit: false, logs: false, errors: false, users: false, approvals: false, tasks: false, activity: false },
          toast: { visible: false, message: '', type: 'success' },
          get csrf() {
            return document.querySelector('meta[name="csrf-token"]').content;
          },
          init() {
            const today = new Date();
            const prior = new Date();
            prior.setDate(prior.getDate() - 29);
            this.filters.analytics.end = today.toISOString().slice(0, 10);
            this.filters.analytics.start = prior.toISOString().slice(0, 10);
            this.loadAll();
          },
          selectTab(tab) {
            this.activeTab = tab;
            if (tab === 'logs' && this.logs.length === 0) this.loadLogs();
            if (tab === 'errors' && this.errorDashboard.groups.length === 0) this.loadErrors();
            if (tab === 'shell' && this.users.length === 0) {
              this.loadUsers();
              this.loadApprovals();
              this.loadTasks();
              this.loadCards();
            }
            if (tab === 'activity' && this.activity.length === 0) this.loadActivity();
          },
          loadAll() {
            this.loadAnalytics();
            this.loadAudit();
            this.loadLogs();
            this.loadErrors();
            this.loadUsers();
            this.loadApprovals();
            this.loadTasks();
            this.loadCards();
            this.loadActivity();
          },
          async loadAnalytics() {
            this.loading.analytics = true;
            try {
              const params = new URLSearchParams();
              if (this.filters.analytics.start) params.set('start', this.filters.analytics.start);
              if (this.filters.analytics.end) params.set('end', this.filters.analytics.end);
              const response = await fetch('/admin/api.php?action=analytics_dashboard&' + params.toString());
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to load analytics');
              this.analytics = payload.analytics;
              renderAnalyticsChart(this.analytics.timeseries || {});
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to load analytics', 'error');
            } finally {
              this.loading.analytics = false;
            }
          },
          async downloadAnalyticsCsv() {
            try {
              const params = new URLSearchParams();
              if (this.filters.analytics.start) params.set('start', this.filters.analytics.start);
              if (this.filters.analytics.end) params.set('end', this.filters.analytics.end);
              const response = await fetch('/admin/api.php?action=analytics_export&' + params.toString());
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to export analytics');
              const blob = this.base64ToBlob(payload.csv, 'text/csv');
              this.downloadBlob(blob, 'analytics.csv');
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to export analytics', 'error');
            }
          },
          async loadAudit() {
            this.loading.audit = true;
            try {
              const params = new URLSearchParams();
              if (this.filters.analytics.start) params.set('start', this.filters.analytics.start);
              if (this.filters.analytics.end) params.set('end', this.filters.analytics.end);
              const response = await fetch('/admin/api.php?action=audit_overview&' + params.toString());
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to load audit');
              this.audit = payload.audit;
              this.alerts = payload.audit.alerts || [];
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to load audit', 'error');
            } finally {
              this.loading.audit = false;
            }
          },
          async ackAlert(alert) {
            try {
              const response = await fetch('/admin/api.php?action=alerts_ack', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify({ id: alert.id }),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to acknowledge alert');
              this.loadAudit();
              this.showToast('Alert acknowledged');
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to acknowledge alert', 'error');
            }
          },
          async closeAlert(alert) {
            try {
              const response = await fetch('/admin/api.php?action=alerts_close', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify({ id: alert.id }),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to close alert');
              this.loadAudit();
              this.showToast('Alert closed');
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to close alert', 'error');
            }
          },
          async loadLogs() {
            this.loading.logs = true;
            try {
              const response = await fetch('/admin/api.php?action=logs_status');
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to load logs');
              this.logs = payload.logs.logs;
              this.archivesCreated = payload.logs.archives_created || [];
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to load logs', 'error');
            } finally {
              this.loading.logs = false;
            }
          },
          async runArchive(name) {
            try {
              const response = await fetch('/admin/api.php?action=logs_archive', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify({ name }),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to archive log');
              this.showToast('Archive created');
              this.loadLogs();
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to archive log', 'error');
            }
          },
          async exportLog(name, archive) {
            if (!name) return;
            try {
              const params = new URLSearchParams({ name, archive: archive ? '1' : '0' });
              const response = await fetch('/admin/api.php?action=logs_export&' + params.toString());
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to export log');
              const blob = this.base64ToBlob(payload.log, 'text/plain');
              this.downloadBlob(blob, name.replace(/[^A-Za-z0-9._-]/g, '_'));
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to download log', 'error');
            }
          },
          async loadErrors() {
            this.loading.errors = true;
            try {
              const response = await fetch('/admin/api.php?action=error_monitor');
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to load error dashboard');
              this.errorDashboard = payload.dashboard;
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to load error dashboard', 'error');
            } finally {
              this.loading.errors = false;
            }
          },
          async loadUsers() {
            this.loading.users = true;
            try {
              const response = await fetch('/admin/api.php?action=users_list');
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to load users');
              this.users = payload.users;
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to load users', 'error');
            } finally {
              this.loading.users = false;
            }
          },
          async createUser() {
            try {
              const response = await fetch('/admin/api.php?action=user_create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify(this.newUser),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to create user');
              this.showToast('User created');
              this.newUser = { name: '', email: '', password: '', role: 'employee', force_reset: true };
              this.loadUsers();
              this.loadCards();
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to create user', 'error');
            }
          },
          async toggleUserStatus(user) {
            try {
              const response = await fetch('/admin/api.php?action=user_update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify({ id: user.id, status: user.status === 'active' ? 'inactive' : 'active' }),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to update user');
              this.showToast('User updated');
              this.loadUsers();
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to update user', 'error');
            }
          },
          async promptReset(user) {
            const password = window.prompt('Enter a temporary password for ' + (user.email || user.id));
            if (!password) return;
            try {
              const response = await fetch('/admin/api.php?action=user_reset_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify({ id: user.id, password, force_reset: true }),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to reset password');
              this.showToast('Password reset');
              this.loadUsers();
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to reset password', 'error');
            }
          },
          async removeUser(user) {
            if (!confirm('Delete user ' + (user.email || user.id) + '?')) return;
            try {
              const response = await fetch('/admin/api.php?action=user_delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify({ id: user.id }),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to delete user');
              this.showToast('User deleted');
              this.loadUsers();
              this.loadCards();
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to delete user', 'error');
            }
          },
          async loadApprovals() {
            this.loading.approvals = true;
            try {
              const response = await fetch('/admin/api.php?action=approvals_list');
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to load approvals');
              this.approvals = payload.approvals;
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to load approvals', 'error');
            } finally {
              this.loading.approvals = false;
            }
          },
          async updateApproval(approval, status) {
            const note = status === 'rejected' ? window.prompt('Reason for rejection?') : null;
            try {
              const response = await fetch('/admin/api.php?action=approval_update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify({ id: approval.id, status, note }),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to update approval');
              this.showToast('Approval ' + status);
              this.loadApprovals();
              this.loadCards();
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to update approval', 'error');
            }
          },
          async loadTasks() {
            this.loading.tasks = true;
            try {
              const response = await fetch('/admin/api.php?action=tasks_board');
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to load tasks');
              this.tasksBoard = payload.board;
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to load tasks', 'error');
            } finally {
              this.loading.tasks = false;
            }
          },
          async createTask() {
            try {
              const response = await fetch('/admin/api.php?action=task_create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify(this.newTask),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to create task');
              this.showToast('Task created');
              this.newTask = { title: '', description: '', priority: 'medium', assignee: '' };
              this.loadTasks();
              this.loadCards();
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to create task', 'error');
            }
          },
          async changeTaskStatus(task) {
            try {
              const response = await fetch('/admin/api.php?action=task_update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify({ id: task.id, status: task.status }),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to update task');
              this.showToast('Task updated');
              this.loadTasks();
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to update task', 'error');
            }
          },
          async removeTask(task) {
            if (!confirm('Delete this task?')) return;
            try {
              const response = await fetch('/admin/api.php?action=task_delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify({ id: task.id }),
              });
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to delete task');
              this.showToast('Task removed');
              this.loadTasks();
              this.loadCards();
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to delete task', 'error');
            }
          },
          async loadCards() {
            try {
              const response = await fetch('/admin/api.php?action=dashboard_cards');
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to load dashboard cards');
              this.cards = payload.cards;
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to load dashboard cards', 'error');
            }
          },
          async loadActivity() {
            this.loading.activity = true;
            try {
              const params = new URLSearchParams();
              if (this.filters.activity.actor) params.set('actor', this.filters.activity.actor);
              if (this.filters.activity.action) params.set('action', this.filters.activity.action);
              if (this.filters.activity.from) params.set('from', this.filters.activity.from);
              if (this.filters.activity.to) params.set('to', this.filters.activity.to);
              const response = await fetch('/admin/api.php?action=activity_log&' + params.toString());
              const payload = await response.json();
              if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'Unable to load activity log');
              this.activity = payload.entries;
            } catch (error) {
              console.error(error);
              this.showToast(error.message || 'Failed to load activity log', 'error');
            } finally {
              this.loading.activity = false;
            }
          },
          formatNumber(value) {
            if (value === null || value === undefined) return '—';
            return Number(value).toFixed(2);
          },
          formatPercent(value) {
            if (value === null || value === undefined) return '—';
            return Number(value).toFixed(1) + '%';
          },
          formatBytes(bytes) {
            if (bytes === null || bytes === undefined) return '—';
            const units = ['B', 'KB', 'MB', 'GB'];
            let index = 0;
            let size = bytes;
            while (size >= 1024 && index < units.length - 1) {
              size /= 1024;
              index++;
            }
            return size.toFixed(2) + ' ' + units[index];
          },
          formatTimestamp(timestamp) {
            if (!timestamp) return '—';
            try {
              return new Date(timestamp).toLocaleString();
            } catch (_) {
              return timestamp;
            }
          },
          base64ToBlob(data, type) {
            const binary = atob(data);
            const array = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
              array[i] = binary.charCodeAt(i);
            }
            return new Blob([array], { type });
          },
          downloadBlob(blob, filename) {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.click();
            URL.revokeObjectURL(url);
          },
          showToast(message, type = 'success') {
            this.toast = { visible: true, message, type };
            setTimeout(() => { this.toast.visible = false; }, 3000);
          },
        };
      }
    </script>
  </body>
</html>
