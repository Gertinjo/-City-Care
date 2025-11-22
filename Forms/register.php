<?php
// /-CITY-CARE/Forms/register.php – CityCare Registration
session_start();

// config.php is in /-CITY-CARE/City-Main/database/config.php
require_once __DIR__ . '/../database/config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $city     = trim($_POST['city'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Basic validation
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if ($city === '') {
        $errors[] = 'Please select your city.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $db = getDB();

            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);

            if ($stmt->fetch()) {
                $errors[] = 'There is already an account with this email.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("
                    INSERT INTO users (full_name, city, email, password_hash, is_admin)
                    VALUES (:full_name, :city, :email, :password_hash, 0)
                ");
                $stmt->execute([
                    ':full_name'     => $fullName,
                    ':city'          => $city,
                    ':email'         => $email,
                    ':password_hash' => $hash,
                ]);

                $userId = (int)$db->lastInsertId();

                // Auto-login
                $_SESSION['user'] = [
                    'id'        => $userId,
                    'full_name' => $fullName,
                    'city'      => $city,
                    'email'     => $email,
                    'is_admin'  => 0,
                ];

                // Redirect to main dashboard
                header('Location: /-CITY-CARE/City-Main/index.php');
                exit;
            }

        } catch (Throwable $e) {
            $errors[] = 'Database error while creating account.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        // Dark mode sync with rest of site
        if (localStorage.theme === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col">

<!-- TOP NAVBAR -->
<header class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur sticky top-0 z-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-14">
        <div class="flex items-center gap-2">
            <div class="h-8 w-8 rounded-lg bg-emerald-500 flex items-center justify-center text-white font-bold">
                C
            </div>
            <div>
                <div class="font-semibold text-sm sm:text-base">CityCare</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Smart Reporting Platform</div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="/-CITY-CARE/City-Main/index.php"
               class="hidden sm:inline-flex text-xs sm:text-sm text-slate-600 dark:text-slate-300 hover:underline">
                Home
            </a>
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

<main class="flex-1 flex items-center justify-center px-4 py-10">
    <div class="max-w-md w-full">
        <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm p-6 sm:p-8">
            <h1 class="text-xl sm:text-2xl font-semibold mb-1">Create your CityCare account</h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mb-4">
                Register to report issues and help keep your city running smoothly.
            </p>

            <?php if (!empty($errors)): ?>
                <div class="mb-4 text-xs sm:text-sm px-3 py-2 rounded-lg bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                    <?php foreach ($errors as $err): ?>
                        <div>• <?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="post" class="space-y-4 text-sm">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">
                        Full name
                    </label>
                    <input type="text" name="full_name" required
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/70">
                </div>

                <!-- City select -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">
                        City
                    </label>
                    <select name="city" required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/70">
                        <option value="">Select your city</option>

                        <optgroup label="Kosovo">
                            <option value="Prishtina"   <?php echo (($_POST['city'] ?? '') === 'Prishtina') ? 'selected' : ''; ?>>Prishtina</option>
                            <option value="Prizren"     <?php echo (($_POST['city'] ?? '') === 'Prizren') ? 'selected' : ''; ?>>Prizren</option>
                            <option value="Peja"        <?php echo (($_POST['city'] ?? '') === 'Peja') ? 'selected' : ''; ?>>Peja</option>
                            <option value="Gjakova"     <?php echo (($_POST['city'] ?? '') === 'Gjakova') ? 'selected' : ''; ?>>Gjakova</option>
                            <option value="Mitrovica"   <?php echo (($_POST['city'] ?? '') === 'Mitrovica') ? 'selected' : ''; ?>>Mitrovica</option>
                            <option value="Ferizaj"     <?php echo (($_POST['city'] ?? '') === 'Ferizaj') ? 'selected' : ''; ?>>Ferizaj</option>
                            <option value="Gjilan"      <?php echo (($_POST['city'] ?? '') === 'Gjilan') ? 'selected' : ''; ?>>Gjilan</option>
                            <option value="Vushtrri"    <?php echo (($_POST['city'] ?? '') === 'Vushtrri') ? 'selected' : ''; ?>>Vushtrri</option>
                            <option value="Podujeva"    <?php echo (($_POST['city'] ?? '') === 'Podujeva') ? 'selected' : ''; ?>>Podujeva</option>
                            <option value="Suhareka"    <?php echo (($_POST['city'] ?? '') === 'Suhareka') ? 'selected' : ''; ?>>Suhareka</option>
                            <option value="Rahovec"     <?php echo (($_POST['city'] ?? '') === 'Rahovec') ? 'selected' : ''; ?>>Rahovec</option>
                            <option value="Istog"       <?php echo (($_POST['city'] ?? '') === 'Istog') ? 'selected' : ''; ?>>Istog</option>
                            <option value="Kamenica"    <?php echo (($_POST['city'] ?? '') === 'Kamenica') ? 'selected' : ''; ?>>Kamenica</option>
                            <option value="Malisheva"   <?php echo (($_POST['city'] ?? '') === 'Malisheva') ? 'selected' : ''; ?>>Malisheva</option>
                            <option value="Skenderaj"   <?php echo (($_POST['city'] ?? '') === 'Skenderaj') ? 'selected' : ''; ?>>Skenderaj</option>
                            <option value="Dragash"     <?php echo (($_POST['city'] ?? '') === 'Dragash') ? 'selected' : ''; ?>>Dragash</option>
                        </optgroup>

                        <optgroup label="Albania (border regions)">
                            <option value="Kukes"       <?php echo (($_POST['city'] ?? '') === 'Kukes') ? 'selected' : ''; ?>>Kukës</option>
                            <option value="Tropoje"     <?php echo (($_POST['city'] ?? '') === 'Tropoje') ? 'selected' : ''; ?>>Tropojë</option>
                            <option value="Shkoder"     <?php echo (($_POST['city'] ?? '') === 'Shkoder') ? 'selected' : ''; ?>>Shkodër</option>
                            <option value="Puke"        <?php echo (($_POST['city'] ?? '') === 'Puke') ? 'selected' : ''; ?>>Pukë</option>
                            <option value="Lezhe"       <?php echo (($_POST['city'] ?? '') === 'Lezhe') ? 'selected' : ''; ?>>Lezhë</option>
                            <option value="Has"         <?php echo (($_POST['city'] ?? '') === 'Has') ? 'selected' : ''; ?>>Has</option>
                        </optgroup>
                    </select>
                    <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                        This helps your municipality see where reports are coming from.
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">
                        Email address
                    </label>
                    <input type="email" name="email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/70">
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">
                            Password
                        </label>
                        <input type="password" name="password" required
                               class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/70">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">
                            Confirm password
                        </label>
                        <input type="password" name="confirm_password" required
                               class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/70">
                    </div>
                </div>

                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Your data is securely protected. Please report only real issues in your city.
                </p>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium shadow-sm">
                    Sign up
                </button>
            </form>
        </div>

        <p class="mt-4 text-xs text-center text-slate-500 dark:text-slate-400">
            Already have an account?
            <a href="/-CITY-CARE/Forms/login.php" class="text-emerald-600 dark:text-emerald-400 font-medium hover:underline">
                Log in
            </a>
        </p>
    </div>
</main>

</body>
</html>
