<?php 
// admin.php – CityCare Admin Panel
session_start();

require_once __DIR__ . '/../database/config.php';

// ---- simple role check (dev shortcut) ----
if (isset($_GET['as']) && in_array($_GET['as'], ['admin', 'citizen'])) {
    $_SESSION['role'] = $_GET['as'];
}
$role = $_SESSION['role'] ?? 'citizen';

$cityName = "Prishtina";
$adminMsg = null;
$adminErr = null;

// ---- handle admin actions (start / resolve) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'], $_POST['issue_id'])) {
    $action  = $_POST['admin_action'];
    $issueId = (int)$_POST['issue_id'];

    try {
        $db = getDB();

        if ($action === 'start') {
            $stmt = $db->prepare("UPDATE issues SET status='in_progress', updated_at=NOW() WHERE id=:id AND status='open'");
            $stmt->execute([':id' => $issueId]);

            if ($stmt->rowCount() > 0) {
                $adminMsg = "Issue #{$issueId} is now WORKING. Timer started.";
            } else {
                $adminErr = "Cannot start work on this issue (maybe not open anymore).";
            }
        } elseif ($action === 'resolve') {
            $stmt = $db->prepare("SELECT status, created_at, updated_at FROM issues WHERE id=:id");
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
                    $stmt = $db->prepare("UPDATE issues SET status='resolved', updated_at=NOW() WHERE id=:id AND status='in_progress'");
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
        $adminErr = "Admin action failed (database error).";
    }
}

// ---- load issues for table ----
$adminIssues = [];
$stats = ['open' => 0, 'in_progress' => 0, 'resolved' => 0];

try {
    $db = getDB();

    // stats
    $stmt = $db->query("SELECT status, COUNT(*) AS c FROM issues GROUP BY status");
    foreach ($stmt as $row) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = (int)$row['c'];
        }
    }

    // table (include latitude, longitude)
    $stmt = $db->query("
        SELECT id, title, category, status, location_text, latitude, longitude, created_at, updated_at
        FROM issues
        ORDER BY created_at DESC
        LIMIT 100
    ");

    foreach ($stmt as $row) {
        $updated   = $row['updated_at'] ?: $row['created_at'];
        $updatedDt = new DateTime($updated);
        $now       = new DateTime();
        $diffSec   = $now->getTimestamp() - $updatedDt->getTimestamp();

        $canResolve  = ($row['status'] === 'in_progress' && $diffSec >= 60);
        $secondsLeft = ($row['status'] === 'in_progress' && $diffSec < 60) ? (60 - $diffSec) : 0;

        $adminIssues[] = [
            'id'           => (int)$row['id'],
            'title'        => $row['title'],
            'category'     => $row['category'],
            'status'       => $row['status'],
            'lat'          => $row['latitude'],
            'lng'          => $row['longitude'],
            'created_at'   => $row['created_at'],
            'updated_at'   => $row['updated_at'],
            'can_resolve'  => $canResolve,
            'seconds_left' => $secondsLeft,
        ];
    }

} catch (Throwable $e) {
    $adminErr = "Could not load issues (database error).";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet for the mini maps -->
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    />
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
    </script>
    <style>
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-down { animation: slideDown 0.25s ease-out; }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">

<!-- Header -->
<header class="border-b border-slate-200 bg-white sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-slate-900">
                CityCare Admin
            </h1>
            <p class="text-xs text-slate-500">Managing reports for <?php echo htmlspecialchars($cityName); ?></p>
        </div>
        <div class="flex items-center gap-3">
            <div class="hidden md:flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-medium">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    Open: <?php echo $stats['open']; ?>
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-medium">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-sky-400"></span>
                    Working: <?php echo $stats['in_progress']; ?>
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    Resolved: <?php echo $stats['resolved']; ?>
                </span>
            </div>
            <a href="/-CITY-CARE/City-Main/index.php?page=dashboard&as=admin"
               class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-sm font-medium">
                ← Dashboard
            </a>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="max-w-7xl mx-auto px-6 py-8">
    
    <!-- Stat Cards (Mobile) -->
    <div class="md:hidden grid grid-cols-3 gap-3 mb-6">
        <div class="bg-white border border-slate-200 rounded-lg p-3 text-center">
            <p class="text-2xl font-bold text-amber-500"><?php echo $stats['open']; ?></p>
            <p class="text-xs text-slate-500 mt-1">Open</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-lg p-3 text-center">
            <p class="text-2xl font-bold text-sky-500"><?php echo $stats['in_progress']; ?></p>
            <p class="text-xs text-slate-500 mt-1">Working</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-lg p-3 text-center">
            <p class="text-2xl font-bold text-emerald-500"><?php echo $stats['resolved']; ?></p>
            <p class="text-xs text-slate-500 mt-1">Resolved</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($adminMsg): ?>
        <div class="mb-6 animate-slide-down flex items-center gap-3 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span><?php echo htmlspecialchars($adminMsg); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($adminErr): ?>
        <div class="mb-6 animate-slide-down flex items-center gap-3 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <span><?php echo htmlspecialchars($adminErr); ?></span>
        </div>
    <?php endif; ?>

    <!-- Reports Table -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-900">All Reports</h2>
            <span class="text-xs text-slate-500 bg-white px-3 py-1 rounded-full border border-slate-200">
                ⏱ Start → Wait 60s → Resolve
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Problem</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Timeline</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($adminIssues)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <p class="text-slate-500 text-sm">No issues reported yet</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($adminIssues as $row): ?>
                            <?php
                            $statusBadge = match ($row['status']) {
                                'open' => ['label' => 'Open', 'class' => 'bg-amber-50 text-amber-700 border border-amber-200'],
                                'in_progress' => ['label' => 'Working', 'class' => 'bg-sky-50 text-sky-700 border border-sky-200'],
                                'resolved' => ['label' => 'Resolved', 'class' => 'bg-emerald-50 text-emerald-700 border border-emerald-200'],
                                default => ['label' => ucfirst($row['status']), 'class' => 'bg-slate-100 text-slate-700 border border-slate-200'],
                            };
                            $since     = $row['updated_at'] ?: $row['created_at'];
                            $hasCoords = !is_null($row['lat']) && !is_null($row['lng']);
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors align-top">
                                <td class="px-6 py-4 text-slate-700 font-medium">#<?php echo $row['id']; ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900">
                                        <?php echo htmlspecialchars($row['title']); ?>
                                    </div>

                                    <?php if ($hasCoords): ?>
                                        <button type="button"
                                                class="mt-2 inline-flex items-center text-xs text-sky-600 hover:text-sky-700 gap-1"
                                                onclick="toggleIssueMap(<?php echo $row['id']; ?>, <?php echo $row['lat']; ?>, <?php echo $row['lng']; ?>)">
                                            <span>▼ View map</span>
                                        </button>
                                        <div id="map-wrapper-<?php echo $row['id']; ?>" class="mt-2 hidden">
                                            <div id="map-<?php echo $row['id']; ?>" class="h-40 rounded-lg border border-slate-200"></div>
                                        </div>
                                    <?php else: ?>
                                        <p class="mt-1 text-xs text-slate-400">No map location</p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-700 text-sm">
                                    <?php echo htmlspecialchars($row['category']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="status-badge <?php echo $statusBadge['class']; ?>">
                                        <?php echo $statusBadge['label']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500">
                                    <div>Created: <?php echo date('M d, H:i', strtotime($row['created_at'])); ?></div>
                                    <div class="mt-1">Updated: <?php echo date('M d, H:i', strtotime($since)); ?></div>
                                    <?php if ($row['status'] === 'in_progress' && !$row['can_resolve']): ?>
                                        <div class="mt-2 text-amber-600 font-medium">⏳ Wait <?php echo $row['seconds_left']; ?>s</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($row['status'] === 'open'): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="issue_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="admin_action" value="start">
                                            <button type="submit"
                                                    class="px-3 py-1.5 rounded-md text-xs font-medium bg-sky-500 hover:bg-sky-600 text-white transition-colors">
                                                Start Work
                                            </button>
                                        </form>
                                    <?php elseif ($row['status'] === 'in_progress'): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="issue_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="admin_action" value="resolve">
                                            <button type="submit" 
                                                    class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors <?php echo $row['can_resolve'] ? 'bg-emerald-500 hover:bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500 cursor-not-allowed'; ?>"
                                                    <?php echo !$row['can_resolve'] ? 'disabled' : ''; ?>>
                                                Resolve
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-500">✓ Completed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
    const issueMaps = {};

    function toggleIssueMap(id, lat, lng) {
        const wrapper = document.getElementById('map-wrapper-' + id);
        if (!wrapper) return;
        const mapDiv = document.getElementById('map-' + id);
        const isHidden = wrapper.classList.contains('hidden');

        if (isHidden) {
            wrapper.classList.remove('hidden');

            if (!issueMaps[id]) {
                // Initialize Leaflet map
                const map = L.map(mapDiv).setView([lat, lng], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
                L.marker([lat, lng]).addTo(map);
                issueMaps[id] = map;
            } else {
                // Fix size if container was hidden before
                setTimeout(() => issueMaps[id].invalidateSize(), 50);
            }
        } else {
            wrapper.classList.add('hidden');
        }
    }
</script>

</body>
</html>
