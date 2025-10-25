<?php

declare(strict_types=1);

require_once __DIR__ . '/../server/helpers.php';
require_once __DIR__ . '/../server/modules.php';

ensure_session();
server_bootstrap();

$customerId = $_SESSION['portal_customer_id'] ?? null;
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'request_otp') {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        if ($identifier === '') {
            $error = 'Please enter your registered email address or phone number.';
        } else {
            $customer = portal_customer_find($identifier);
            if (!$customer) {
                $error = 'We could not locate a customer record for the provided details.';
            } else {
                $channel = str_contains($identifier, '@') ? 'email' : 'sms';
                $destination = $channel === 'email' ? ($customer['email'] ?? $identifier) : ($customer['phone'] ?? $identifier);
                portal_issue_login_otp($customer['id'], $channel, (string) $destination);
                $_SESSION['portal_pending_customer'] = [
                    'id' => $customer['id'],
                    'identifier' => $identifier,
                    'channel' => $channel,
                    'destination' => (string) $destination,
                ];
                $message = 'A one-time passcode has been sent. Please check your ' . ($channel === 'email' ? 'email inbox' : 'SMS messages') . ' within the next 10 minutes.';
            }
        }
    } elseif ($action === 'verify_otp') {
        $otp = trim((string) ($_POST['otp'] ?? ''));
        $pending = $_SESSION['portal_pending_customer'] ?? null;
        if (!$pending) {
            $error = 'Please request a one-time passcode first.';
        } elseif ($otp === '') {
            $error = 'Enter the six-digit one-time passcode shared with you.';
        } elseif (!portal_verify_login_otp($pending['id'], $otp)) {
            $error = 'The one-time passcode is invalid or has expired. Please request a new one.';
        } else {
            $_SESSION['portal_customer_id'] = $pending['id'];
            unset($_SESSION['portal_pending_customer']);
            header('Location: /portal/');
            exit;
        }
    } elseif ($action === 'request_update' && $customerId) {
        $note = trim((string) ($_POST['message'] ?? ''));
        if ($note === '') {
            $error = 'Please let us know what you would like an update on.';
        } else {
            $snapshot = portal_customer_snapshot($customerId);
            $actor = 'portal:' . ($snapshot['customer']['email'] ?? $snapshot['customer']['id']);
            portal_record_update_request($customerId, $note, $actor);
            $message = 'Thank you. Your relationship manager will get in touch shortly.';
        }
    } elseif ($action === 'logout') {
        unset($_SESSION['portal_customer_id'], $_SESSION['portal_pending_customer']);
        header('Location: /portal/');
        exit;
    }
}

$pendingCustomer = $_SESSION['portal_pending_customer'] ?? null;
$snapshot = null;
if ($customerId) {
    try {
        $snapshot = portal_customer_snapshot($customerId);
    } catch (Throwable $exception) {
        $error = 'We were unable to load your account at this moment. Please try again later.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Customer Portal | Dakshayani Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css" rel="stylesheet" />
  </head>
  <body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="mx-auto min-h-screen w-full max-w-4xl px-6 py-10">
      <div class="mb-8 text-center">
        <h1 class="text-2xl font-semibold text-slate-900">Dakshayani Customer Portal</h1>
        <p class="mt-2 text-sm text-slate-500">Track subsidy progress, complaint tickets, documentation, and maintenance milestones.</p>
      </div>

      <?php if ($error): ?>
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
      <?php endif; ?>
      <?php if ($message): ?>
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
          <?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <?php if (!$customerId): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-900">Secure sign-in</h2>
          <p class="mt-2 text-sm text-slate-500">Enter your registered email address or phone number. We will send a one-time passcode for authentication.</p>
          <form method="post" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="<?= $pendingCustomer ? 'verify_otp' : 'request_otp'; ?>" />
            <?php if (!$pendingCustomer): ?>
              <div>
                <label class="text-xs font-medium text-slate-500" for="identifier">Email or phone number</label>
                <input id="identifier" name="identifier" type="text" required class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="you@example.com or +91-98765-43210" />
              </div>
              <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500">Send one-time passcode</button>
            <?php else: ?>
              <p class="text-sm text-slate-500">We have sent a passcode to <span class="font-medium text-slate-700"><?= htmlspecialchars($pendingCustomer['destination'] ?? 'your contact', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>. It expires in 10 minutes.</p>
              <div>
                <label class="text-xs font-medium text-slate-500" for="otp">One-time passcode</label>
                <input id="otp" name="otp" type="text" required class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="123456" />
              </div>
              <div class="flex items-center justify-between gap-3">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500">Verify &amp; sign in</button>
                <button type="submit" name="action" value="request_otp" class="text-xs font-medium text-blue-600 hover:underline">Resend passcode</button>
              </div>
            <?php endif; ?>
          </form>
        </div>
      <?php else: ?>
        <?php $customer = $snapshot['customer'] ?? []; ?>
        <div class="mb-6 flex items-center justify-between">
          <div>
            <p class="text-sm text-slate-500">Signed in as</p>
            <p class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($customer['full_name'] ?? 'Customer', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
          </div>
          <form method="post">
            <input type="hidden" name="action" value="logout" />
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Sign out</button>
          </form>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700">Project profile</h3>
            <dl class="mt-3 space-y-2 text-sm text-slate-600">
              <div class="flex justify-between"><dt class="font-medium text-slate-500">Application #</dt><dd><?= htmlspecialchars($customer['pmsg_application_no'] ?? 'N/A', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></dd></div>
              <div class="flex justify-between"><dt class="font-medium text-slate-500">Email</dt><dd><?= htmlspecialchars($customer['email'] ?? '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></dd></div>
              <div class="flex justify-between"><dt class="font-medium text-slate-500">Phone</dt><dd><?= htmlspecialchars($customer['phone'] ?? '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></dd></div>
              <div class="flex justify-between"><dt class="font-medium text-slate-500">State</dt><dd><?= htmlspecialchars($customer['state'] ?? '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></dd></div>
            </dl>
            <div class="mt-4 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700">
              <p class="font-semibold">Current PMSGY stage</p>
              <p class="mt-1 text-blue-600"><?= htmlspecialchars($snapshot['pmsg_stage'] ?? 'Application Received', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700">Request an update</h3>
            <p class="mt-2 text-sm text-slate-500">Let us know what you need clarified — subsidy disbursal, complaint status, or maintenance support.</p>
            <form method="post" class="mt-3 space-y-3">
              <input type="hidden" name="action" value="request_update" />
              <textarea name="message" rows="3" required class="w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Share your query or required update"></textarea>
              <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500">Submit request</button>
            </form>
          </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700">Support tickets</h3>
            <?php if (empty($snapshot['tickets'])): ?>
              <p class="mt-3 text-sm text-slate-500">No complaints or support tickets linked to your account.</p>
            <?php else: ?>
              <ul class="mt-3 space-y-3 text-sm text-slate-600">
                <?php foreach ($snapshot['tickets'] as $ticket): ?>
                  <li class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="font-medium text-slate-800"><?= htmlspecialchars($ticket['subject'] ?? 'Support ticket', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                    <p class="text-xs text-slate-500">Status: <?= htmlspecialchars(ucfirst($ticket['status'] ?? 'open'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> · Updated <?= htmlspecialchars($ticket['updated_at'] ?? $ticket['created_at'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700">Assets &amp; AMC</h3>
            <?php if (empty($snapshot['amcs'])): ?>
              <p class="mt-3 text-sm text-slate-500">No warranty or AMC assets are linked to this profile.</p>
            <?php else: ?>
              <ul class="mt-3 space-y-3 text-sm text-slate-600">
                <?php foreach ($snapshot['amcs'] as $asset): ?>
                  <li class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="font-medium text-slate-800"><?= htmlspecialchars($asset['asset_type'] ?? 'Asset', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> · <?= htmlspecialchars($asset['serial_number'] ?? 'SN', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                    <p class="text-xs text-slate-500">Installed <?= htmlspecialchars($asset['installation_date'] ?? 'N/A', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> · Warranty till <?= htmlspecialchars($asset['warranty_expiry'] ?? 'N/A', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700">Documents</h3>
            <?php if (empty($snapshot['documents'])): ?>
              <p class="mt-3 text-sm text-slate-500">Project documents will appear here once shared by our team.</p>
            <?php else: ?>
              <ul class="mt-3 space-y-3 text-sm text-slate-600">
                <?php foreach ($snapshot['documents'] as $doc): ?>
                  <li class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="font-medium text-slate-800"><?= htmlspecialchars($doc['title'] ?? 'Document', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                    <p class="text-xs text-slate-500">Updated <?= htmlspecialchars($doc['updated_at'] ?? $doc['created_at'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700">Recent communication</h3>
            <?php if (empty($snapshot['communications'])): ?>
              <p class="mt-3 text-sm text-slate-500">Your communication timeline will appear here once updates are logged.</p>
            <?php else: ?>
              <ul class="mt-3 space-y-3 text-sm text-slate-600">
                <?php foreach ($snapshot['communications'] as $entry): ?>
                  <li class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="font-medium text-slate-800"><?= htmlspecialchars($entry['summary'] ?? 'Update', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                    <p class="text-xs text-slate-500">Recorded <?= htmlspecialchars($entry['recorded_at'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> · <?= htmlspecialchars($entry['channel'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </body>
</html>
