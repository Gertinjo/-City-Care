<?php
// -CITY-CARE/City-Main/profile.php

session_start();
require_once __DIR__ . '/../database/config.php';

// Require login
if (empty($_SESSION['user'])) {
    header('Location: /-CITY-CARE/Forms/login.php');
    exit;
}

$currentUser = $_SESSION['user'];

// -----------------------------------------------------
// ADMIN ROUTER
// is_admin = 2 → Super Admin (profile2.php)
// is_admin = 1 → Admin (profile1.php)
// 0 or null  → Normal citizen (this file)
// -----------------------------------------------------
$isAdminLevel = (int)($currentUser['is_admin'] ?? 0);

if ($isAdminLevel === 2) {
    // Super admin profile
    header('Location: /-CITY-CARE/admin/profile2.php');
    exit;
} elseif ($isAdminLevel === 1) {
    // City admin profile
    header('Location: /-CITY-CARE/admin/profile1.php');
    exit;
}

// -----------------------------------------------------
// HANDLE NAME UPDATE (CITIZEN ONLY)
// -----------------------------------------------------
$updateMsg = null;
$updateErr = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_name') {
    $newName = trim($_POST['full_name'] ?? '');

    if ($newName === '') {
        $updateErr = 'Full name cannot be empty.';
    } elseif (mb_strlen($newName) > 100) {
        $updateErr = 'Full name is too long.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET full_name = :fn WHERE id = :id");
            $stmt->execute([
                ':fn' => $newName,
                ':id' => $currentUser['id'],
            ]);

            // Update session so it shows immediately
            $_SESSION['user']['full_name'] = $newName;
            $currentUser['full_name'] = $newName;
            $updateMsg = 'Your name has been updated.';
        } catch (Throwable $e) {
            $updateErr = 'Could not update your name right now.';
        }
    }
}

// -----------------------------------------------------
// NORMAL USER PROFILE (CITIZEN)
// -----------------------------------------------------
$userId   = $currentUser['id'] ?? null;
$fullName = $currentUser['full_name'] ?? 'Unknown User';
$email    = $currentUser['email'] ?? 'unknown@example.com';
$city     = $currentUser['city'] ?? 'Unknown city';

// if 'avatar' is missing in session, use a default image instead of error
$useravatar = $currentUser['avatar']
    ?? '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png';

// -----------------------------------------------------
// REPORT STATS FOR THIS USER (uses issues.created_by)
// -----------------------------------------------------
$reportCount = 0;
try {
    $db = getDB();

    if ($userId !== null) {
        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM issues WHERE created_by = :uid");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            $reportCount = (int)$row['c'];
        }
    }
} catch (Throwable $e) {
    // if DB fails, just leave reportCount = 0
}

// -----------------------------------------------------
// SCORE LABEL
// -----------------------------------------------------
if ($reportCount >= 10) {
    $scoreLabel = 'Excellent';
    $scoreDesc  = 'You are a top CityCare reporter. Great work keeping the city in shape!';
} elseif ($reportCount >= 5) {
    $scoreLabel = 'Nice';
    $scoreDesc  = 'You are actively helping your city. Keep it going!';
} elseif ($reportCount >= 3) {
    $scoreLabel = 'Not bad';
    $scoreDesc  = 'Good start. A few more reports and your impact grows.';
} elseif ($reportCount === 2) {
    $scoreLabel = 'Could be better';
    $scoreDesc  = 'You’re warming up. Don’t hesitate to report issues you see.';
} else { // 0 or 1
    $scoreLabel = 'Get active';
    $scoreDesc  = 'CityCare works best when citizens are active. Try reporting your first issues.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">

<!-- HEADER -->
<header class="border-b border-slate-200 bg-white sticky top-0 z-40">
    <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="h-9 w-9 rounded-xl bg-emerald-500 flex items-center justify-center text-white font-bold">
                C
            </div>
            <div>
                <div class="font-semibold text-base">CityCare</div>
                <div class="text-xs text-slate-500">Citizen Profile</div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="/-CITY-CARE/City-Main/index.php"
               class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm hover:bg-slate-50">
                ← Dashboard
            </a>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-6 py-8 space-y-6">

    <!-- Alerts for name update -->
    <?php if ($updateMsg): ?>
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
            <?php echo htmlspecialchars($updateMsg); ?>
        </div>
    <?php endif; ?>
    <?php if ($updateErr): ?>
        <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
            <?php echo htmlspecialchars($updateErr); ?>
        </div>
    <?php endif; ?>

    <!-- Top section -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row gap-6">
        <!-- Avatar + basic info -->
        <div class="flex flex-col items-center md:items-start gap-4 md:w-1/3">
            <div class="relative">
                <div class="h-32 w-32 rounded-3xl bg-slate-100 flex items-center justify-center overflow-hidden border border-slate-200">
                    <img src="<?php echo htmlspecialchars($useravatar); ?>"
                         alt="Profile avatar"
                         class="h-full w-full object-contain">
                </div>
            </div>
            <div class="text-center md:text-left">
                <!-- Clickable name with dropdown -->
                <div class="relative inline-block text-left">
                    <button type="button"
                            id="name-toggle-btn"
                            class="text-xl font-semibold text-slate-900 inline-flex items-center gap-1 cursor-pointer focus:outline-none">
                        <?php echo htmlspecialchars($fullName); ?>
                        <span class="text-xs text-slate-400">✎</span>
                    </button>
                    <div id="name-dropdown-menu"
                         class="hidden absolute left-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white shadow-lg z-20 p-3">
                        <p class="text-xs text-slate-500 mb-2">
                            Change your full name:
                        </p>
                        <form method="post" class="space-y-2 text-sm">
                            <input type="hidden" name="action" value="update_name">
                            <input type="text"
                                   name="full_name"
                                   value="<?php echo htmlspecialchars($fullName); ?>"
                                   class="w-full rounded-lg border border-slate-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <button type="submit"
                                    class="w-full mt-1 inline-flex items-center justify-center px-3 py-1.5 rounded-lg bg-emerald-500 text-white text-xs font-medium hover:bg-emerald-600">
                                Save name
                            </button>
                            <p class="text-[11px] text-slate-400 mt-1">
                                This will update your account name.
                            </p>
                        </form>
                    </div>
                </div>

                <p class="mt-1 text-sm text-slate-500">
                    <?php echo htmlspecialchars($city); ?> · Citizen
                </p>
            </div>
        </div>

        <!-- Stats -->
        <div class="flex-1 grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Total Reports</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    <?php echo $reportCount; ?>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Issues you have reported using CityCare.
                </p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-xs text-emerald-700 uppercase tracking-wide">Score</p>
                <p class="mt-2 text-xl font-semibold text-emerald-800">
                    <?php echo htmlspecialchars($scoreLabel); ?>
                </p>
                <p class="mt-1 text-xs text-emerald-700">
                    <?php echo htmlspecialchars($scoreDesc); ?>
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 flex flex-col justify-between">
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide">Account</p>
                    <p class="mt-2 text-sm text-slate-800">
                        <?php echo htmlspecialchars($email); ?>
                    </p>
                </div>
                <div class="mt-3 text-[11px] text-slate-500">
                    Tip: The more accurate your reports, the more helpful CityCare becomes for your city.
                </div>
            </div>
        </div>
    </section>

    <!-- Placeholder for future activity section -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-sm font-semibold text-slate-900 mb-3">Activity</h2>
        <p class="text-sm text-slate-500">
            In a future version, this can show your latest reports and their current status.
        </p>
    </section>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('name-toggle-btn');
    const menu = document.getElementById('name-dropdown-menu');

    if (!toggleBtn || !menu) return;

    toggleBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.classList.toggle('hidden');
    });

    document.addEventListener('click', function (e) {
        if (!menu.classList.contains('hidden')) {
            if (!menu.contains(e.target) && e.target !== toggleBtn) {
                menu.classList.add('hidden');
            }
        }
    });
});
</script>

</body>
</html>
