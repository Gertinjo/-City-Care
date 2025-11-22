<?php
// /-CITY-CARE/Forms/login.php – CityCare Login
session_start();

require_once __DIR__ . '/../database/config.php';

$errors = [];



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email.';
    }
    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        try {
            $db = getDB();

            $stmt = $db->prepare("
                SELECT id, full_name, city, email, password_hash, is_admin
                FROM users
                WHERE email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user'] = [
                    'id'        => (int)$user['id'],
                    'full_name' => $user['full_name'],
                    'city'      => $user['city'],
                    'email'     => $user['email'],
                    'is_admin'  => (int)$user['is_admin'],
                ];

                header('Location: /-CITY-CARE/City-Main/index.php');
                exit;
            } else {
                $errors[] = 'Invalid email or password.';
            }

        } catch (Throwable $e) {
            $errors[] = 'Database error while logging in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (localStorage.theme === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col">

<header class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur sticky top-0 z-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-14">
        <div class="flex items-center gap-2">
            <div class="h-8 w-8 rounded-lg bg-emerald-500 flex items-center justify-center text-white font-bold">C</div>
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
                title="Toggle dark mode">🌗</button>
        </div>
    </div>
</header>

<main class="flex-1 flex items-center justify-center px-4 py-10">
    <div class="max-w-md w-full">
        <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm p-6 sm:p-8">
            <h1 class="text-xl sm:text-2xl font-semibold mb-1">Welcome back</h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mb-4">
                Sign in to access CityCare and manage or report issues in your city.
            </p>

            <?php if (!empty($errors)): ?>
                <div class="mb-4 text-xs sm:text-sm px-3 py-2 rounded-lg bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                    <?php foreach ($errors as $err): ?>
                        <div>• <?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="post" class="space-y-4 text-sm">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Email address</label>
                    <input type="email" name="email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/70">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Password</label>
                    <input type="password" name="password" required
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/70">
                </div>

                <div class="text-[11px] text-slate-400 dark:text-slate-500">
                    Your data is securely protected. Never share your password with anyone.
                </div>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium shadow-sm">
                    Log in
                </button>
            </form>
        </div>

        <p class="mt-4 text-xs text-center text-slate-500 dark:text-slate-400">
            Don’t have an account?
            <a href="/-CITY-CARE/Forms/register.php" class="text-emerald-600 dark:text-emerald-400 font-medium hover:underline">
                Sign up
            </a>
        </p>
    </div>
</main>

</body>
</html>
