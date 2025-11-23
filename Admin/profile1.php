<?php
// -CITY-CARE/City-Main/profile1.php (City Admin)
session_start();
require_once __DIR__ . '/../database/config.php';

if (empty($_SESSION['user'])) {
    header('Location: /-CITY-CARE/Forms/login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$adminLevel  = (int)($currentUser['is_admin'] ?? 0);

if ($adminLevel !== 1) {
    // super admin or normal user -> send them to generic profile router
    header('Location: /-CITY-CARE/City-Main/profile.php');
    exit;
}

$userId   = $currentUser['id'] ?? null;
$fullName = $currentUser['full_name'] ?? 'Admin';
$email    = $currentUser['email'] ?? 'unknown@example.com';
$city     = $currentUser['city'] ?? 'Unknown city';
$avatar   = $currentUser['avatar'] ?? null;

$openCount     = 0;
$workingCount  = 0;
$resolvedCount = 0;

try {
    $db = getDB();

    // Count issues this admin is handling (by handled_by)
    if ($userId !== null) {
        $stmt = $db->prepare("
            SELECT
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END)        AS open_count,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS working_count,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END)    AS resolved_count
            FROM issues
            WHERE handled_by = :id
        ");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            $openCount     = (int)$row['open_count'];
            $workingCount  = (int)$row['working_count'];
            $resolvedCount = (int)$row['resolved_count'];
        }
    }
} catch (Throwable $e) {
    // keep 0
}

// Score based on resolved issues
if ($resolvedCount >= 10) {
    $scoreLabel = 'City Guardian';
    $scoreDesc  = 'Outstanding work! You are resolving a lot of issues for your city.';
} elseif ($resolvedCount >= 7) {
    $scoreLabel = 'On Fire';
    $scoreDesc  = 'You are fixing many reports. Keep this energy up!';
} elseif ($resolvedCount >= 4) {
    $scoreLabel = 'Super admin is coming';
    $scoreDesc  = 'Great job. You’re getting close to super admin performance.';
} elseif ($resolvedCount >= 2) {
    $scoreLabel = 'Start working more';
    $scoreDesc  = 'Nice start, but there’s room to resolve more reports.';
} else {
    $scoreLabel = 'Get active';
    $scoreDesc  = 'Try resolving your first few reports. The city needs you.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Admin Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">

<header class="border-b border-slate-200 bg-white sticky top-0 z-40">
    <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="h-9 w-9 rounded-xl bg-slate-900 flex items-center justify-center text-white font-bold">
                A
            </div>
            <div>
                <div class="font-semibold text-base">CityCare</div>
                <div class="text-xs text-slate-500">Admin Profile</div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="/-CITY-CARE/admin/admin.php"
               class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-sm hover:bg-slate-800">
                Admin Panel
            </a>
            <a href="/-CITY-CARE/City-Main/index.php"
               class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm hover:bg-slate-50">
                ← Dashboard
            </a>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-6 py-8 space-y-6">

    <!-- Top section -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row gap-6">
        <!-- Avatar + info -->
        <div class="flex flex-col items-center md:items-start gap-4 md:w-1/3">
            <div class="relative">
                <div class="h-32 w-32 rounded-3xl bg-slate-100 flex items-center justify-center overflow-hidden border border-slate-200">
                    <?php if ($avatar): ?>
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="h-full w-full object-contain">
                    <?php else: ?>
                        <span class="text-3xl font-semibold text-slate-400">
                            <?php echo strtoupper(substr($fullName, 0, 1)); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-center md:text-left">
                <h1 class="text-xl font-semibold text-slate-900">
                    <?php echo htmlspecialchars($fullName); ?>
                </h1>
                <p class="text-sm text-slate-500">
                    <?php echo htmlspecialchars($city); ?> · City Admin
                </p>
            </div>
        </div>

        <!-- Stats -->
        <div class="flex-1 grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Issues You Handle</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    <?php echo $openCount + $workingCount + $resolvedCount; ?>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Total issues where you are marked as the handling admin.
                </p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-xs text-emerald-700 uppercase tracking-wide">Resolved by You</p>
                <p class="mt-2 text-xl font-semibold text-emerald-800">
                    <?php echo $resolvedCount; ?>
                </p>
                <p class="mt-1 text-xs text-emerald-700">
                    Completed reports you’ve resolved.
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 flex flex-col justify-between">
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide">Score</p>
                    <p class="mt-2 text-sm text-slate-800 font-semibold">
                        <?php echo htmlspecialchars($scoreLabel); ?>
                    </p>
                </div>
                <div class="mt-3 text-[11px] text-slate-500">
                    <?php echo htmlspecialchars($scoreDesc); ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Breakdown section -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-sm font-semibold text-slate-900 mb-3">Your workload</h2>
        <div class="grid sm:grid-cols-3 gap-4 text-sm">
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-xs text-amber-700 uppercase tracking-wide">Open</p>
                <p class="mt-2 text-xl font-semibold text-amber-800"><?php echo $openCount; ?></p>
                <p class="mt-1 text-xs text-amber-700">Still waiting to be progressed.</p>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3">
                <p class="text-xs text-sky-700 uppercase tracking-wide">Working</p>
                <p class="mt-2 text-xl font-semibold text-sky-800"><?php echo $workingCount; ?></p>
                <p class="mt-1 text-xs text-sky-700">Marked as in progress.</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-xs text-emerald-700 uppercase tracking-wide">Resolved</p>
                <p class="mt-2 text-xl font-semibold text-emerald-800"><?php echo $resolvedCount; ?></p>
                <p class="mt-1 text-xs text-emerald-700">Fully completed by you.</p>
            </div>
        </div>
    </section>

</main>

</body>
</html>
