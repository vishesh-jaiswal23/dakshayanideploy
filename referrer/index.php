<?php

declare(strict_types=1);

require_once __DIR__ . '/../server/helpers.php';

ensure_session();
server_bootstrap();

if (($_SESSION['user_role'] ?? null) !== 'referrer') {
    header('Location: /login.php');
    exit;
}

$displayName = $_SESSION['display_name'] ?? 'Referral Partner';
$email = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Referral Partner Portal | Dakshayani Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css" rel="stylesheet" />
  </head>
  <body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="mx-auto flex min-h-screen w-full max-w-4xl flex-col gap-6 px-6 py-12">
      <header class="text-center">
        <h1 class="text-3xl font-semibold text-slate-900">Referral Partner Hub</h1>
        <p class="mt-2 text-sm text-slate-500">Track leads, commissions, and shared resources.</p>
      </header>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Welcome</h2>
        <p class="mt-3 text-sm text-slate-600">
          Hello <?= htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>. Use this space to manage referrals and monitor progress.
        </p>
        <dl class="mt-4 space-y-2 text-sm text-slate-600">
          <div class="flex justify-between gap-3"><dt class="font-medium text-slate-500">Email</dt><dd><?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></dd></div>
          <div class="flex justify-between gap-3"><dt class="font-medium text-slate-500">Role</dt><dd>Referral partner</dd></div>
        </dl>
      </section>

      <form method="post" action="/logout.php" class="mt-auto">
        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-blue-500">
          Sign out
        </button>
      </form>
    </div>
  </body>
</html>
