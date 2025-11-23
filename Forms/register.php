<?php
// -CITY-CARE/Forms/register.php
session_start();

require_once __DIR__ . '/../database/config.php';

$errors = [];
$fullName = '';
$email    = '';
$city     = '';
$selectedAvatar = '';

// Avatar options (absolute web paths so they work everywhere)
$avatarOptions = [
    'engineer' => '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png',
    'hipster'  => '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Hipster_Walking/Hipster_Walking_Front.png',
    'shadow'   => '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Shadow_Walking/Shadow_Walking_Front.png',
    'speedster'=> '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Speedster_Walking/Speedster_Walking_Front.png',
];

// City options (Kosovo + nearby Albania)
$cityOptions = [
    'Prishtina',
    'Podujeva',
    'Ferizaj',
    'Prizren',
    'Peja',
    'Gjakova',
    'Mitrovica',
    'Gjilan',
    'Vushtrri',
    'Kamenica',
    'Malisheva',
    'Kline',
    'Suhareke',
    'Viti',
    'Kacanik',
    'Dragash',
    'Lipjan',
    'Obiliq',
    'Fushe Kosove',
    // Albania near border
    'Kukës',
    'Tropojë',
    'Shkodër',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $city     = $_POST['city'] ?? '';
    $selectedAvatarKey = $_POST['avatar'] ?? '';

    // ---------- validation ----------
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if ($city === '' || !in_array($city, $cityOptions, true)) {
        $errors[] = 'Please select your city.';
    }

    if ($selectedAvatarKey === '' || !isset($avatarOptions[$selectedAvatarKey])) {
        $errors[] = 'Please choose an avatar.';
    }

    // If no validation errors so far, check if email already exists
    if (empty($errors)) {
        try {
            $db = getDB();

            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Database error while checking existing user.';
        }
    }

    // ---------- create user ----------
    if (empty($errors)) {
        try {
            $db = getDB();

            $hash   = password_hash($password, PASSWORD_DEFAULT);
            $avatar = $avatarOptions[$selectedAvatarKey];   // final path

            // IMPORTANT: matches your columns: full_name, city, email, password_hash, is_admin, avatar
            $stmt = $db->prepare("
                INSERT INTO users (full_name, city, email, password_hash, is_admin, avatar)
                VALUES (:full_name, :city, :email, :password_hash, 0, :avatar)
            ");

            $stmt->execute([
                ':full_name'     => $fullName,
                ':city'          => $city,
                ':email'         => $email,
                ':password_hash' => $hash,
                ':avatar'        => $avatar,
            ]);

            // Log them in right away
            $newUserId = $db->lastInsertId();
            $_SESSION['user'] = [
                'id'        => $newUserId,
                'full_name' => $fullName,
                'email'     => $email,
                'city'      => $city,
                'is_admin'  => 0,
                'avatar'    => $avatar,
            ];

            // Redirect to dashboard
            header('Location: /-CITY-CARE/City-Main/index.php');
            exit;

        } catch (Throwable $e) {
            // If you still get an error, temporarily uncomment the next line to see it:
            // $errors[] = 'Database error: ' . $e->getMessage();
            $errors[] = 'Could not create user (database error).';
        }
    }

    // keep the selected avatar on form re-display
    $selectedAvatar = $selectedAvatarKey;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CityCare · Sign Up</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center py-8 px-4">

<div class="w-full max-w-xl bg-white rounded-2xl shadow-xl border border-slate-200 p-6 sm:p-8">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="h-9 w-9 rounded-xl bg-emerald-500 flex items-center justify-center text-white font-bold">C</div>
            <div>
                <div class="font-semibold text-base">CityCare</div>
                <div class="text-xs text-slate-500">Smart Reporting Platform</div>
            </div>
        </div>
    </div>

    <h1 class="text-xl font-semibold mb-1">Create your account</h1>
    <p class="text-sm text-slate-500 mb-4">Join CityCare and start reporting issues in your city.</p>

    <?php if (!empty($errors)): ?>
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm px-3 py-2">
            <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="register.php" method="post" class="space-y-4 text-sm">

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Full name</label>
                <input type="text" name="full_name" required
                       value="<?php echo htmlspecialchars($fullName); ?>"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">City</label>
                <select name="city" required
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">Choose your city…</option>
                    <?php foreach ($cityOptions as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>"
                            <?php echo $city === $opt ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($opt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
            <input type="email" name="email" required
                   value="<?php echo htmlspecialchars($email); ?>"
                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Password</label>
                <input type="password" name="password" required
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Confirm password</label>
                <input type="password" name="confirm_password" required
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
        </div>

        <!-- Avatar selection -->
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-2">Choose your avatar</label>
            <p class="text-[11px] text-slate-500 mb-2">
                This character will represent you in CityCare (profile and future features).
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <?php foreach ($avatarOptions as $key => $path): ?>
                    <label class="cursor-pointer group">
                        <input type="radio" name="avatar" value="<?php echo htmlspecialchars($key); ?>"
                               class="peer hidden"
                               <?php echo ($selectedAvatar === $key) ? 'checked' : ''; ?>>
                        <div class="rounded-2xl border border-slate-200 peer-checked:border-emerald-500 peer-checked:ring-2 peer-checked:ring-emerald-400 bg-slate-50 flex flex-col items-center justify-center p-2 transition">
                            <div class="h-20 w-full flex items-center justify-center overflow-hidden">
                                <img src="<?php echo htmlspecialchars($path); ?>"
                                     alt="<?php echo htmlspecialchars(ucfirst($key)); ?> avatar"
                                     class="h-full w-auto object-contain group-hover:scale-105 transition-transform duration-150">
                            </div>
                            <div class="mt-1 text-xs font-medium text-slate-700">
                                <?php echo htmlspecialchars(ucfirst($key)); ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <p class="text-[11px] text-slate-500">
                Already have an account?
                <a href="login.php" class="text-emerald-600 hover:text-emerald-700 font-medium">Log in</a>
            </p>
            <button type="submit"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium shadow-sm">
                Sign up
            </button>
        </div>

    </form>
</div>

</body>
</html>
