<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Caja.php';

Auth::checkAccess();

$database = new Database();
$db = $database->getConnection();
$cajaModel = new Caja($db);

// Verificar si ya tiene una caja abierta
$cajaAbierta = $cajaModel->obtenerEstado($_SESSION['user_id']);
$yaAbierta = $cajaAbierta ? true : false;
// No redirigimos aquí con header para poder mostrar el SweetAlert en el script

$active = 'arqueo';
$extraJs = [
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
];
include_once __DIR__ . '/../layout/header.php';
?>
<div class="app-shell">
    <?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="content">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-primary text-white p-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-safe2 fs-1 me-3"></i>
                                <div>
                                    <h3 class="mb-0">Apertura de Caja</h3>
                                    <p class="mb-0 text-white-50">Inicia tu jornada laboral estableciendo el monto
                                        inicial.</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <form id="formAperturaCaja">
                                <div class="mb-4">
                                    <label for="monto_inicial" class="form-label fw-bold">Monto Inicial en
                                        Efectivo</label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-light">Bs</span>
                                        <input type="number" step="0.01" class="form-control" id="monto_inicial"
                                            name="monto_inicial" placeholder="0.00" required>
                                    </div>
                                    <div class="form-text mt-2 text-muted">
                                        Ingresa el monto de dinero físico con el que inicias la caja hoy.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg w-100 py-3 shadow-sm">
                                    <i class="bi bi-play-circle me-2"></i> Abrir Caja Ahora
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include_once __DIR__ . '/../layout/footer.php'; ?>

<script>
    // Verificar si la caja ya estaba abierta al cargar la página
    <?php if ($yaAbierta): ?>
    Swal.fire({
        icon: 'info',
        title: 'Caja ya abierta',
        text: 'Ya tienes una caja abierta. Te redirigiremos al punto de venta.',
        timer: 2500,
        showConfirmButton: false
    }).then(() => {
        window.location.href = 'pos.php';
    });
    <?php endif; ?>

    document.getElementById('formAperturaCaja').addEventListener('submit', async (e) => {
        e.preventDefault();

        const monto = document.getElementById('monto_inicial').value;

        try {
            const response = await fetch('arqueo_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=abrir&monto_inicial=${monto}`
            });

            const res = await response.json();

            if (res.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Caja Abierta',
                    text: 'Se ha registrado la apertura correctamente.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'pos.php';
                });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'Hubo un problema al procesar la solicitud.', 'error');
        }
    });
</script>