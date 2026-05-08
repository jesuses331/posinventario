<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : '/Filacell/';

// Fetch Global Config
try {
    $dbCfg = (new Database())->getConnection();
    $stmtCfg = $dbCfg->query("SELECT nombre_negocio, logo_path, plan_sistema, fecha_inicio_plan FROM configuracion ORDER BY updated_at DESC LIMIT 1");
    $globalConfig = $stmtCfg->fetch(PDO::FETCH_ASSOC) ?: [];
    $nombreNegocio = $globalConfig['nombre_negocio'] ?? 'AbdiSoft POS';
    $logoPath = $globalConfig['logo_path'] ?? '';
    $planSistema = $globalConfig['plan_sistema'] ?? 'demo';
    $fechaInicioPlan = $globalConfig['fecha_inicio_plan'] ?? null;

    // Control de Vigencia
    $isExpired = false;
    $daysLeft = 0;
    $fechaExpiracion = null;
    
    if ($planSistema && $fechaInicioPlan) {
        $durations = [
            'demo' => 7,
            'mensual' => 30,
            'trimestral' => 90,
            'semestral' => 180,
            'anual' => 365
        ];
        $days = $durations[$planSistema] ?? 7;
        $startDate = new DateTime($fechaInicioPlan);
        $expDate = clone $startDate;
        $expDate->modify("+$days days");
        $fechaExpiracion = $expDate->format('d/m/Y');
        
        $today = new DateTime();
        if ($today >= $expDate && ($_SESSION['user_usuario'] ?? '') !== 'desarrollador') {
            $isExpired = true;
        } else {
            $interval = $today->diff($expDate);
            $daysLeft = (int)$interval->format('%r%a');
            if ($daysLeft < 0) $daysLeft = 0;
        }
    }
} catch (Throwable $e) {
    $nombreNegocio = 'AbdiSoft POS';
    $logoPath = '';
    $isExpired = false;
    $planSistema = null;
    $fechaExpiracion = null;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'AbdiSoft CORE'); ?></title>
    <script>
        document.documentElement.setAttribute('data-theme', 'light');
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($baseUrl . 'assets/css/main.css'); ?>" rel="stylesheet">
    <?php if (!empty($extraCss) && is_array($extraCss)): ?>
        <?php foreach ($extraCss as $href): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($href); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body>
    <?php if ($isExpired): ?>
    <div style="position:fixed; inset:0; background:rgba(19, 36, 61, 0.95); z-index:9999; display:flex; align-items:center; justify-content:center; color:white; text-align:center; padding:20px; font-family:'Inter', sans-serif; backdrop-filter: blur(10px);">
            <div>
                <h1 style="font-weight:800; font-size:3rem; margin-bottom:1rem;">SISTEMA EXPIRADO</h1>
                <p style="font-size:1.2rem; opacity:0.8; max-width:600px; margin:0 auto 2rem;">
                    La suscripción de este sistema (Plan <?php echo ucfirst($planSistema); ?>) ha finalizado.
                    Por favor, contacte con el desarrollador para renovar su licencia.
                </p>
                <a href="<?php echo htmlspecialchars($baseUrl . 'views/logout.php'); ?>"
                    class="btn btn-primary btn-lg">Cerrar Sesión</a>
            </div>
        </div>
    <?php endif; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menú">☰</button>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (!toggle || !sidebar || !overlay) return;

            function openSidebar() {
                sidebar.classList.add('open');
                overlay.classList.add('visible');
            }
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('visible');
            }

            toggle.addEventListener('click', function () {
                if (sidebar.classList.contains('open')) closeSidebar();
                else openSidebar();
            });

            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 992) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('visible');
                }
            });
        });
    </script>