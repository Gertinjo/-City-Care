<?php
// /-CITY-CARE/admin/admin_heatmap.php – CityCare Heat Map
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

// -----------------------------------------------------
// CITY OPTIONS + COORDS (center map)
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

// approximate centers for map
$cityCoords = [
    'Prishtina' => [42.6629, 21.1655],
    'Ferizaj'   => [42.3700, 21.1550],
    'Prizren'   => [42.2122, 20.7397],
    'Peja'      => [42.6590, 20.2880],
    'Gjakova'   => [42.3800, 20.4300],
    'Mitrovica' => [42.8859, 20.8667],
    'Gjilan'    => [42.4636, 21.4661],
    'Podujeva'  => [42.9100, 21.2000],
];

// fallback center for "all"
$defaultAllCenter = [42.6, 20.9];

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
// DETERMINE SELECTED CITY
//  - super admin: GET dropdown (All + any city)
//  - normal admin: locked to his city
// -----------------------------------------------------
if (!$hasCityColumn) {
    $selectedCityKey   = 'all';
    $selectedCityLabel = 'All cities (city column not found)';
} else {
    if ($isSuperAdmin) {
        $selectedCityKey = $_GET['city'] ?? 'all';
        if (!array_key_exists($selectedCityKey, $cityOptions)) {
            $selectedCityKey = 'all';
        }
        $selectedCityLabel = $cityOptions[$selectedCityKey];
    } else {
        if ($adminCity && array_key_exists($adminCity, $cityOptions)) {
            $selectedCityKey   = $adminCity;
            $selectedCityLabel = $cityOptions[$adminCity];
        } else {
            $selectedCityKey   = 'all';
            $selectedCityLabel = 'All cities';
        }
    }
}

// -----------------------------------------------------
// LOAD ISSUE POINTS FOR HEATMAP
// Heat intensity idea:
//   open        → 1.0 (red / very dirty)
//   in_progress → 0.7 (orange / medium dirty)
//   resolved    → 0.3 (green-ish / cleaner)
// -----------------------------------------------------
$heatPoints = [];
$errMsg     = null;

try {
    if ($hasCityColumn && $selectedCityKey !== 'all') {
        $stmt = $db->prepare("
            SELECT latitude, longitude, status
            FROM issues
            WHERE city = :city
              AND latitude IS NOT NULL
              AND longitude IS NOT NULL
        ");
        $stmt->execute([':city' => $selectedCityKey]);
    } else {
        $stmt = $db->query("
            SELECT latitude, longitude, status
            FROM issues
            WHERE latitude IS NOT NULL
              AND longitude IS NOT NULL
        ");
    }

    foreach ($stmt as $row) {
        $lat = (float)$row['latitude'];
        $lng = (float)$row['longitude'];
        if (!$lat && !$lng) {
            continue;
        }

        $s = strtolower((string)$row['status']);
        if     ($s === 'open')        $intensity = 1.0;
        elseif ($s === 'in_progress') $intensity = 0.75;
        elseif ($s === 'resolved')    $intensity = 0.35;
        else                          $intensity = 0.5;

        $heatPoints[] = [$lat, $lng, $intensity];
    }
} catch (Throwable $e) {
    $errMsg = "Could not load heat map data (database error).";
}

// -----------------------------------------------------
// MAP CENTER + ZOOM
// -----------------------------------------------------
if ($selectedCityKey === 'all') {
    [$centerLat, $centerLng] = $defaultAllCenter;
    $startZoom = 8.5;
} else {
    [$centerLat, $centerLng] = $cityCoords[$selectedCityKey] ?? $defaultAllCenter;
    $startZoom = 13; // closer for single city
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Heat Map</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Leaflet.heat plugin -->
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

    <style>
        #heatMap {
            height: 520px;
        }
        .heat-legend-bar {
            background: linear-gradient(to right, #22c55e, #eab308, #ef4444);
        }
        .heat-empty-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
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
                        CityCare Heat Map
                    </h1>
                    <p class="text-xs text-slate-500">
                        Visual pollution map · red = dirty · green = clean
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
            <a href="/-CITY-CARE/admin/admin_analytics.php"
               class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-sm font-medium">
                ← Analytics
            </a>
            <a href="/-CITY-CARE/admin/admin.php"
               class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-sm font-medium">
                Admin Panel
            </a>
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-8 space-y-5">

    <!-- City filter -->
    <?php if ($hasCityColumn): ?>
        <?php if ($isSuperAdmin): ?>
            <form method="get" class="flex flex-wrap items-center gap-3 text-sm mb-1">
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
                    Showing heat map for: <?php echo htmlspecialchars($selectedCityLabel); ?>
                </span>
            </form>
        <?php else: ?>
            <div class="text-sm text-slate-600 mb-1">
                City: <span class="font-medium"><?php echo htmlspecialchars($selectedCityLabel); ?></span>
                (locked to your assigned city)
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-[11px] text-red-500 mb-1">
            Note: "city" column not found in issues table – city-based filtering is disabled.
        </div>
    <?php endif; ?>

    <?php if ($errMsg): ?>
        <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm mb-2">
            <?php echo htmlspecialchars($errMsg); ?>
        </div>
    <?php endif; ?>

    <!-- Info + Legend -->
    <section class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm text-sm flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-sm font-semibold mb-1">How dirty is this area?</h2>
            <p class="text-xs text-slate-500 max-w-md">
                Each report adds heat to the map. Many open reports = hot red zones.
                Resolved reports still contribute a little intensity, to show history.
            </p>
        </div>
        <div class="flex flex-col gap-1 text-xs text-slate-600">
            <div class="flex items-center gap-2">
                <div class="heat-legend-bar h-2 w-40 rounded-full border border-slate-200"></div>
                <div class="flex justify-between w-40 text-[10px]">
                    <span>Clean</span>
                    <span>Medium</span>
                    <span>Dirty</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1">
                    <span class="w-3 h-3 rounded-full bg-green-500"></span> Low reports
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-3 h-3 rounded-full bg-yellow-400"></span> Some reports
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-3 h-3 rounded-full bg-red-500"></span> Many reports
                </span>
            </div>
        </div>
    </section>

    <!-- HEAT MAP -->
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden relative">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold">Heat Map</h2>
            <span class="text-[11px] text-slate-400">
                Red = many open issues · zoom in for more detail
            </span>
        </div>
        <div class="relative">
            <div id="heatMap" class="bg-slate-100"></div>
            <?php if (empty($heatPoints)): ?>
                <div class="heat-empty-overlay">
                    <div class="px-4 py-2 rounded-full bg-white/90 border border-slate-200 text-xs text-slate-500 shadow-sm">
                        No reports with coordinates for this filter yet.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const centerLat   = <?php echo json_encode($centerLat); ?>;
    const centerLng   = <?php echo json_encode($centerLng); ?>;
    const startZoom   = <?php echo json_encode($startZoom); ?>;
    const heatPoints  = <?php echo json_encode($heatPoints, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

    const map = L.map('heatMap').setView([centerLat, centerLng], startZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    if (heatPoints.length > 0) {
        L.heatLayer(heatPoints, {
            radius: 45,       // bigger blobs = more visible
            blur: 28,
            maxZoom: 18,
            minOpacity: 0.35, // keep visible even when zoomed out
            // custom gradient: green -> yellow -> orange -> red
            gradient: {
                0.0: '#22c55e',  // green-500
                0.4: '#eab308',  // yellow-500
                0.7: '#f97316',  // orange-500
                1.0: '#ef4444'   // red-500
            }
        }).addTo(map);
    }s
});
</script>

</body>
</html>
