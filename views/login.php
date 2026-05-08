<?php
require_once __DIR__ . '/../controllers/AuthController.php';

$authController = new AuthController();
$error = $authController->handleLogin();

if (Auth::isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Fetch Global Config for Login
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Database.php';

try {
    $dbCfg = (new Database())->getConnection();
    $stmtCfg = $dbCfg->query("SELECT nombre_negocio, logo_path FROM configuracion ORDER BY updated_at DESC LIMIT 1");
    $globalConfig = $stmtCfg->fetch(PDO::FETCH_ASSOC) ?: [];
    $nombreNegocio = $globalConfig['nombre_negocio'] ?? 'AbdiSoft CORE';
    $logoPath = $globalConfig['logo_path'] ?? '';
} catch (Throwable $e) {
    $nombreNegocio = 'AbdiSoft CORE';
    $logoPath = '';
}
$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AbdiSoft POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00aeef;
            --primary-dark: #0081b1;
            --secondary: #13243d;
            --bg: #f8f9fc;
            --text: #2d3748;
            --border: #e2e8f0;
        }

        body {
            background: radial-gradient(circle at top right, #f8f9fc 0%, #e2e8f0 100%);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .login-card {
            background: #ffffff;
            border: none;
            border-radius: 20px;
            padding: 3rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 40px rgba(19, 36, 61, 0.1);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .login-header h1 {
            font-weight: 800;
            font-size: 1.75rem;
            letter-spacing: -0.5px;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #718096;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .form-label {
            font-weight: 700;
            font-size: 0.75rem;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .form-control {
            background: #f8fafc;
            border: 2px solid #f1f5f9;
            color: var(--secondary);
            padding: 0.85rem 1.2rem;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 174, 239, 0.1);
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            padding: 1rem;
            font-weight: 700;
            border-radius: 12px;
            margin-top: 1rem;
            box-shadow: 0 10px 20px rgba(0, 174, 239, 0.2);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(0, 174, 239, 0.3);
        }

        .logo-box {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 16px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.8rem;
            color: #fff;
            box-shadow: 0 8px 16px rgba(0, 174, 239, 0.2);
        }

        .alert-danger {
            background: #fff5f5;
            border: none;
            border-left: 4px solid var(--danger);
            color: #c53030;
            font-size: 0.9rem;
            border-radius: 8px;
            padding: 1rem;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <?php if (!empty($logoPath)): ?>
            <img src="<?php echo htmlspecialchars($baseUrl . $logoPath); ?>" alt="Logo" style="max-height: 70px; width: auto; margin-bottom: 1.5rem;">
        <?php else: ?>
            <div class="logo-box">A</div>
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($nombreNegocio); ?></h1>
        <p>Soluciones Digitales de Alto Rendimiento</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Usuario</label>
            <input type="text" name="username" id="username" class="form-control" placeholder="admin" required autofocus>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">ACCEDER AL SISTEMA</button>
    </form>
</div>

</body>
</html>
