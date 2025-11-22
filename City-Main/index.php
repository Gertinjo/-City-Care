<?php
// CityCare - Smart Reporting Platform
session_start();

require_once __DIR__ . '/../database/config.php';

// -----------------------------------------------------
// SIMPLE DEV ROLE SWITCH (for now)
// -----------------------------------------------------
if (isset($_GET['as']) && in_array($_GET['as'], ['admin', 'citizen'])) {
    $_SESSION['role'] = $_GET['as'];
}
$role = $_SESSION['role'] ?? 'citizen';

// -----------------------------------------------------
// PAGE ROUTER
// -----------------------------------------------------
$page = $_GET['page'] ?? 'dashboard';

$cityName      = "Prishtina";
$reportError   = null;
$reportSuccess = isset($_GET['reported']) ? true : false;
$adminMsg      = null;
$adminErr      = null;

// -----------------------------------------------------
// HANDLE ADMIN ACTIONS (only on dashboard, only admin)
// -----------------------------------------------------
if (
    $role === 'admin' &&
    $page === 'dashboard' &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['admin_action'], $_POST['issue_id'])
) {
    $action  = $_POST['admin_action'];
    $issueId = (int)$_POST['issue_id'];

    try {
        $db = getDB();

        if ($action === 'start') {
            // Only change from open -> in_progress
            $stmt = $db->prepare("UPDATE issues SET status='in_progress' WHERE id=:id AND status='open'");
            $stmt->execute([':id' => $issueId]);

            if ($stmt->rowCount() > 0) {
                $adminMsg = "Issue #{$issueId} is now in WORKING state.";
            } else {
                $adminErr = "Cannot start work on this issue (maybe not open anymore).";
            }

        } elseif ($action === 'resolve') {
            // Load issue and check 60s rule
            $stmt = $db->prepare("SELECT status, updated_at, created_at FROM issues WHERE id=:id");
            $stmt->execute([':id' => $issueId]);
            $issue = $stmt->fetch();

            if (!$issue) {
                $adminErr = "Issue not found.";
            } elseif ($issue['status'] !== 'in_progress') {
                $adminErr = "Issue must be in WORKING state before resolving.";
            } else {
                $updatedAt = $issue['updated_at'] ?: $issue['created_at'];
                $updated   = new DateTime($updatedAt);
                $now       = new DateTime();
                $diffSec   = $now->getTimestamp() - $updated->getTimestamp();

                if ($diffSec < 60) {
                    $left = 60 - $diffSec;
                    $adminErr = "You can resolve this issue after {$left} more seconds.";
                } else {
                    $stmt = $db->prepare("UPDATE issues SET status='resolved' WHERE id=:id AND status='in_progress'");
                    $stmt->execute([':id' => $issueId]);

                    if ($stmt->rowCount() > 0) {
                        $adminMsg = "Issue #{$issueId} has been RESOLVED.";
                    } else {
                        $adminErr = "Could not resolve this issue.";
                    }
                }
            }
        }

    } catch (Throwable $e) {
        $adminErr = 'Admin action failed (database error).';
    }
}

// -----------------------------------------------------
// HANDLE REPORT FORM SUBMIT (citizen)
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'report') {
    $title       = trim($_POST['title'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $anonymous   = isset($_POST['anonymous']) ? 1 : 0;
    $lat         = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
    $lng         = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;

    if ($title === '' || $category === '') {
        $reportError = 'Title and category are required.';
    } else {
        $photoPath = null;

        // optional photo upload
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowedExt = ['jpg', 'jpeg', 'png'];
            $uploadDir  = __DIR__ . '/uploads/issues';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $originalName = $_FILES['photo']['name'];
            $tmpPath      = $_FILES['photo']['tmp_name'];
            $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (in_array($ext, $allowedExt)) {
                $newName = 'issue_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $target  = $uploadDir . '/' . $newName;
                if (move_uploaded_file($tmpPath, $target)) {
                    $photoPath = 'uploads/issues/' . $newName;
                }
            }
        }

        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO issues 
                    (title, category, status, location_text, latitude, longitude, description, photo_path, is_anonymous, created_by) 
                VALUES 
                    (:title, :category, 'open', :location_text, :latitude, :longitude, :description, :photo_path, :is_anonymous, NULL)
            ");
            $stmt->execute([
                ':title'         => $title,
                ':category'      => $category,
                ':location_text' => $location !== '' ? $location : null,
                ':latitude'      => $lat,
                ':longitude'     => $lng,
                ':description'   => $description !== '' ? $description : null,
                ':photo_path'    => $photoPath,
                ':is_anonymous'  => $anonymous,
            ]);

            header('Location: index.php?page=dashboard&reported=1');
            exit;
        } catch (Throwable $e) {
            $reportError = 'Could not save your report (database error).';
        }
    }
}

// -----------------------------------------------------
// LOAD DATA FOR DASHBOARD
// -----------------------------------------------------
$stats = [
    'open'              => 0,
    'in_progress'       => 0,
    'resolved'          => 0,
    'city_health_score' => 100,
];

$issues        = [];
$issuesForMap  = [];
$adminIssues   = [];

try {
    $db = getDB();

    // stats
    $stmt = $db->query("SELECT status, COUNT(*) AS c FROM issues GROUP BY status");
    foreach ($stmt as $row) {
        if ($row['status'] === 'open') {
            $stats['open'] = (int)$row['c'];
        } elseif ($row['status'] === 'in_progress') {
            $stats['in_progress'] = (int)$row['c'];
        } elseif ($row['status'] === 'resolved') {
            $stats['resolved'] = (int)$row['c'];
        }
    }

    $total = $stats['open'] + $stats['in_progress'] + $stats['resolved'];
    if ($total === 0) {
        $stats['city_health_score'] = 100;
    } else {
        $penalty = $stats['open'] * 3 + $stats['in_progress'] * 1;
        $score   = 100 - min(75, $penalty);
        if ($score < 10) $score = 10;
        $stats['city_health_score'] = $score;
    }

    // latest issues for small list
    $stmt = $db->query("
        SELECT id, title, category, status, location_text, created_at
        FROM issues
        ORDER BY created_at DESC
        LIMIT 5
    ");
    foreach ($stmt as $row) {
        $issues[] = [
            'title'    => $row['title'],
            'category' => $row['category'],
            'status'   => match ($row['status']) {
                'open'        => 'Open',
                'in_progress' => 'In Progress',
                'resolved'    => 'Resolved',
                default       => ucfirst($row['status']),
            },
            'location' => $row['location_text'] ?? 'No location',
            'time'     => date('d M Y H:i', strtotime($row['created_at'])),
        ];
    }

    // issues with coords for dashboard map
    $stmt = $db->query("
        SELECT id, title, category, status, location_text, latitude, longitude, created_at
        FROM issues
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        ORDER BY created_at DESC
        LIMIT 200
    ");
    foreach ($stmt as $row) {
        $issuesForMap[] = [
            'id'       => (int)$row['id'],
            'title'    => $row['title'],
            'category' => $row['category'],
            'status'   => $row['status'],
            'location' => $row['location_text'],
            'lat'      => (float)$row['latitude'],
            'lng'      => (float)$row['longitude'],
        ];
    }

    // admin list: full table with timing info
    if ($role === 'admin') {
        $stmt = $db->query("
            SELECT id, title, category, status, location_text, created_at, updated_at
            FROM issues
            ORDER BY created_at DESC
            LIMIT 30
        ");
        foreach ($stmt as $row) {
            $updated   = $row['updated_at'] ?: $row['created_at'];
            $updatedDt = new DateTime($updated);
            $now       = new DateTime();
            $diffSec   = $now->getTimestamp() - $updatedDt->getTimestamp();

            $canResolve  = ($row['status'] === 'in_progress' && $diffSec >= 60);
            $secondsLeft = ($row['status'] === 'in_progress' && $diffSec < 60) ? (60 - $diffSec) : 0;

            $adminIssues[] = [
                'id'          => (int)$row['id'],
                'title'       => $row['title'],
                'category'    => $row['category'],
                'status'      => $row['status'],
                'location'    => $row['location_text'],
                'created_at'  => $row['created_at'],
                'updated_at'  => $row['updated_at'],
                'can_resolve' => $canResolve,
                'seconds_left'=> $secondsLeft,
            ];
        }
    }

} catch (Throwable $e) {
    // leave arrays empty
}

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Smart Reporting Platform</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        if (localStorage.theme === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <style>
        .glass-card {
            backdrop-filter: blur(12px);
            background: rgba(15, 23, 42, 0.65);
        }
    </style>
</head>
<body class="bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col">

<header class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur sticky top-0 z-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-14">
        <div class="flex items-center gap-2">
            <div class="h-8 w-8 rounded-lg bg-emerald-500 flex items-center justify-center text-white font-bold">C</div>
            <div>
                <div class="font-semibold text-sm sm:text-base">CityCare</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Smart Reporting Platform</div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div class="hidden sm:flex items-center gap-2 text-xs sm:text-sm">
                <span class="uppercase tracking-wide text-slate-500 dark:text-slate-400">City Health</span>
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs font-semibold">
                    <?php echo $stats['city_health_score']; ?>/100
                </span>
            </div>

            <span class="hidden sm:inline-flex px-2.5 py-1 rounded-full text-xs font-medium 
                         <?php echo $role === 'admin' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'; ?>">
                <?php echo ucfirst($role); ?> view
            </span>

            <button
                class="h-8 w-8 flex items-center justify-center rounded-full border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-300 text-lg"
                onclick="
                    document.documentElement.classList.toggle('dark');
                    localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                "
                title="Toggle dark mode"
            >
                🌗
            </button>
        </div>
    </div>
</header>

<main class="flex-1">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 grid gap-6 lg:grid-cols-[260px,1fr]">

        <!-- SIDEBAR -->
        <aside class="space-y-4">
            <div class="bg-white dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">Welcome</div>
                <div class="font-semibold text-sm mb-1">
                    <?php echo $role === 'admin' ? 'City Operator' : 'Citizen'; ?> Panel
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    CityCare prototype for <?php echo htmlspecialchars($cityName); ?>.
                </p>
            </div>

            <nav class="bg-white dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 shadow-sm space-y-1 text-sm">
                <a href="index.php?page=dashboard"
                   class="flex items-center justify-between px-3 py-2 rounded-xl <?php echo $page === 'dashboard' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>Dashboard</span>
                    <span class="text-xs text-slate-400">Overview</span>
                </a>
                <a href="index.php?page=report"
                   class="flex items-center justify-between px-3 py-2 rounded-xl <?php echo $page === 'report' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>Report a Problem</span>
                    <span class="text-xs text-slate-400">New issue</span>
                </a>
            </nav>

            <div class="text-[11px] text-slate-400 dark:text-slate-500">
                Dev shortcuts:
                <a href="?as=citizen" class="underline">Citizen</a> ·
                <a href="?as=admin" class="underline">Admin</a>
            </div>
        </aside>

        <!-- MAIN -->
        <section class="space-y-6">

            <?php if ($page === 'dashboard'): ?>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-semibold">Dashboard · <?php echo htmlspecialchars($cityName); ?></h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Track reported issues and their status on the map of Prishtina.
                        </p>
                        <?php if ($reportSuccess): ?>
                            <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                ✅ Your report has been submitted successfully.
                            </p>
                        <?php endif; ?>
                        <?php if ($adminMsg): ?>
                            <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                ✅ <?php echo htmlspecialchars($adminMsg); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($adminErr): ?>
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                ⚠ <?php echo htmlspecialchars($adminErr); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <a href="index.php?page=report"
                       class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium shadow-sm">
                        + Report a new problem
                    </a>
                </div>

                <!-- STATS -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
                    <div class="bg-white dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 sm:p-4 shadow-sm">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Open Issues</div>
                        <div class="mt-1 text-xl font-semibold text-amber-500"><?php echo $stats['open']; ?></div>
                    </div>
                    <div class="bg-white dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 sm:p-4 shadow-sm">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Working</div>
                        <div class="mt-1 text-xl font-semibold text-sky-500"><?php echo $stats['in_progress']; ?></div>
                    </div>
                    <div class="bg-white dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 sm:p-4 shadow-sm">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Resolved</div>
                        <div class="mt-1 text-xl font-semibold text-emerald-500"><?php echo $stats['resolved']; ?></div>
                    </div>
                    <div class="bg-gradient-to-br from-emerald-500 to-sky-500 rounded-2xl p-3 sm:p-4 text-white shadow-md">
                        <div class="text-xs opacity-80">City Health Score</div>
                        <div class="mt-1 text-xl font-semibold"><?php echo $stats['city_health_score']; ?>/100</div>
                        <div class="mt-1 text-[11px] opacity-80">
                            More open issues and slow responses lower this score.
                        </div>
                    </div>
                </div>

                <!-- TOP GRID -->
                <div class="grid lg:grid-cols-[1.1fr,0.9fr] gap-4 sm:gap-5">

                    <!-- Recent Reports -->
                    <div class="bg-white dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-4 py-3 flex items-center justify-between border-b border-slate-100 dark:border-slate-800">
                            <h2 class="text-sm font-semibold">Recent Reports</h2>
                            <span class="text-xs text-slate-400">latest from database</span>
                        </div>
                        <?php if (empty($issues)): ?>
                            <div class="px-4 py-6 text-sm text-slate-500 dark:text-slate-400">
                                No reports yet. Be the first to report an issue.
                            </div>
                        <?php else: ?>
                            <ul class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
                                <?php foreach ($issues as $issue): ?>
                                    <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($issue['title']); ?></div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                <?php echo htmlspecialchars($issue['location']); ?> · <?php echo htmlspecialchars($issue['time']); ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 sm:text-right">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                                <?php echo htmlspecialchars($issue['category']); ?>
                                            </span>
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px]
                                                <?php
                                                echo match ($issue['status']) {
                                                    'Open' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                                    'In Progress' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
                                                    'Resolved' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                                                    default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                                };
                                                ?>">
                                                <?php echo htmlspecialchars($issue['status']); ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Map card -->
                    <div class="space-y-4">
                        <div class="bg-white dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
                            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <h2 class="text-sm font-semibold">Prishtina Map</h2>
                                <span class="text-[11px] text-slate-400">click markers for details</span>
                            </div>
                            <div id="cityMap" class="h-56 bg-slate-100 dark:bg-slate-900 rounded-b-2xl"></div>
                        </div>

                        <div class="bg-gradient-to-br from-slate-900 to-slate-800 glass-card rounded-2xl p-4 text-slate-100 shadow-md">
                            <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">Prototype Note</div>
                            <p class="text-sm mb-2">
                                Each report with coordinates appears as a marker on the map of Prishtina.
                            </p>
                            <ul class="text-xs space-y-1 text-slate-200">
                                <li>• Red = Open, Orange = Working, Green = Resolved</li>
                                <li>• Admin can start working and resolve issues in the panel below</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if ($role === 'admin'): ?>
                <?php endif; ?>

            <?php elseif ($page === 'report'): ?>

                <!-- REPORT PAGE (unchanged from previous, shortened a bit) -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-semibold">Report a Problem</h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Help keep <?php echo htmlspecialchars($cityName); ?> clean, safe and working.
                        </p>
                        <?php if ($reportError): ?>
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                ⚠ <?php echo htmlspecialchars($reportError); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <a href="index.php?page=dashboard"
                       class="inline-flex items-center justify-center px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm">
                        ← Back to dashboard
                    </a>
                </div>

                <div class="mt-4 bg-white dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-6 shadow-sm">
                    <form action="index.php?page=report" method="post" enctype="multipart/form-data" class="space-y-4 text-sm">

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Issue title</label>
                                <input type="text" name="title" required
                                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Category</label>
                                <select name="category" required
                                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2">
                                    <option value="">Choose…</option>
                                    <option>Streetlight</option>
                                    <option>Road / Pothole</option>
                                    <option>Waste / Trash</option>
                                    <option>Water / Sewage</option>
                                    <option>Noise / Safety</option>
                                    <option>Other</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Location (address or description)</label>
                            <input type="text" name="location"
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2">
                            <p class="mt-1 text-[11px] text-slate-400">You can also pick the exact spot on the map.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Map · Click to set the problem location
                            </label>
                            <div id="reportMap" class="h-64 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700"></div>
                            <input type="hidden" name="lat" id="latField">
                            <input type="hidden" name="lng" id="lngField">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Description</label>
                            <textarea name="description" rows="4"
                                      class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2"></textarea>
                        </div>

                        <div class="grid md:grid-cols-[1.5fr,1fr] gap-4 items-start">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Photo (optional)</label>
                                <input type="file" name="photo"
                                       class="block w-full text-xs text-slate-500 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-emerald-500 file:text-white file:text-xs hover:file:bg-emerald-600">
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl p-3 text-xs text-slate-500 dark:text-slate-400">
                                <div class="font-semibold text-slate-600 dark:text-slate-200 mb-1">What happens next?</div>
                                <ol class="list-decimal ml-4 space-y-0.5">
                                    <li>Your report is saved in the CityCare database.</li>
                                    <li>City operators can start working on it.</li>
                                    <li>When it’s fixed, status becomes Resolved.</li>
                                </ol>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <div class="flex items-center gap-2 text-[11px] text-slate-400">
                                <input type="checkbox" id="anonymous" name="anonymous" class="rounded border-slate-300 dark:border-slate-600">
                                <label for="anonymous">Submit anonymously</label>
                            </div>
                            <button type="submit"
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium shadow-sm">
                                Submit report
                            </button>
                        </div>
                    </form>
                </div>

            <?php endif; ?>

        </section>

    </div>
</main>

<footer class="border-t border-slate-200 dark:border-slate-800 text-[11px] text-slate-400 dark:text-slate-500 py-3">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-1.5">
        <span>CityCare Prototype · PHP &amp; MySQL · Prishtina</span>
        <span>Made for Champion Trails · <?php echo date('Y'); ?></span>
    </div>
</footer>

<!-- MAP SCRIPTS -->
<?php if ($page === 'dashboard'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const map = L.map('cityMap').setView([42.6629, 21.1655], 13); // Prishtina

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const issues = <?php echo json_encode($issuesForMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

    issues.forEach(issue => {
        const color = issue.status === 'open'
            ? 'red'
            : (issue.status === 'in_progress' ? 'orange' : 'green');

        const marker = L.circleMarker([issue.lat, issue.lng], {
            radius: 7,
            color,
            fillColor: color,
            fillOpacity: 0.9
        }).addTo(map);

        marker.bindPopup(
            `<strong>${issue.title}</strong><br>` +
            `${issue.category}<br>` +
            (issue.location ? `<small>${issue.location}</small>` : '')
        );
    });
});
</script>
<?php elseif ($page === 'report'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const map = L.map('reportMap').setView([42.6629, 21.1655], 13); // Prishtina

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;
    const latField = document.getElementById('latField');
    const lngField = document.getElementById('lngField');

    map.on('click', function (e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        if (marker) marker.setLatLng(e.latlng);
        else marker = L.marker(e.latlng).addTo(map);

        latField.value = lat.toFixed(6);
        lngField.value = lng.toFixed(6);
    });
});
</script>
<?php endif; ?>

</body>
</html>
