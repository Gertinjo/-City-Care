<?php
// /-CITY-CARE/City-Main/admin.php – CityCare Admin Panel
session_start();

require_once __DIR__ . '/../database/config.php';

// -----------------------------------------------------
// REQUIRE ADMIN LOGIN (is_admin 1 or 2)
// -----------------------------------------------------
if (empty($_SESSION['user'])) {
    header('Location: /-CITY-CARE/Forms/login.php');
    exit;
}

$currentUser   = $_SESSION['user'];
$adminLevel    = (int)($currentUser['is_admin'] ?? 0);
$isAdmin       = $adminLevel >= 1;
$isSuperAdmin  = $adminLevel === 2;

if (!$isAdmin) {
    header('Location: /-CITY-CARE/Forms/login.php');
    exit;
}

$adminName = $currentUser['full_name'] ?? 'Admin';
$adminCity = $currentUser['city'] ?? null;

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
    $hasCityColumn = false;
}

// -----------------------------------------------------
// CITY FILTER OPTIONS
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

// -----------------------------------------------------
// DETERMINE SELECTED CITY (DIFFERENT FOR ADMIN vs SUPER)
// -----------------------------------------------------
// If there's no city column, we don't really filter by city.
if (!$hasCityColumn) {
    $selectedCityKey   = 'all';
    $selectedCityLabel = 'All cities (city column not found)';
} else {
    if ($isSuperAdmin) {
        // Super admin can choose any city (or "all")
        $selectedCityKey = $_GET['city'] ?? 'all';
        if (!array_key_exists($selectedCityKey, $cityOptions)) {
            $selectedCityKey = 'all';
        }
        $selectedCityLabel = $cityOptions[$selectedCityKey];
    } else {
        // Normal admin (is_admin = 1) – locked to their city
        if ($adminCity && array_key_exists($adminCity, $cityOptions)) {
            $selectedCityKey = $adminCity;
            $selectedCityLabel = $cityOptions[$adminCity];
        } else {
            // fallback if city not set / unknown
            $selectedCityKey   = 'all';
            $selectedCityLabel = 'All cities';
        }
    }
}

// -----------------------------------------------------
// HANDLE ISSUE ADMIN ACTIONS (start / resolve)
// -----------------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['admin_action'], $_POST['issue_id'])
) {
    $action  = $_POST['admin_action'];
    $issueId = (int)$_POST['issue_id'];

    try {
        if ($action === 'start') {
            $adminId = (int)$currentUser['id'];

            // Load current status
            $stmt = $db->prepare("SELECT status FROM issues WHERE id = :id");
            $stmt->execute([':id' => $issueId]);
            $issue = $stmt->fetch();

            if (!$issue) {
                $adminErr = "Issue not found.";
            } else {
                // Safely handle NULL
                $currentStatus = strtolower((string)($issue['status'] ?? ''));

                // Allow start if status is 'open' or empty/NULL (old data)
                if ($currentStatus !== '' && $currentStatus !== 'open') {
                    $adminErr = "Issue must be OPEN to start work. Current status: " . ($issue['status'] ?? '(none)');
                } else {
                    $stmt = $db->prepare("
                        UPDATE issues
                        SET 
                            status     = 'in_progress',
                            handled_by = :admin_id,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':id'        => $issueId,
                        ':admin_id'  => $adminId,
                    ]);

                    if ($stmt->rowCount() > 0) {
                        $adminMsg = "Issue #{$issueId} is now WORKING. Timer started.";
                    } else {
                        $adminErr = "Could not update this issue.";
                    }
                }
            }

        } elseif ($action === 'resolve') {
            $stmt = $db->prepare("SELECT status, created_at, updated_at FROM issues WHERE id=:id");
            $stmt->execute([':id' => $issueId]);
            $issue = $stmt->fetch();

            if (!$issue) {
                $adminErr = "Issue not found.";
            } else {
                $currentStatus = strtolower((string)($issue['status'] ?? ''));

                if ($currentStatus !== 'in_progress') {
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
                        $stmt = $db->prepare("
                            UPDATE issues
                            SET status = 'resolved',
                                updated_at = NOW()
                            WHERE id = :id
                        ");
                        $stmt->execute([':id' => $issueId]);

                        if ($stmt->rowCount() > 0) {
                            $adminMsg = "Issue #{$issueId} has been RESOLVED.";
                        } else {
                            $adminErr = "Could not resolve this issue.";
                        }
                    }
                }
            }
        }

    } catch (Throwable $e) {
        $adminErr = "Admin action failed (database error).";
        // Uncomment for debugging:
        // $adminErr .= ' ' . $e->getMessage();
    }
}

// -----------------------------------------------------
// LOAD STATS + ISSUES (respect city filter)
// -----------------------------------------------------
$adminIssues = [];
$stats = ['open' => 0, 'in_progress' => 0, 'resolved' => 0];

try {
    // Stats
    if ($hasCityColumn && $selectedCityKey !== 'all') {
        $stmt = $db->prepare("SELECT status, COUNT(*) AS c FROM issues WHERE city = :city GROUP BY status");
        $stmt->execute([':city' => $selectedCityKey]);
    } else {
        $stmt = $db->query("SELECT status, COUNT(*) AS c FROM issues GROUP BY status");
    }

    foreach ($stmt as $row) {
        $key = strtolower((string)$row['status']);
        if (isset($stats[$key])) {
            $stats[$key] = (int)$row['c'];
        }
    }

    // Issues list
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

        $statusLower = strtolower((string)$row['status']);

        $canResolve  = ($statusLower === 'in_progress' && $diffSec >= 60);
        $secondsLeft = ($statusLower === 'in_progress' && $diffSec < 60) ? (60 - $diffSec) : 0;

        $adminIssues[] = [
            'id'           => (int)$row['id'],
            'title'        => $row['title'],
            'category'     => $row['category'],
            'status'       => $row['status'],
            'city'         => $hasCityColumn ? ($row['city'] ?? null) : null,
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

// -----------------------------------------------------
// HANDLE USER ADMIN ACTIONS (edit / delete / toggle admin)
// -----------------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['user_action'], $_POST['user_id'])
) {
    $uAction = $_POST['user_action'];
    $uId     = (int)$_POST['user_id'];

    if ($uId <= 0) {
        $adminErr = "Invalid user ID.";
    } else {
        try {
            $stmt = $db->prepare("SELECT id, full_name, email, city, is_admin FROM users WHERE id = :id");
            $stmt->execute([':id' => $uId]);
            $target = $stmt->fetch();

            if (!$target) {
                $adminErr = "User not found.";
            } else {
                $isSelf = ($uId === (int)$currentUser['id']);

                if ($uAction === 'toggle_admin') {
                    if ($isSelf) {
                        $adminErr = "You cannot change your own admin status.";
                    } else {
                        // Only SUPER ADMIN should be able to promote/demote
                        if (!$isSuperAdmin) {
                            $adminErr = "Only super admins can change admin roles.";
                        } else {
                            $newIsAdmin = $target['is_admin'] ? 0 : 1;
                            $stmt = $db->prepare("UPDATE users SET is_admin = :ia WHERE id = :id");
                            $stmt->execute([
                                ':ia' => $newIsAdmin,
                                ':id' => $uId,
                            ]);
                            if ($newIsAdmin) {
                                $adminMsg = "User #{$uId} is now an ADMIN.";
                            } else {
                                $adminMsg = "User #{$uId} is no longer an admin.";
                            }
                        }
                    }

                } elseif ($uAction === 'delete_user') {
                    if ($isSelf) {
                        $adminErr = "You cannot delete your own account.";
                    } else {
                        // Only SUPER ADMIN can delete users
                        if (!$isSuperAdmin) {
                            $adminErr = "Only super admins can delete users.";
                        } else {
                            $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
                            $stmt->execute([':id' => $uId]);
                            $adminMsg = "User #{$uId} has been deleted.";
                        }
                    }

                } elseif ($uAction === 'update_user') {
                    $newName = trim($_POST['full_name'] ?? '');
                    $newEmail= trim($_POST['email'] ?? '');
                    $newCity = trim($_POST['city'] ?? '');

                    if ($newName === '' || $newEmail === '') {
                        $adminErr = "Full name and email are required to update a user.";
                    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                        $adminErr = "Please enter a valid email address.";
                    } else {
                        $stmt = $db->prepare("
                            UPDATE users
                            SET full_name = :fn, email = :em, city = :ct
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            ':fn' => $newName,
                            ':em' => $newEmail,
                            ':ct' => $newCity !== '' ? $newCity : null,
                            ':id' => $uId,
                        ]);
                        $adminMsg = "User #{$uId} has been updated.";
                    }
                }
            }

        } catch (Throwable $e) {
            $adminErr = "User action failed (database error).";
        }
    }
}

// -----------------------------------------------------
// LOAD USERS LIST FOR ADMIN TABLE
// -----------------------------------------------------
$adminUsers = [];

try {
    $stmt = $db->query("
        SELECT id, full_name, email, city, is_admin, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 100
    ");

    foreach ($stmt as $row) {
        $adminUsers[] = [
            'id'        => (int)$row['id'],
            'full_name' => $row['full_name'],
            'email'     => $row['email'],
            'city'      => $row['city'],
            'is_admin'  => (int)$row['is_admin'],
            'created_at'=> $row['created_at'],
        ];
    }
} catch (Throwable $e) {
    // optional: $adminErr = "Could not load users (database error).";
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
                <?php if ($hasCityColumn): ?>
                    · <span><?php echo htmlspecialchars($selectedCityLabel); ?></span>
                <?php elseif ($adminCity): ?>
                    · <span><?php echo htmlspecialchars($adminCity); ?></span>
                <?php endif; ?>
                <?php if ($isSuperAdmin): ?>
                    · <span class="inline-flex px-2 py-0.5 rounded-full bg-slate-900 text-white text-[10px] font-medium ml-1">
                        SUPER ADMIN
                    </span>
                <?php endif; ?>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <!-- Stats pills -->
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

            <div class="flex items-center gap-3">
                <a href="/-CITY-CARE/admin/admin_analytics.php"
                   class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-sm font-medium">
                    📊 Analytics
                </a>

                <a href="/-CITY-CARE/City-Main/index.php"
                   class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-sm font-medium">
                    ← Dashboard
                </a>
                <a href="/-CITY-CARE/admin/admin_heatmap.php"
                class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-sm font-medium">
                🌡 Heat Map
                </a>
            </div>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-8 space-y-6">

    <!-- City filter -->
    <?php if ($hasCityColumn): ?>
        <?php if ($isSuperAdmin): ?>
            <form method="get" class="flex flex-wrap items-center gap-3 mb-4 text-sm">
                <span class="text-slate-600">Filter by city:</span>
                <select name="city" onchange="this.form.submit()"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm bg-white">
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
            </form>
        <?php else: ?>
            <!-- Normal admin: just show the city they are locked to -->
            <div class="mb-4 text-sm text-slate-600">
                City filter: <span class="font-medium"><?php echo htmlspecialchars($selectedCityLabel); ?></span>
                (your assigned municipality)
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="mb-4 text-[11px] text-red-500">
            Note: "city" column not found in issues table – city filtering is disabled.
        </div>
    <?php endif; ?>

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

    <!-- ISSUES TABLE -->
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
                        $statusBadge = match (strtolower((string)$row['status'])) {
                            'open'        => ['Open', 'bg-amber-50 text-amber-700 border border-amber-200'],
                            'in_progress' => ['Working', 'bg-sky-50 text-sky-700 border border-sky-200'],
                            'resolved'    => ['Resolved', 'bg-emerald-50 text-emerald-700 border border-emerald-200'],
                            default       => [ucfirst((string)$row['status']), 'bg-slate-100 text-slate-700 border border-slate-200'],
                        };
                        $since     = $row['updated_at'] ?: $row['created_at'];
                        $hasCoords = !is_null($row['lat']) && !is_null($row['lng']);
                        $statusLower = strtolower((string)$row['status']);
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
                                <?php if ($statusLower === 'in_progress' && !$row['can_resolve']): ?>
                                    <div class="mt-2 text-amber-600 font-medium">⏳ Wait <?php echo $row['seconds_left']; ?>s</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($statusLower === 'open' || $statusLower === ''): ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="issue_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="admin_action" value="start">
                                        <button type="submit"
                                                class="px-3 py-1.5 rounded-md text-xs font-medium bg-sky-500 hover:bg-sky-600 text-white transition-colors">
                                            Start Work
                                        </button>
                                    </form>
                                <?php elseif ($statusLower === 'in_progress'): ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="issue_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="admin_action" value="resolve">
                                        <?php
                                        $btnClass = $row['can_resolve']
                                            ? 'bg-emerald-500 hover:bg-emerald-600 text-white'
                                            : 'bg-slate-200 text-slate-500 cursor-not-allowed';
                                        ?>
                                        <button type="submit"
                                                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors <?php echo $btnClass; ?>"
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

    <!-- USERS TABLE -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm mt-8">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-900">Registered Users</h2>
            <span class="text-xs text-slate-500 bg-white px-3 py-1 rounded-full border border-slate-200">
                Manage accounts · edit · promote · delete
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Full name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">City</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Created at</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (empty($adminUsers)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-sm text-slate-500">
                            No users found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($adminUsers as $u): ?>
                        <?php
                        $roleLabel = $u['is_admin'] === 2
                            ? 'Super Admin'
                            : ($u['is_admin'] === 1 ? 'Admin' : 'Citizen');

                        $roleClass =
                            $u['is_admin'] === 2 ? 'bg-purple-700 text-white' :
                            ($u['is_admin'] === 1 ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700');

                        $isSelfRow = ($u['id'] === (int)$currentUser['id']);
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors align-top">
                            <td class="px-6 py-4 text-slate-700 font-medium">#<?php echo $u['id']; ?></td>
                            <td class="px-6 py-4 text-slate-900 font-medium">
                                <?php echo htmlspecialchars($u['full_name']); ?>
                            </td>
                            <td class="px-6 py-4 text-slate-700">
                                <?php echo htmlspecialchars($u['email']); ?>
                            </td>
                            <td class="px-6 py-4 text-slate-700 text-sm">
                                <?php echo $u['city'] ? htmlspecialchars($u['city']) : '—'; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $roleClass; ?>">
                                    <?php echo $roleLabel; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                <?php echo date('Y-m-d H:i', strtotime($u['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <!-- Details dropdown toggle -->
                                <button type="button"
                                        class="px-3 py-1.5 rounded-md border border-slate-200 bg-white text-xs text-slate-700 hover:bg-slate-100 inline-flex items-center gap-1"
                                        onclick="toggleUserDetails(<?php echo $u['id']; ?>)">
                                    ▼ Details
                                </button>

                                <!-- Hidden details area -->
                                <div id="user-details-<?php echo $u['id']; ?>" class="mt-2 hidden">
                                    <div class="border border-slate-200 rounded-lg p-3 bg-slate-50 space-y-3">
                                        <!-- Edit form -->
                                        <form method="post" class="space-y-2 text-xs">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="user_action" value="update_user">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                                <div>
                                                    <label class="block text-[11px] text-slate-500 mb-1">Full name</label>
                                                    <input type="text" name="full_name"
                                                           value="<?php echo htmlspecialchars($u['full_name']); ?>"
                                                           class="w-full rounded-md border border-slate-300 px-2 py-1 text-xs">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] text-slate-500 mb-1">Email</label>
                                                    <input type="email" name="email"
                                                           value="<?php echo htmlspecialchars($u['email']); ?>"
                                                           class="w-full rounded-md border border-slate-300 px-2 py-1 text-xs">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] text-slate-500 mb-1">City</label>
                                                    <input type="text" name="city"
                                                           value="<?php echo htmlspecialchars($u['city'] ?? ''); ?>"
                                                           class="w-full rounded-md border border-slate-300 px-2 py-1 text-xs">
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between mt-2">
                                                <span class="text-[11px] text-slate-500">
                                                    User ID: <?php echo $u['id']; ?>
                                                </span>
                                                <button type="submit"
                                                        class="px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium hover:bg-slate-800">
                                                    Save changes
                                                </button>
                                            </div>
                                        </form>

                                        <div class="flex items-center justify-between pt-2 border-t border-slate-200 mt-2">
                                            <!-- Toggle admin (only for super admin, and not for self or other super admins) -->
                                            <form method="post" class="inline">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <input type="hidden" name="user_action" value="toggle_admin">
                                                <?php
                                                $canToggleAdmin = $isSuperAdmin && !$isSelfRow && $u['is_admin'] !== 2;
                                                $toggleLabel = $u['is_admin'] === 1 ? 'Remove admin' : 'Make admin';
                                                $toggleClass = $u['is_admin'] === 1
                                                    ? 'bg-slate-200 text-slate-700 hover:bg-slate-300'
                                                    : 'bg-emerald-500 text-white hover:bg-emerald-600';
                                                if (!$canToggleAdmin) {
                                                    $toggleClass = 'bg-slate-200 text-slate-500 cursor-not-allowed';
                                                }
                                                ?>
                                                <button type="submit"
                                                        class="px-3 py-1.5 rounded-md text-xs font-medium <?php echo $toggleClass; ?>"
                                                        <?php echo $canToggleAdmin ? '' : 'disabled'; ?>>
                                                    <?php echo $toggleLabel; ?>
                                                </button>
                                            </form>

                                            <!-- Delete user (only super admin, not self) -->
                                            <form method="post" class="inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <input type="hidden" name="user_action" value="delete_user">
                                                <?php
                                                $canDelete = $isSuperAdmin && !$isSelfRow;
                                                $delClass = $canDelete
                                                    ? 'bg-red-500 text-white hover:bg-red-600'
                                                    : 'bg-slate-200 text-slate-500 cursor-not-allowed';
                                                ?>
                                                <button type="submit"
                                                        class="px-3 py-1.5 rounded-md text-xs font-medium <?php echo $delClass; ?>"
                                                        <?php echo $canDelete ? '' : 'disabled'; ?>>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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

    function toggleUserDetails(id) {
        const el = document.getElementById('user-details-' + id);
        if (!el) return;
        el.classList.toggle('hidden');
    }
</script>

</body>
</html>
