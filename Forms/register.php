<?php
// register.php – CityCare Registration
session_start();

require_once __DIR__ . '/../database/config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Basic validation
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
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
                    INSERT INTO users (full_name, email, password_hash, is_admin)
                    VALUES (:full_name, :email, :password_hash, 0)
                ");
                $stmt->execute([
                    ':full_name'    => $fullName,
                    ':email'        => $email,
                    ':password_hash'=> $hash,
                ]);

                $userId = (int)$db->lastInsertId();

                // Auto login user after register
                $_SESSION['user'] = [
                    'id'       => $userId,
                    'full_name'=> $fullName,
                    'email'    => $email,
                    'is_admin' => 0,
                ];

                $success = true;
                // Optional: redirect to dashboard
                // header('Location: index.php?page=dashboard');
                // exit;
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
        // Same dark mode behavior as index (if you used it)
        if (localStorage.theme === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col">

<!-- TOP NAVBAR (similar to index.php) -->
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
            <a href="index.php?page=dashboard"
               class="hidden sm:inline-flex text-xs sm:text-sm text-slate-600 dark:text-slate-300 hover:underline">
                ← Back to dashboard
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
                Register to report issues and help keep Prishtina running smoothly.
            </p>

            <?php if ($success): ?>
                <div class="mb-4 text-xs sm:text-sm px-3 py-2 rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    ✅ Account created and you are now logged in.
                    <br> 
                    <a href="index.php?page=dashboard" class="underline">Go to dashboard</a>
                </div>
            <?php endif; ?>

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
                    By creating an account, you agree to use CityCare only for real issues in your city.
                </p>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium shadow-sm">
                    Create account
                </button>
            </form>
        </div>

        <p class="mt-4 text-xs text-center text-slate-500 dark:text-slate-400">
            Already have an account? (We can add <span class="font-semibold">login.php</span> next.)
        </p>
    </div>
</main>

</body>
</html>
