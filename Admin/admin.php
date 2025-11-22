<?php
// /-CITY-CARE/City-Main/admin.php – CityCare Admin Panel
session_start();

// config.php is in City-Main/database
require_once __DIR__ . '/../database/config.php';

// -----------------------------------------------------
// REQUIRE ADMIN LOGIN (real users, not ?as=admin)
// -----------------------------------------------------
if (empty($_SESSION['user']) || (int)($_SESSION['user']['is_admin'] ?? 0) !== 1) {
    header('Location: /-CITY-CARE/Forms/login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$adminName   = $currentUser['full_name'] ?? 'Admin';
$adminCity   = $currentUser['city'] ?? null;

$adminMsg = null;
$adminErr = null;

// -----------------------------------------------------
// CONNECT & CHECK IF `city` COLUMN EXISTS
// -----------------------------------------------------
try {
    $db = getDB();
} catch (Throwable $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

$hasCityColumn = false;
try {
    $stmt = $db->query("SHOW COLUMNS FROM issues LIKE 'city'");
    if ($stmt && $stmt->fetch()) {
        $hasCityColumn = true;
    }
} catch (Throwable $e) {
    // ignore, just assume no city column
    $hasCityColumn = false;
}

// -----------------------------------------------------
// CITY FILTER OPTIONS (used only if city column exists)
// -----------------------------------------------------
$cityOptions = [
    'all'       => 'All cities',
    'Prishtina' => 'Prishtina',
    'Prizren'   => 'Prizren',
    'Peja'      => 'Peja',
    'Gjakova'   => 'Gjakova',
    'Mitrovica' => 'Mitrovica',
    'Ferizaj'   => 'Ferizaj',
    'Gjilan'    => 'Gjilan',
    'Vushtrri'  => 'Vushtrri',
    'Podujeva'  => 'Podujeva',
    'Suhareka'  => 'Suhareka',
    'Rahovec'   => 'Rahovec',
    'Istog'     => 'Istog',
    'Kamenica'  => 'Kamenica',
    'Malisheva' => 'Malisheva',
    'Skenderaj' => 'Skenderaj',
    'Dragash'   => 'Dragash',
    'Kukes'     => 'Kukës',
    'Tropoje'   => 'Tropojë',
    'Shkoder'   => 'Shkodër',
    'Puke'      => 'Pukë',
    'Lezhe'     => 'Lezhë',
    'Has'       => 'Has',
];

$selectedCityKey = $_GET['city'] ?? 'all';
if (!array_key_exists($selectedCityKey, $cityOptions)) {
    $selectedCityKey = 'all';
}
$selectedCityLabel = $cityOptions[$selectedCityKey];

// If city column does NOT exist, we force "all"
if (!$hasCityColumn) {
    $selectedCityKey   = 'all';
    $selectedCityLabel = 'All cities (city column not found)';
}

// -----------------------------------------------------
// HANDLE ADMIN ACTIONS (start / resolve)
// -----------------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['admin_action'], $_POST['issue_id'])
) {
    $action  = $_POST['admin_action'];
    $issueId = (int)$_POST['issue_id'];

    try {
        if ($action === 'start') {
            $stmt = $db->prepare("UPDATE issues SET status='in_progress', updated_at=NOW() WHERE id=:id AND status='open'");
            $stmt->execute([':id' => $issueId]);

            if ($stmt->rowCount() > 0) {
                $adminMsg = "Issue #{$issueId} is now WORKING. Timer started.";
            } else {
                $adminErr = "Cannot start work on this issue.";
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

// -----------------------------------------------------
// LOAD STATS + ISSUES (uses city filter if available)
// -----------------------------------------------------
$adminIssues = [];
$stats = ['open' => 0, 'in_progress' => 0, 'resolved' => 0];

try {
    // stats
    if ($hasCityColumn && $selectedCityKey !== 'all') {
        $stmt = $db->prepare("SELECT status, COUNT(*) AS c FROM issues WHERE city = :city GROUP BY status");
        $stmt->execute([':city' => $selectedCityKey]);
    } else {
        $stmt = $db->query("SELECT status, COUNT(*) AS c FROM issues GROUP BY status");
    }

    foreach ($stmt as $row) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = (int)$row['c'];
        }
    }

    // issues list
    if ($hasCityColumn && $selectedCityKey !== 'all') {
        $stmt = $db->prepare("
            SELECT id, title, category, city, status, location_text, latitude, longitude, created_at, updated_at
            FROM issues
            WHERE city = :city
            ORDER BY created_at DESC
            LIMIT 200
        ");
        $stmt->execute([':city' => $selectedCityKey]);
    } else {
        // if no city column, don't select it
        if ($hasCityColumn) {
            $stmt = $db->query("
                SELECT id, title, category, city, status, location_text, latitude, longitude, created_at, updated_at
                FROM issues
                ORDER BY created_at DESC
                LIMIT 200
            ");
        } else {
            $stmt = $db->query("
                SELECT id, title, category, status, location_text, latitude, longitude, created_at, updated_at
                FROM issues
                ORDER BY created_at DESC
                LIMIT 200
            ");
        }
    }

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
            'city'         => $hasCityColumn ? $row['city'] : null,
            'location'     => $row['location_text'],
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

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        @keyframes slideDown {
            from {opacity:0;transform:translateY(-6px);}
            to   {opacity:1;transform:translateY(0);}
        }
        .animate-slide-down { animation: slideDown 0.25s ease-out; }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .35rem .7rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">

<!-- HEADER -->
<header class="border-b border-slate-200 bg-white sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded-lg bg-emerald-500 flex items-center justify-center text-white font-bold">
                    C
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900">
                        CityCare Admin
                    </h1>
                    <p class="text-xs text-slate-500">
                        Managing incoming reports across municipalities
                    </p>
                </div>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Hi, <span class="font-semibold"><?php echo htmlspecialchars($adminName); ?></span>
                <?php if ($adminCity): ?> · <?php echo htmlspecialchars($adminCity); ?><?php endif; ?>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div class="hidden md:flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-medium">
                    Open: <?php echo $stats['open']; ?>
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-medium">
                    Working: <?php echo $stats['in_progress']; ?>
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">
                    Resolved: <?php echo $stats['resolved']; ?>
                </span>
            </div>
            <a href="/-CITY-CARE/City-Main/index.php"
               class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-sm font-medium">
                ← Dashboard
            </a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-8 space-y-6">

    <!-- City filter (works only if city column exists) -->
    <form method="get" class="flex flex-wrap items-center gap-3 mb-4 text-sm">
        <span class="text-slate-600">Filter by city:</span>
        <select name="city" onchange="this.form.submit()"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm bg-white"
                <?php echo $hasCityColumn ? '' : 'disabled'; ?>>
            <?php foreach ($cityOptions as $key => $label): ?>
                <option value="<?php echo htmlspecialchars($key); ?>"
                    <?php echo $key === $selectedCityKey ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="text-[11px] text-slate-500">
            Currently showing: <?php echo htmlspecialchars($selectedCityLabel); ?>
        </span>
        <?php if (!$hasCityColumn): ?>
            <span class="text-[11px] text-red-500">
                (Note: "city" column not found in issues table – filter disabled)
            </span>
        <?php endif; ?>
    </form>

    <!-- alerts -->
    <?php if ($adminMsg): ?>
        <div class="mb-4 animate-slide-down flex items-center gap-3 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
            <span><?php echo htmlspecialchars($adminMsg); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($adminErr): ?>
        <div class="mb-4 animate-slide-down flex items-center gap-3 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
            <span><?php echo htmlspecialchars($adminErr); ?></span>
        </div>
    <?php endif; ?>

    <!-- table -->
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
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">City</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Timeline</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (empty($adminIssues)): ?>
                    <tr><td colspan="7" class="px-6 py-10 text-center text-sm text-slate-500">
                        No reports found.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($adminIssues as $row): ?>
                        <?php
                        $statusBadge = match ($row['status']) {
                            'open'        => ['Open', 'bg-amber-50 text-amber-700 border border-amber-200'],
                            'in_progress' => ['Working', 'bg-sky-50 text-sky-700 border border-sky-200'],
                            'resolved'    => ['Resolved', 'bg-emerald-50 text-emerald-700 border border-emerald-200'],
                            default       => [ucfirst($row['status']), 'bg-slate-100 text-slate-700 border border-slate-200'],
                        };
                        $since     = $row['updated_at'] ?: $row['created_at'];
                        $hasCoords = !is_null($row['lat']) && !is_null($row['lng']);
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors align-top">
                            <td class="px-6 py-4 text-slate-700 font-medium">#<?php echo $row['id']; ?></td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-900"><?php echo htmlspecialchars($row['title']); ?></div>
                                <?php if ($hasCoords): ?>
                                    <button type="button"
                                            class="mt-2 inline-flex items-center text-xs text-sky-600 hover:text-sky-700 gap-1"
                                            onclick="toggleIssueMap(<?php echo $row['id']; ?>, <?php echo $row['lat']; ?>, <?php echo $row['lng']; ?>)">
                                        ▼ View map
                                    </button>
                                    <div id="map-wrapper-<?php echo $row['id']; ?>" class="mt-2 hidden">
                                        <div id="map-<?php echo $row['id']; ?>" class="h-40 rounded-lg border border-slate-200"></div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-slate-700 text-sm">
                                <?php echo $row['city'] !== null ? htmlspecialchars($row['city']) : '—'; ?>
                            </td>
                            <td class="px-6 py-4 text-slate-700 text-sm">
                                <?php echo htmlspecialchars($row['category']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="status-badge <?php echo $statusBadge[1]; ?>">
                                    <?php echo $statusBadge[0]; ?>
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
                const map = L.map(mapDiv).setView([lat, lng], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
                L.marker([lat, lng]).addTo(map);
                issueMaps[id] = map;
            } else {
                setTimeout(() => issueMaps[id].invalidateSize(), 50);
            }
        } else {
            wrapper.classList.add('hidden');
        }
    }
</script>

</body>
</html>
