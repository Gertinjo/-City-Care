<?php
// -CITY-CARE/Forms/login.php
session_start();

require_once __DIR__ . '/../database/config.php';

// DEV: show PHP errors (you can remove later)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        try {
            $db = getDB();

            // MUST match your columns: id, full_name, city, email, password_hash, is_admin, avatar
            $stmt = $db->prepare("
                SELECT id, full_name, city, email, password_hash, is_admin, avatar
                FROM users
                WHERE email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $errors[] = 'No account found with that email.';
            } else {
                // verify password against password_hash column
                if (!password_verify($password, $row['password_hash'])) {
                    $errors[] = 'Incorrect password.';
                } else {
                    // ✅ save everything in the session, exactly as profile/index expect
                    $_SESSION['user'] = [
                        'id'        => (int)$row['id'],
                        'full_name' => $row['full_name'],
                        'city'      => $row['city'],
                        'email'     => $row['email'],
                        'is_admin'  => (int)$row['is_admin'],
                        'avatar'    => $row['avatar'],
                    ];

                    // ✅ redirect based on role
                    $isAdminLevel = (int)$row['is_admin'];

                    if ($isAdminLevel >= 1) {
                        // Admin or Super Admin → HEAT MAP
                        header('Location: /-CITY-CARE/admin/admin_heatmap.php');
                    } else {
                        // Normal citizen → main dashboard
                        header('Location: /-CITY-CARE/City-Main/index.php');
                    }
                    exit;
                }
            }
        } catch (Throwable $e) {
            // TEMP: show real DB error so you can see what’s wrong if any
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Log In</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center py-8 px-4">

<div class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-200 p-6 sm:p-8">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="h-9 w-9 rounded-xl bg-emerald-500 flex items-center justify-center text-white font-bold">C</div>
            <div>
                <div class="font-semibold text-base">CityCare</div>
                <div class="text-xs text-slate-500">Smart Reporting Platform</div>
            </div>
        </div>
    </div>

    <h1 class="text-xl font-semibold mb-1">Welcome back</h1>
    <p class="text-sm text-slate-500 mb-4">Log in to your CityCare account.</p>

    <?php if (!empty($errors)): ?>
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm px-3 py-2">
            <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="login.php" method="post" class="space-y-4 text-sm">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
            <input type="email" name="email" required
                   value="<?php echo htmlspecialchars($email); ?>"
                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Password</label>
            <input type="password" name="password" required
                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>

        <div class="flex items-center justify-between pt-2">
            <p class="text-[11px] text-slate-500">
                Don’t have an account?
                <a href="register.php" class="text-emerald-600 hover:text-emerald-700 font-medium">Sign up</a>
            </p>
            <button type="submit"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium shadow-sm">
                Log in
            </button>
        </div>
    </form>
</div>

</body>
</html>
