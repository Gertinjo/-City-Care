<?php
// CityCare - Smart Reporting Platform (city-based dashboard)
session_start();

require_once __DIR__ . '/../database/config.php';

// -----------------------------------------------------
// CURRENT USER & CITY
// -----------------------------------------------------
$currentUser = $_SESSION['user'] ?? null;

$userId   = $currentUser['id']        ?? null;
$userName = $currentUser['full_name'] ?? 'Guest';
$userCity = $currentUser['city']      ?? 'Prishtina';   // fallback if not logged in

$cityName = $userCity;  // used in UI

// Simple city → coordinates (approx)
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

[$mapLat, $mapLng] = $cityCoords[$userCity] ?? $cityCoords['Prishtina'];

// -----------------------------------------------------
// PAGE ROUTER
// -----------------------------------------------------
$page           = $_GET['page'] ?? 'dashboard';
$reportError    = null;
$reportSuccess  = isset($_GET['reported']) ? true : false;

// -----------------------------------------------------
// HANDLE REPORT FORM SUBMIT
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'report') {
    $title          = trim($_POST['title'] ?? '');
    $categorySelect = trim($_POST['category'] ?? '');
    $categoryOther  = trim($_POST['category_other'] ?? '');
    $location       = trim($_POST['location'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $anonymous      = isset($_POST['anonymous']) ? 1 : 0;
    $lat            = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
    $lng            = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;

    // If "Other" selected, use the custom text as category
    $category = $categorySelect;
    if ($categorySelect === 'Other' && $categoryOther !== '') {
        $category = $categoryOther;
    }

    if ($title === '' || $category === '') {
        $reportError = 'Title and category are required.';
    } else {
        $photoPath = null;

        // ---- PHOTO IS NOW REQUIRED ----
        if (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $reportError = 'A photo is required for every report.';
        } else {
            $allowedExt = ['jpg', 'jpeg', 'png'];
            $uploadDir  = __DIR__ . '/uploads/issues';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $originalName = $_FILES['photo']['name'];
            $tmpPath      = $_FILES['photo']['tmp_name'];
            $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                $reportError = 'Only JPG and PNG images are allowed.';
            } else {
                $newName = 'issue_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $target  = $uploadDir . '/' . $newName;

                if (!move_uploaded_file($tmpPath, $target)) {
                    $reportError = 'Failed to upload the photo. Please try again.';
                } else {
                    // web path
                    $photoPath = 'uploads/issues/' . $newName;
                }
            }
        }

        // Only insert into DB if no errors (title/category + photo ok)
        if ($reportError === null) {
            try {
                $db = getDB();

                $stmt = $db->prepare("
                    INSERT INTO issues 
                        (title, category, city, status, location_text, latitude, longitude, description, photo_path, is_anonymous, created_by) 
                    VALUES 
                        (:title, :category, :city, 'open', :location_text, :latitude, :longitude, :description, :photo_path, :is_anonymous, :created_by)
                ");

                $stmt->execute([
                    ':title'         => $title,
                    ':category'      => $category,
                    ':city'          => $userCity,
                    ':location_text' => $location !== '' ? $location : null,
                    ':latitude'      => $lat,
                    ':longitude'     => $lng,
                    ':description'   => $description !== '' ? $description : null,
                    ':photo_path'    => $photoPath,
                    ':is_anonymous'  => $anonymous,
                    ':created_by'    => $userId,
                ]);

                header('Location: index.php?page=dashboard&reported=1');
                exit;
            } catch (Throwable $e) {
                $reportError = 'Could not save your report (database error).';
            }
        }
    }
}

// -----------------------------------------------------
// LOAD DATA FOR DASHBOARD (FILTER BY USER CITY)
// -----------------------------------------------------
$stats        = [
    'open'              => 0,
    'in_progress'       => 0,
    'resolved'          => 0,
    'city_health_score' => 100,
];
$issues       = [];
$issuesForMap = [];

try {
    $db = getDB();

    // stats for this city
    $stmt = $db->prepare("SELECT status, COUNT(*) AS c FROM issues WHERE city = :city GROUP BY status");
    $stmt->execute([':city' => $userCity]);

    foreach ($stmt as $row) {
        if ($row['status'] === 'open') {
            $stats['open'] = (int)$row['c'];
        } elseif ($row['status'] === 'in_progress') {
            $stats['in_progress'] = (int)$row['c'];
        } elseif ($row['status'] === 'resolved') {
            $stats['resolved'] = (int)$row['c'];
        }
    }

    // simple city health score based on open / in_progress counts
    $total = $stats['open'] + $stats['in_progress'] + $stats['resolved'];
    if ($total === 0) {
        $stats['city_health_score'] = 100;
    } else {
        $penalty = $stats['open'] * 3 + $stats['in_progress'] * 1;
        $score   = 100 - min(75, $penalty);
        if ($score < 10) $score = 10;
        $stats['city_health_score'] = $score;
    }

    // latest issues for this city
    $stmt = $db->prepare("
        SELECT id, title, category, status, location_text, created_at
        FROM issues
        WHERE city = :city
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([':city' => $userCity]);

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

    // issues with coordinates for map
    // IMPORTANT: hide resolved issues from map after 10 minutes
    $stmt = $db->prepare("
        SELECT id, title, category, status, location_text, latitude, longitude, photo_path, created_at, updated_at
        FROM issues
        WHERE city = :city
          AND latitude IS NOT NULL
          AND longitude IS NOT NULL
          AND (
                status <> 'resolved'
                OR updated_at IS NULL
                OR updated_at >= (NOW() - INTERVAL 10 MINUTE)
              )
        ORDER BY created_at DESC
        LIMIT 200
    ");
    $stmt->execute([':city' => $userCity]);

    foreach ($stmt as $row) {
        $issuesForMap[] = [
            'id'       => (int)$row['id'],
            'title'    => $row['title'],
            'category' => $row['category'],
            'status'   => $row['status'],
            'location' => $row['location_text'],
            'lat'      => (float)$row['latitude'],
            'lng'      => (float)$row['longitude'],
            'photo'    => $row['photo_path'], // used in popup
        ];
    }
} catch (Throwable $e) {
    // leave arrays empty if DB fails
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
            <div class="hidden sm:flex flex-col items-end text-xs sm:text-sm">
                <span class="font-medium">Hi, <?php echo htmlspecialchars($userName); ?> · <?php echo htmlspecialchars($cityName); ?></span>
                <span class="text-slate-500 dark:text-slate-400 text-[11px]">
                    City Health: <?php echo $stats['city_health_score']; ?>/100
                </span>
            </div>

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
                    Citizen Panel
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
                <a href="profile.php"
                   class="flex items-center justify-between px-3 py-2 rounded-xl <?php echo $page === 'profile' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>Profile</span>
                    <span class="text-xs text-slate-400"><?php  echo htmlspecialchars($userName);?></span>
                </a>
                <a href="logout.php"
                class="flex items-center justify-between px-3 py-2 rounded-xl hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-300">
                <span>Log out</span>
                <span class="text-xs text-slate-400">See you soon!</span>
</a>

            </nav>

            <div class="text-[11px] text-slate-400 dark:text-slate-500">
                Help your city by reporting problems you see in everyday life:
                broken lights, potholes, trash, vandalism and more.
            </div>
        </aside>

        <!-- MAIN -->
        <section class="space-y-6">

            <?php if ($page === 'dashboard'): ?>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-semibold">Dashboard · <?php echo htmlspecialchars($cityName); ?></h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Track reported issues and their status in your city.
                        </p>
                        <?php if ($reportSuccess): ?>
                            <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                ✅ Your report has been submitted successfully.
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
                            <span class="text-xs text-slate-400">latest in <?php echo htmlspecialchars($cityName); ?></span>
                        </div>
                        <?php if (empty($issues)): ?>
                            <div class="px-4 py-6 text-sm text-slate-500 dark:text-slate-400">
                                No reports yet for this city. Be the first to report an issue.
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
                                <h2 class="text-sm font-semibold"><?php echo htmlspecialchars($cityName); ?> Map</h2>
                                <span class="text-[11px] text-slate-400">hover markers for details</span>
                            </div>
                            <div id="cityMap" class="h-56 bg-slate-100 dark:bg-slate-900 rounded-b-2xl"></div>
                        </div>

                        <div class="bg-gradient-to-br from-slate-900 to-slate-800 glass-card rounded-2xl p-4 text-slate-100 shadow-md">
                            <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">Prototype Note</div>
                            <p class="text-sm mb-2">
                                Each open or recently resolved report appears as a marker on the map.
                                Resolved issues disappear from the map after 10 minutes.
                            </p>
                            <ul class="text-xs space-y-1 text-slate-200">
                                <li>• Red = Open, Orange = Working, Green = Recently Resolved</li>
                                <li>• Hover a marker to see issue details and photo</li>
                            </ul>
                        </div>
                    </div>
                </div>

            <?php elseif ($page === 'report'): ?>

                <!-- REPORT PAGE -->
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
                                <select name="category" id="categorySelect" required
                                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2">
                                    <option value="">Choose…</option>
                                    <option>Streetlight</option>
                                    <option>Road / Pothole</option>
                                    <option>Waste / Trash</option>
                                    <option>Water / Sewage</option>
                                    <option>Noise / Safety</option>
                                    <option>Other</option>
                                </select>

                                <!-- shown only when "Other" is selected -->
                                <div id="otherCategoryWrapper" class="mt-2 hidden">
                                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Other category
                                    </label>
                                    <input type="text" name="category_other"
                                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2"
                                           placeholder="Describe the category…">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Location (address or description)</label>
                            <input type="text" name="location" id="locationInput"
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2"
                                   placeholder="Click on the map to auto-fill coordinates">
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
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                    Photo <span class="text-red-500">*</span>
                                </label>
                                <input type="file" name="photo" accept=".jpg,.jpeg,.png" required
                                       class="block w-full text-xs text-slate-500 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-emerald-500 file:text-white file:text-xs hover:file:bg-emerald-600">
                                <p class="mt-1 text-[11px] text-slate-400">
                                    Please upload a clear JPG or PNG photo of the problem.
                                </p>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl p-3 text-xs text-slate-500 dark:text-slate-400">
                                <div class="font-semibold text-slate-600 dark:text-slate-200 mb-1">What happens next?</div>
                                <ol class="list-decimal ml-4 space-y-0.5">
                                    <li>Your report is saved in the CityCare database.</li>
                                    <li>City operators can start working on it.</li>
                                    <li>When it’s fixed, the status becomes Resolved.</li>
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
        <span>CityCare Prototype · PHP &amp; MySQL · <?php echo htmlspecialchars($cityName); ?></span>
        <span>Made for Champion Trails · <?php echo date('Y'); ?></span>
    </div>
</footer>

<!-- MAP SCRIPTS -->
<?php if ($page === 'dashboard'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const centerLat = <?php echo json_encode($mapLat); ?>;
    const centerLng = <?php echo json_encode($mapLng); ?>;

    const map = L.map('cityMap').setView([centerLat, centerLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const issues = <?php echo json_encode($issuesForMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

    issues.forEach(issue => {
        const status = (issue.status || '').toLowerCase();
        const color =
            status === 'open'
                ? 'red'
                : (status === 'in_progress' ? 'orange' : 'green');

        const marker = L.circleMarker([issue.lat, issue.lng], {
            radius: 7,
            color: color,
            fillColor: color,
            fillOpacity: 0.9
        }).addTo(map);

        let html =
            `<strong>${issue.title}</strong><br>` +
            `${issue.category}<br>` +
            (issue.location ? `<small>${issue.location}</small><br>` : '');

        if (issue.photo) {
            html += `
                <div style="margin-top:6px;">
                    <img src="${issue.photo}"
                         alt="Issue photo"
                         style="max-width:180px;border-radius:8px;display:block;">
                </div>
            `;
        }

        // Disable autoPan so the map doesn't "jump" when popup opens
        marker.bindPopup(html, {
            autoPan: false,
            closeButton: false
        });

        // Open popup on hover
        marker.on('mouseover', function () {
            this.openPopup();
        });
        marker.on('mouseout', function () {
            this.closePopup();
        });
    });
});
</script>
<?php elseif ($page === 'report'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const centerLat = <?php echo json_encode($mapLat); ?>;
    const centerLng = <?php echo json_encode($mapLng); ?>;

    // ----- OTHER CATEGORY TOGGLE -----
    const categorySelect = document.getElementById('categorySelect');
    const otherWrapper   = document.getElementById('otherCategoryWrapper');

    if (categorySelect && otherWrapper) {
        const toggleOther = () => {
            if (categorySelect.value === 'Other') {
                otherWrapper.classList.remove('hidden');
            } else {
                otherWrapper.classList.add('hidden');
            }
        };
        categorySelect.addEventListener('change', toggleOther);
        toggleOther(); // initial
    }

    // ----- MAP -----
    const map = L.map('reportMap').setView([centerLat, centerLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;
    const latField      = document.getElementById('latField');
    const lngField      = document.getElementById('lngField');
    const locationInput = document.getElementById('locationInput');

    map.on('click', function (e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        if (marker) marker.setLatLng(e.latlng);
        else marker = L.marker(e.latlng).addTo(map);

        const latFixed = lat.toFixed(6);
        const lngFixed = lng.toFixed(6);

        latField.value = latFixed;
        lngField.value = lngFixed;

        // Auto-fill location text with coordinates
        if (locationInput) {
            locationInput.value = latFixed + ', ' + lngFixed;
        }
    });
});
</script>
<?php endif; ?>

</body>
</html>
