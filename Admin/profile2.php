<?php
// -CITY-CARE/City-Main/profile2.php (Super Admin)
session_start();
require_once __DIR__ . '/../database/config.php';

if (empty($_SESSION['user'])) {
    header('Location: /-CITY-CARE/Forms/login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$adminLevel  = (int)($currentUser['is_admin'] ?? 0);

if ($adminLevel !== 2) {
    header('Location: /-CITY-CARE/City-Main/profile.php');
    exit;
}

$superName  = $currentUser['full_name'] ?? 'Super Admin';
$superEmail = $currentUser['email'] ?? 'unknown@example.com';
$superCity  = $currentUser['city'] ?? 'All cities';
$avatar     = $currentUser['avatar'] ?? null;

$bestAdmin = null;
$adminsStats = [];

try {
    $db = getDB();

    // Best admin of last 7 days (by resolved issues)
    $stmt = $db->query("
        SELECT u.id, u.full_name, u.city, COUNT(*) AS resolved_count
        FROM issues i
        JOIN users u ON i.handled_by = u.id
        WHERE i.status = 'resolved'
          AND i.updated_at >= (NOW() - INTERVAL 7 DAY)
          AND u.is_admin IN (1,2)
        GROUP BY u.id
        ORDER BY resolved_count DESC
        LIMIT 1
    ");
    $bestAdmin = $stmt->fetch();

    // Stats per admin
    $stmt = $db->query("
        SELECT 
            u.id,
            u.full_name,
            u.city,
            u.is_admin,
            SUM(CASE WHEN i.status = 'open'        THEN 1 ELSE 0 END) AS open_count,
            SUM(CASE WHEN i.status = 'in_progress' THEN 1 ELSE 0 END) AS working_count,
            SUM(CASE WHEN i.status = 'resolved'    THEN 1 ELSE 0 END) AS resolved_count
        FROM users u
        LEFT JOIN issues i ON i.handled_by = u.id
        WHERE u.is_admin IN (1,2)
        GROUP BY u.id
        ORDER BY resolved_count DESC, open_count DESC
    ");

    foreach ($stmt as $row) {
        $adminsStats[] = [
            'id'            => (int)$row['id'],
            'full_name'     => $row['full_name'],
            'city'          => $row['city'],
            'is_admin'      => (int)$row['is_admin'],
            'open_count'    => (int)$row['open_count'],
            'working_count' => (int)$row['working_count'],
            'resolved_count'=> (int)$row['resolved_count'],
        ];
    }

} catch (Throwable $e) {
    // leave empty
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Super Admin Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">

<header class="border-b border-slate-200 bg-white sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-purple-700 flex items-center justify-center text-white font-bold">
                SA
            </div>
            <div>
                <div class="font-semibold text-base">CityCare</div>
                <div class="text-xs text-slate-500">Super Admin Profile</div>
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

<main class="max-w-6xl mx-auto px-6 py-8 space-y-6">

    <!-- Top section -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row gap-6">
        <!-- Super admin info -->
        <div class="flex flex-col items-center md:items-start gap-4 md:w-1/3">
            <div class="relative">
                <div class="h-32 w-32 rounded-3xl bg-slate-100 flex items-center justify-center overflow-hidden border border-slate-200">
                    <?php if ($avatar): ?>
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="h-full w-full object-contain">
                    <?php else: ?>
                        <span class="text-3xl font-semibold text-slate-400">
                            <?php echo strtoupper(substr($superName, 0, 1)); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-center md:text-left">
                <h1 class="text-xl font-semibold text-slate-900">
                    <?php echo htmlspecialchars($superName); ?>
                </h1>
                <p class="text-sm text-slate-500">
                    Oversees · All Cities
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    <?php echo htmlspecialchars($superEmail); ?>
                </p>
            </div>
        </div>

        <!-- Best admin of the week -->
        <div class="flex-1 grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-xs text-emerald-700 uppercase tracking-wide">Best Admin of the Week</p>
                <?php if ($bestAdmin): ?>
                    <p class="mt-2 text-lg font-semibold text-emerald-900">
                        <?php echo htmlspecialchars($bestAdmin['full_name']); ?>
                    </p>
                    <p class="text-xs text-emerald-700">
                        City: <?php echo htmlspecialchars($bestAdmin['city'] ?: 'Unknown'); ?>
                    </p>
                    <p class="mt-2 text-sm text-emerald-800">
                        Resolved <?php echo (int)$bestAdmin['resolved_count']; ?> issue(s) in the last 7 days.
                    </p>
                <?php else: ?>
                    <p class="mt-2 text-sm text-emerald-800">
                        No resolved issues in the last 7 days yet.
                    </p>
                <?php endif; ?>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 flex flex-col justify-between">
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide">Role</p>
                    <p class="mt-2 text-sm text-slate-800 font-semibold">
                        Super Admin
                    </p>
                </div>
                <div class="mt-3 text-[11px] text-slate-500">
                    You can see performance for all admins, across all municipalities,
                    and promote or guide them based on their activity.
                </div>
            </div>
        </div>
    </section>

    <!-- Admin performance table -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-slate-900">Admin Performance Overview</h2>
            <span class="text-xs text-slate-500">
                Based on issues where admin is marked as <span class="font-semibold">handled_by</span>.
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Admin</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">City</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Open</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Working</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Resolved</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (empty($adminsStats)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">
                            No admin activity data yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($adminsStats as $a): ?>
                        <?php
                        $total = $a['open_count'] + $a['working_count'] + $a['resolved_count'];
                        $roleLabel = $a['is_admin'] === 2 ? 'Super Admin' : 'Admin';
                        $roleClass = $a['is_admin'] === 2
                            ? 'bg-purple-700 text-white'
                            : 'bg-slate-900 text-white';
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 text-slate-900 font-medium">
                                #<?php echo $a['id']; ?> · <?php echo htmlspecialchars($a['full_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?php echo $a['city'] ? htmlspecialchars($a['city']) : '—'; ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium <?php echo $roleClass; ?>">
                                    <?php echo $roleLabel; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-amber-700">
                                <?php echo $a['open_count']; ?>
                            </td>
                            <td class="px-4 py-3 text-sky-700">
                                <?php echo $a['working_count']; ?>
                            </td>
                            <td class="px-4 py-3 text-emerald-700">
                                <?php echo $a['resolved_count']; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-900 font-semibold">
                                <?php echo $total; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>
