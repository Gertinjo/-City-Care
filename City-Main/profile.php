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
$userId   = $currentUser['id'] ?? null;
$fullName = $currentUser['full_name'] ?? 'Unknown User';
$email    = $currentUser['email'] ?? 'unknown@example.com';
$city     = $currentUser['city'] ?? 'Unknown city';
$useravatar   = $currentUser['avatar'];
$isAdmin  = !empty($currentUser['is_admin']) && (int)$currentUser['is_admin'] === 1;

// -----------------------------------------------------
// RANDOM AVATAR (kept stable in session)
// -----------------------------------------------------
// We use the FRONT images inside: uploads/Walking_PNGs/*
// -----------------------------------------------------
// RANDOM AVATAR (kept stable in session)
// -----------------------------------------------------
// Absolute URLs from web root: /-CITY-CARE/City-Main/...
if (empty($_SESSION['avatar_path'])) {
    $base = '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking_Front.png';


    $randKey = array_rand($avatars);
    $_SESSION['avatar_path'] = $avatars[$randKey];
}

$avatarPath = $_SESSION['avatar_path'];

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
            <?php if ($isAdmin): ?>
                <a href="/-CITY-CARE/admin/admin.php"
                   class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-sm hover:bg-slate-800">
                    Admin Panel
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-6 py-8 space-y-6">

    <!-- Top section -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row gap-6">
        <!-- Avatar + basic info -->
        <div class="flex flex-col items-center md:items-start gap-4 md:w-1/3">
            <div class="relative">
                <div class="h-32 w-32 rounded-3xl bg-slate-100 flex items-center justify-center overflow-hidden border border-slate-200">
                    <img src="<?php echo htmlspecialchars($useravatar); ?>">
                </div>
            </div>
            <div class="text-center md:text-left">
                <h1 class="text-xl font-semibold text-slate-900">
                    <?php echo htmlspecialchars($fullName); ?>
                </h1>
                <p class="text-sm text-slate-500">
                    <?php echo htmlspecialchars($city); ?>
                    <?php if ($isAdmin): ?>
                        · <span class="inline-flex text-[11px] px-2 py-0.5 rounded-full bg-slate-900 text-white">Admin</span>
                    <?php else: ?>
                        · Citizen
                    <?php endif; ?>
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

</body>
</html>
