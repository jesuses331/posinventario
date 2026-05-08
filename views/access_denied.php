<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Auth.php';

$pageTitle = 'Acceso Denegado';
include_once __DIR__ . '/layout/header.php';
?>

<div class="container d-flex align-items-center justify-content-center" style="min-height: 80vh;">
    <div class="text-center p-5 shadow-lg rounded-4 bg-white" style="max-width: 500px;">
        <div class="mb-4">
            <i class="bi bi-shield-lock text-danger" style="font-size: 5rem;"></i>
        </div>
        <h1 class="fw-bold text-dark mb-3">Acceso Denegado</h1>
        <p class="text-muted mb-4">
            Lo sentimos, no tienes los permisos necesarios para acceder a este módulo. 
            Si crees que esto es un error, contacta con el administrador.
        </p>
        <div class="d-grid gap-2">
            <a href="<?php echo htmlspecialchars(BASE_URL . 'views/dashboard.php'); ?>" class="btn btn-primary btn-lg rounded-pill">
                <i class="bi bi-house-door me-2"></i> Volver al Inicio
            </a>
            <a href="<?php echo htmlspecialchars(BASE_URL . 'views/logout.php'); ?>" class="btn btn-outline-secondary btn-sm border-0 mt-3">
                Cerrar Sesión
            </a>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/layout/footer.php'; ?>
