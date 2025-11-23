<?php
// /-CITY-CARE/admin/admin_analytics.php – CityCare Analytics
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

$errMsg = null;

// -----------------------------------------------------
// DB CONNECT + CHECK IF `city` COLUMN EXISTS
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
// DETERMINE SELECTED CITY
//  - super admin: dropdown (All + any city)
//  - normal admin: locked to his city
// -----------------------------------------------------
if (!$hasCityColumn) {
    $selectedCityKey   = 'all';
    $selectedCityLabel = 'All cities (city column not found)';
} else {
    if ($isSuperAdmin) {
        // SUPER ADMIN – can choose
        $selectedCityKey = $_GET['city'] ?? 'all';
        if (!array_key_exists($selectedCityKey, $cityOptions)) {
            $selectedCityKey = 'all';
        }
        $selectedCityLabel = $cityOptions[$selectedCityKey];
    } else {
        // NORMAL ADMIN – locked to his city
        if ($adminCity && array_key_exists($adminCity, $cityOptions)) {
            $selectedCityKey   = $adminCity;
            $selectedCityLabel = $cityOptions[$adminCity];
        } else {
            // fallback if user has no city configured
            $selectedCityKey   = 'all';
            $selectedCityLabel = 'All cities';
        }
    }
}

// -----------------------------------------------------
// LOAD ANALYTICS DATA
// -----------------------------------------------------
$stats         = ['total' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0];
$categoryStats = [];
$cityBreakdown = []; // only meaningful for SUPER ADMIN with "all"
$recentIssues  = [];

try {
    // 1) BASIC STATUS STATS
    if ($hasCityColumn && $selectedCityKey !== 'all') {
        $stmt = $db->prepare("
            SELECT LOWER(status) AS s, COUNT(*) AS c
            FROM issues
            WHERE city = :city
            GROUP BY LOWER(status)
        ");
        $stmt->execute([':city' => $selectedCityKey]);
    } else {
        $stmt = $db->query("
            SELECT LOWER(status) AS s, COUNT(*) AS c
            FROM issues
            GROUP BY LOWER(status)
        ");
    }

    $total = 0;
    foreach ($stmt as $row) {
        $s = $row['s'] ?? '';
        $count = (int)$row['c'];
        $total += $count;
        if ($s === 'open')        $stats['open']        = $count;
        if ($s === 'in_progress') $stats['in_progress'] = $count;
        if ($s === 'resolved')    $stats['resolved']    = $count;
    }
    $stats['total'] = $total;

    // 2) CATEGORY STATS (TOP 6)
    if ($hasCityColumn && $selectedCityKey !== 'all') {
        $stmt = $db->prepare("
            SELECT category, COUNT(*) AS c
            FROM issues
            WHERE city = :city
            GROUP BY category
            ORDER BY c DESC
            LIMIT 6
        ");
        $stmt->execute([':city' => $selectedCityKey]);
    } else {
        $stmt = $db->query("
            SELECT category, COUNT(*) AS c
            FROM issues
            GROUP BY category
            ORDER BY c DESC
            LIMIT 6
        ");
    }

    foreach ($stmt as $row) {
        $categoryStats[] = [
            'category' => $row['category'] ?: 'Other',
            'count'    => (int)$row['c'],
        ];
    }

    // 3) CITY BREAKDOWN (ONLY for SUPER ADMIN, when "all" selected and city column exists)
    if ($isSuperAdmin && $hasCityColumn && $selectedCityKey === 'all') {
        $stmt = $db->query("
            SELECT city,
                   COUNT(*) AS total,
                   SUM(LOWER(status) = 'open')        AS open_count,
                   SUM(LOWER(status) = 'in_progress') AS in_progress_count,
                   SUM(LOWER(status) = 'resolved')    AS resolved_count
            FROM issues
            GROUP BY city
            ORDER BY total DESC
        ");

        foreach ($stmt as $row) {
            $cityBreakdown[] = [
                'city'       => $row['city'] ?: 'Unknown',
                'total'      => (int)$row['total'],
                'open'       => (int)$row['open_count'],
                'in_progress'=> (int)$row['in_progress_count'],
                'resolved'   => (int)$row['resolved_count'],
            ];
        }
    }

    // 4) RECENT ISSUES LIST (last 10)
    if ($hasCityColumn && $selectedCityKey !== 'all') {
        $stmt = $db->prepare("
            SELECT id, title, category, city, status, location_text, created_at
            FROM issues
            WHERE city = :city
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([':city' => $selectedCityKey]);
    } else {
        $stmt = $db->query("
            SELECT id, title, category, city, status, location_text, created_at
            FROM issues
            ORDER BY created_at DESC
            LIMIT 10
        ");
    }

    foreach ($stmt as $row) {
        $recentIssues[] = [
            'id'       => (int)$row['id'],
            'title'    => $row['title'],
            'category' => $row['category'],
            'city'     => $hasCityColumn ? ($row['city'] ?? null) : null,
            'status'   => $row['status'],
            'location' => $row['location_text'] ?? '',
            'time'     => date('d M Y H:i', strtotime($row['created_at'])),
        ];
    }

} catch (Throwable $e) {
    $errMsg = "Could not load analytics (database error).";
}

// -----------------------------------------------------
// MAP CATEGORIES → COLORS (used for circle + recent list)
// -----------------------------------------------------
$segmentColors = [
    'Streetlight'    => '#4CAF50', // green
    'Road / Pothole' => '#2196F3', // blue
    'Waste / Trash'  => '#FFC107', // yellow
    'Water / Sewage' => '#00BCD4', // cyan
    'Noise / Safety' => '#E91E63', // pink
    'Other'          => '#9E9E9E', // grey
];

// Build the segments array for JS (pie chart)
$circleSegments = [];
foreach ($categoryStats as $row) {
    $catName = $row['category'];
    $color   = $segmentColors[$catName] ?? '#9CA3AF'; // default grey
    $circleSegments[] = [
        'name'  => $catName,
        'color' => $color,
        'count' => $row['count'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* Donut / category wheel */
        .category-wheel-wrapper {
            position: relative;
            width: 180px;
            height: 180px;
        }
        .category-wheel-wrapper svg {
            width: 100%;
            height: 100%;
            border-radius: 50%;
        }
        .category-wheel-wrapper::after {
            content: "";
            position: absolute;
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 2px 6px rgba(0,0,0,0.18);
            pointer-events: none;
        }
        .category-wheel-label {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 11px;
            text-align: center;
            color: #4b5563;
            pointer-events: none;
            line-height: 1.25;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">

<header class="border-b border-slate-200 bg-white sticky top-0 z-50">
    <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded-lg bg-emerald-500 flex items-center justify-center text-white font-bold">
                    C
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900">
                        CityCare Analytics
                    </h1>
                    <p class="text-xs text-slate-500">
                        Overview of reports and status
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
            <a href="/-CITY-CARE/admin/admin.php"
               class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-sm font-medium">
                ← Admin Panel
            </a>
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-8 space-y-6">

    <!-- City filter bar -->
    <?php if ($hasCityColumn): ?>
        <?php if ($isSuperAdmin): ?>
            <form method="get" class="flex flex-wrap items-center gap-3 mb-2 text-sm">
                <span class="text-slate-600">City:</span>
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
                    Showing analytics for: <?php echo htmlspecialchars($selectedCityLabel); ?>
                </span>
            </form>
        <?php else: ?>
            <div class="text-sm text-slate-600 mb-2">
                City: <span class="font-medium"><?php echo htmlspecialchars($selectedCityLabel); ?></span>
                (you are locked to your assigned city)
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-[11px] text-red-500 mb-2">
            Note: "city" column not found in issues table – city-based analytics are disabled.
        </div>
    <?php endif; ?>

    <?php if ($errMsg): ?>
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
            <?php echo htmlspecialchars($errMsg); ?>
        </div>
    <?php endif; ?>

    <!-- Top stats -->
    <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <p class="text-xs text-slate-500">Total reports</p>
            <p class="mt-2 text-2xl font-semibold"><?php echo $stats['total']; ?></p>
        </div>
        <div class="bg-white border border-amber-200 rounded-2xl p-4 shadow-sm">
            <p class="text-xs text-amber-700">Open</p>
            <p class="mt-2 text-2xl font-semibold text-amber-700"><?php echo $stats['open']; ?></p>
        </div>
        <div class="bg-white border border-sky-200 rounded-2xl p-4 shadow-sm">
            <p class="text-xs text-sky-700">Working</p>
            <p class="mt-2 text-2xl font-semibold text-sky-700"><?php echo $stats['in_progress']; ?></p>
        </div>
        <div class="bg-white border border-emerald-200 rounded-2xl p-4 shadow-sm">
            <p class="text-xs text-emerald-700">Resolved</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?php echo $stats['resolved']; ?></p>
        </div>
    </section>

    <!-- Category + city breakdown -->
    <section class="grid md:grid-cols-2 gap-5">
        <!-- Category stats + Circle -->
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <h2 class="text-sm font-semibold mb-3">Top categories</h2>

            <?php if (empty($categoryStats)): ?>
                <p class="text-sm text-slate-500">No data yet.</p>
            <?php else: ?>
                <div class="flex flex-col items-center md:flex-row md:items-start gap-4">
                    <!-- Donut chart -->
                    <div class="category-wheel-wrapper">
                        <svg id="categoryWheelSvg" viewBox="0 0 180 180"></svg>
                        <div class="category-wheel-label">
                            <?php echo htmlspecialchars($selectedCityLabel); ?><br>
                            <span class="font-semibold"><?php echo $stats['total']; ?></span> reports
                        </div>
                    </div>

                    <!-- Legend / list -->
                    <ul class="space-y-2 text-sm w-full">
                        <?php foreach ($categoryStats as $row):
                            $catName = $row['category'];
                            $color   = $segmentColors[$catName] ?? '#9CA3AF';
                        ?>
                            <li class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-3 h-3 rounded-full"
                                          style="background: <?php echo htmlspecialchars($color); ?>"></span>
                                    <span><?php echo htmlspecialchars($catName); ?></span>
                                </div>
                                <span class="text-slate-500"><?php echo $row['count']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- City breakdown (super admin + all cities) -->
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <h2 class="text-sm font-semibold mb-3">
                <?php echo $isSuperAdmin && $selectedCityKey === 'all'
                    ? 'Cities breakdown'
                    : 'Info'; ?>
            </h2>

            <?php if ($isSuperAdmin && $selectedCityKey === 'all' && !empty($cityBreakdown)): ?>
                <div class="max-h-64 overflow-auto text-sm">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-2 py-1 text-left">City</th>
                            <th class="px-2 py-1 text-right">Total</th>
                            <th class="px-2 py-1 text-right text-amber-700">Open</th>
                            <th class="px-2 py-1 text-right text-sky-700">Work</th>
                            <th class="px-2 py-1 text-right text-emerald-700">Resolved</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cityBreakdown as $c): ?>
                            <tr class="border-b border-slate-100">
                                <td class="px-2 py-1"><?php echo htmlspecialchars($c['city']); ?></td>
                                <td class="px-2 py-1 text-right"><?php echo $c['total']; ?></td>
                                <td class="px-2 py-1 text-right text-amber-700"><?php echo $c['open']; ?></td>
                                <td class="px-2 py-1 text-right text-sky-700"><?php echo $c['in_progress']; ?></td>
                                <td class="px-2 py-1 text-right text-emerald-700"><?php echo $c['resolved']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500">
                    This panel will show a list of all cities and their numbers when a super admin selects
                    <strong>All cities</strong>.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Recent issues -->
    <section class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
        <h2 class="text-sm font-semibold mb-3">Recent reports</h2>
        <?php if (empty($recentIssues)): ?>
            <p class="text-sm text-slate-500">No recent reports.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100 text-sm">
                <?php foreach ($recentIssues as $i): 
                    $catName = $i['category'] ?: 'Other';
                    $color   = $segmentColors[$catName] ?? '#9CA3AF';
                ?>
                    <li class="py-2 flex justify-between gap-4">
                        <div class="flex gap-2">
                            <!-- small category color dot -->
                            <span class="mt-1 inline-block w-2.5 h-2.5 rounded-full flex-shrink-0"
                                  style="background: <?php echo htmlspecialchars($color); ?>"></span>
                            <div>
                                <div class="font-medium"><?php echo htmlspecialchars($i['title']); ?></div>
                                <div class="text-xs text-slate-500">
                                    <?php if ($i['city']): ?>
                                        <?php echo htmlspecialchars($i['city']); ?> ·
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($i['category']); ?>
                                    <?php if ($i['location']): ?>
                                        · <?php echo htmlspecialchars($i['location']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <div><?php echo htmlspecialchars($i['time']); ?></div>
                            <div>
                                <?php
                                $s = strtolower((string)$i['status']);
                                if ($s === 'open') echo 'Open';
                                elseif ($s === 'in_progress') echo 'Working';
                                elseif ($s === 'resolved') echo 'Resolved';
                                else echo htmlspecialchars($i['status']);
                                ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</main>

<script>
// Build proportional donut chart using SVG paths
(function () {
    const svg = document.getElementById('categoryWheelSvg');
    if (!svg) return;

    const segments = <?php echo json_encode($circleSegments); ?>;
    if (!segments || !segments.length) return;

    let total = 0;
    segments.forEach(s => { total += s.count; });
    if (total <= 0) return;

    const cx = 90, cy = 90, r = 80;
    let currentAngle = -90; // start at top

    function polarToCartesian(cx, cy, r, angleDeg) {
        const rad = (Math.PI / 180) * angleDeg;
        return {
            x: cx + r * Math.cos(rad),
            y: cy + r * Math.sin(rad)
        };
    }

    segments.forEach(seg => {
        const angle = 360 * (seg.count / total);
        const startAngle = currentAngle;
        const endAngle   = currentAngle + angle;

        const start = polarToCartesian(cx, cy, r, startAngle);
        const end   = polarToCartesian(cx, cy, r, endAngle);
        const largeArcFlag = angle > 180 ? 1 : 0;

        const d = [
            "M", cx, cy,
            "L", start.x, start.y,
            "A", r, r, 0, largeArcFlag, 1, end.x, end.y,
            "Z"
        ].join(" ");

        const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        path.setAttribute("d", d);
        path.setAttribute("fill", seg.color);
        path.setAttribute("stroke", "#ffffff");
        path.setAttribute("stroke-width", "2");
        svg.appendChild(path);

        currentAngle = endAngle;
    });
})();
</script>

</body>
</html>
