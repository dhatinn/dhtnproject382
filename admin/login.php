<?php
require_once __DIR__ . '/../config.php';

// Redirect if already logged in
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($submittedToken)) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan coba lagi.';
    } else {
        $username = cleanInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Username dan password wajib diisi!';
        } else {
            try {
                $db = getDb();
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Prevent session fixation
                    session_regenerate_id(true);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_name'] = $user['name'];
                    $_SESSION['admin_role'] = $user['role'];
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Username atau password salah!';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Manajemen - RM Padang Harapan Bundo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #C0392B;
            --primary-hover: #E74C3C;
            --gold: #D4AF37;
            --bg: #0D0C0F;
            --card-bg: #1A1720;
            --card-border: rgba(255,255,255,0.08);
            --text-main: #F0EEF5;
            --text-muted: #7A7690;
            --input-bg: #211E29;
            --input-border: rgba(255,255,255,0.1);
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif;}
        body{background:var(--bg);background-image:radial-gradient(ellipse 70% 50% at 50% -10%,rgba(192,57,43,0.18) 0%,transparent 70%),radial-gradient(ellipse 40% 30% at 85% 85%,rgba(212,175,55,0.08) 0%,transparent 60%);color:var(--text-main);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
        .login-wrap{width:100%;max-width:420px;}
        .login-logo{text-align:center;margin-bottom:28px;}
        .logo-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primary) 0%,#8B1A1A 100%);border-radius:18px;display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;color:white;margin-bottom:14px;box-shadow:0 8px 24px rgba(192,57,43,0.4),inset 0 1px 0 rgba(255,255,255,0.15);}
        .logo-title{font-family:'Playfair Display',serif;font-size:1.65rem;font-weight:700;color:var(--text-main);letter-spacing:-0.4px;margin-bottom:4px;}
        .logo-subtitle{font-size:0.82rem;color:var(--text-muted);font-weight:500;}
        .login-card{background:var(--card-bg);border-radius:24px;padding:32px 30px;border:1px solid var(--card-border);box-shadow:0 32px 64px rgba(0,0,0,0.65),0 0 0 1px rgba(255,255,255,0.03);position:relative;overflow:hidden;}
        .login-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent 0%,var(--primary) 30%,var(--gold) 60%,transparent 100%);}
        .login-card::after{content:'';position:absolute;top:-80px;right:-60px;width:200px;height:200px;background:radial-gradient(circle,rgba(192,57,43,0.09) 0%,transparent 70%);border-radius:50%;pointer-events:none;}
        .alert-error{background:rgba(239,68,68,0.12);color:#FCA5A5;border-left:3px solid rgba(239,68,68,0.5);padding:12px 14px;border-radius:10px;font-size:0.85rem;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:600;}
        .form-group{margin-bottom:18px;}
        .form-label{display:block;font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:8px;}
        .input-group{position:relative;display:flex;align-items:center;}
        .input-group i{position:absolute;left:14px;color:var(--text-muted);font-size:0.95rem;pointer-events:none;transition:color 0.2s;}
        .form-control{width:100%;padding:13px 16px 13px 42px;border:1.5px solid var(--input-border);border-radius:12px;font-size:0.92rem;color:var(--text-main);background:var(--input-bg);transition:all 0.2s ease;outline:none;font-weight:500;}
        .form-control::placeholder{color:rgba(122,118,144,0.5);}
        .form-control:focus{background:#261F32;border-color:rgba(192,57,43,0.5);box-shadow:0 0 0 3px rgba(192,57,43,0.12);}
        .btn-submit{width:100%;padding:14px;background:linear-gradient(135deg,var(--primary) 0%,#8B1A1A 100%);color:white;border:1px solid rgba(192,57,43,0.4);border-radius:14px;font-size:0.95rem;font-weight:800;cursor:pointer;box-shadow:0 8px 20px rgba(192,57,43,0.35);transition:all 0.25s;display:flex;align-items:center;justify-content:center;gap:10px;margin-top:8px;font-family:inherit;}
        .btn-submit:hover{background:linear-gradient(135deg,var(--primary-hover) 0%,var(--primary) 100%);transform:translateY(-2px);box-shadow:0 12px 28px rgba(192,57,43,0.5);}
        .btn-submit:active{transform:translateY(0);}
        .default-credentials{background:rgba(255,255,255,0.03);border:1px dashed rgba(255,255,255,0.1);border-radius:10px;padding:10px 14px;margin-top:16px;font-size:0.78rem;color:var(--text-muted);text-align:center;}
        .default-credentials strong{color:var(--gold);}
        .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);text-decoration:none;font-size:0.82rem;font-weight:600;margin-top:18px;transition:color 0.2s;}
        .back-link:hover{color:var(--primary-hover);}
        .footer-note{margin-top:20px;text-align:center;font-size:0.74rem;color:rgba(122,118,144,0.55);line-height:1.5;}
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-logo">
            <div class="logo-icon"><i class="fa-solid fa-utensils"></i></div>
            <h1 class="logo-title">Harapan Bundo</h1>
            <p class="logo-subtitle">Panel Manajemen Restoran Padang</p>
        </div>

        <div class="login-card">
            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>">
                        <i class="fa-solid fa-user"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required value="admin123">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Masuk ke Dashboard</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="default-credentials">
                <i class="fa-solid fa-key"></i> Login Default: <strong>admin</strong> | <strong>admin123</strong>
            </div>
        </div>

        <div style="text-align:center;">
            <a href="../index.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Menu Restoran
            </a>
        </div>

        <div class="footer-note">
            &copy; <?= date('Y') ?> RM Padang Harapan Bundo &mdash; Sistem Pemesanan Aman &amp; Terverifikasi
        </div>
    </div>
</body>
</html>