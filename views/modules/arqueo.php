<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Caja.php';

Auth::checkAccess();

$database = new Database();
$db = $database->getConnection();
$cajaModel = new Caja($db);

$cajaAbierta = $cajaModel->obtenerEstado($_SESSION['user_id']);

$active = 'arqueo';
$extraJs = [
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
];
include_once __DIR__ . '/../layout/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="content">
        <div class="container py-5">
            
            <?php if (!$cajaAbierta): ?>
                <!-- VISTA 1: APERTURA NECESARIA -->
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-lg rounded-4 overflow-hidden text-center p-5">
                            <div class="mb-4">
                                <div class="bg-light d-inline-flex p-4 rounded-circle mb-3">
                                    <i class="bi bi-safe2 text-primary" style="font-size: 4rem;"></i>
                                </div>
                                <h2 class="fw-bold">Control de Caja</h2>
                                <p class="text-muted">No tienes una sesión de caja abierta para el turno actual.</p>
                            </div>
                            <div class="d-grid gap-3">
                                <a href="apertura_caja.php" class="btn btn-primary btn-lg rounded-pill py-3 shadow-sm">
                                    <i class="bi bi-play-fill me-2"></i> Abrir Nueva Caja
                                </a>
                                <a href="arqueo_historial.php" class="btn btn-outline-secondary rounded-pill">
                                    <i class="bi bi-clock-history me-2"></i> Ver Historial de Cierres
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- VISTA 2: CORTE DE CAJA (OPERACIÓN ACTIVA) -->
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div>
                                <h1 class="h3 title-strong mb-1">Corte de Caja</h1>
                                <p class="text-muted mb-0">Resumen operativo del turno actual.</p>
                            </div>
                            <div class="badge bg-success-soft text-success px-3 py-2 rounded-pill">
                                <i class="bi bi-circle-fill me-2 small"></i> Caja Abierta
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <!-- Resumen Operativo -->
                            <div class="col-md-8">
                                <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="bg-primary-soft p-3 rounded-3 me-3 text-primary">
                                            <i class="bi bi-calculator fs-3"></i>
                                        </div>
                                        <h5 class="mb-0 fw-bold">Balance de Turno</h5>
                                    </div>
                                    
                                    <div class="row g-4 text-center">
                                        <div class="col-6 col-md-3">
                                            <div class="text-muted small text-uppercase fw-bold mb-1">Inicial</div>
                                            <div class="h5 mb-0 fw-bold">Bs <?= number_format($cajaAbierta['monto_inicial'], 2) ?></div>
                                        </div>
                                        <div class="col-6 col-md-3 border-start">
                                             <?php
                                             $stmtV = $db->prepare("SELECT SUM(pago_efectivo) as total_efectivo, SUM(pago_qr) as total_qr FROM ventas WHERE id_caja = ?");
                                             $stmtV->execute([$cajaAbierta['id']]);
                                             $ventas = $stmtV->fetch();
                                             $totalEfectivo = $ventas['total_efectivo'] ?? 0;
                                             $totalQr = $ventas['total_qr'] ?? 0;
                                             ?>
                                             <div class="text-muted small text-uppercase fw-bold mb-1">Ventas Ef.</div>
                                             <div class="h5 mb-0 fw-bold text-success">+ Bs <?= number_format($totalEfectivo, 2) ?></div>
                                         </div>
                                         <div class="col-6 col-md-3 border-start">
                                             <div class="text-muted small text-uppercase fw-bold mb-1">Ventas QR</div>
                                             <div class="h5 mb-0 fw-bold text-info">+ Bs <?= number_format($totalQr, 2) ?></div>
                                         </div>
                                         <div class="col-6 col-md-3 border-start">
                                             <?php
                                             $stmtG = $db->prepare("SELECT SUM(monto) as total_gastos FROM gastos_extra WHERE id_caja = ?");
                                             $stmtG->execute([$cajaAbierta['id']]);
                                             $totalGastos = $stmtG->fetch()['total_gastos'] ?? 0;
                                             ?>
                                             <div class="text-muted small text-uppercase fw-bold mb-1">Gastos</div>
                                             <div class="h5 mb-0 fw-bold text-danger">- Bs <?= number_format($totalGastos, 2) ?></div>
                                         </div>
                                         <div class="col-6 col-md-3 border-start">
                                             <div class="text-muted small text-uppercase fw-bold mb-1">Esperado</div>
                                             <div class="h5 mb-0 fw-bold text-primary">Bs <?= number_format(($cajaAbierta['monto_inicial'] + $totalEfectivo + $totalQr) - $totalGastos, 2) ?></div>
                                         </div>
                                    </div>

                                    <hr class="my-4 opacity-50">

                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="text-muted small">Abierta el: <b><?= date('d/m/Y H:i', strtotime($cajaAbierta['fecha_apertura'])) ?></b></div>
                                        <button onclick="prepararCierre()" class="btn btn-danger px-4 rounded-pill shadow-sm">
                                            <i class="bi bi-lock-fill me-2"></i> Finalizar y Cerrar Caja
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Gastos Rápidos -->
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                                    <h6 class="fw-bold mb-3"><i class="bi bi-cash-stack me-2 text-danger"></i> Registrar Gasto</h6>
                                    <form id="formGastoExtra">
                                        <input type="hidden" name="id_caja" value="<?= $cajaAbierta['id'] ?>">
                                        <div class="mb-3">
                                            <input type="text" name="descripcion" class="form-control form-control-sm border-0 bg-light" placeholder="Motivo (ej. Limpieza)..." required>
                                        </div>
                                        <div class="input-group input-group-sm mb-3">
                                            <span class="input-group-text border-0 bg-light">Bs</span>
                                            <input type="number" step="0.01" name="monto" class="form-control border-0 bg-light" placeholder="0.00" required>
                                            <button class="btn btn-danger" type="submit">OK</button>
                                        </div>
                                    </form>
                                    
                                    <div class="list-group list-group-flush overflow-auto small" style="max-height: 120px;">
                                        <?php
                                        $stmtListG = $db->prepare("SELECT * FROM gastos_extra WHERE id_caja = ? ORDER BY fecha DESC");
                                        $stmtListG->execute([$cajaAbierta['id']]);
                                        $gastos = $stmtListG->fetchAll();
                                        foreach ($gastos as $g):
                                        ?>
                                            <div class="list-group-item px-0 py-2 d-flex justify-content-between bg-transparent">
                                                <span class="text-truncate" style="max-width: 100px;"><?= htmlspecialchars($g['descripcion']) ?></span>
                                                <span class="text-danger fw-bold">- Bs <?= number_format($g['monto'], 2) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <a href="arqueo_historial.php" class="text-muted text-decoration-none small">
                                <i class="bi bi-clock-history me-1"></i> Ver historial de auditoría de cierres
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- Modal Cierre -->
<div class="modal fade" id="modalCierre" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold">Confirmar Cierre de Turno</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted">Por favor, ingresa el <b>monto de dinero físico</b> total que tienes en la caja actualmente.</p>
                <form id="formCierreCaja">
                    <input type="hidden" name="id_caja" value="<?= $cajaAbierta['id'] ?? '' ?>">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Dinero Real en Caja</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text border-0 bg-light">Bs</span>
                            <input type="number" step="0.01" class="form-control border-0 bg-light" name="monto_final_real" id="monto_final_real" required placeholder="0.00" autofocus>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger btn-lg w-100 rounded-pill py-3 shadow">
                        Finalizar Turno y Generar Arqueo
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-primary-soft { background-color: rgba(13, 110, 253, 0.1); }
    .bg-success-soft { background-color: rgba(25, 135, 84, 0.1); }
    .btn-link:hover { opacity: 0.8; }
</style>

<?php include_once __DIR__ . '/../layout/footer.php'; ?>

<script>
    function prepararCierre() {
        new bootstrap.Modal(document.getElementById('modalCierre')).show();
    }

    document.getElementById('formGastoExtra')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.append('action', 'registrar_gasto');
        try {
            const response = await fetch('arqueo_api.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });
            const res = await response.json();
            if (res.ok) location.reload();
            else Swal.fire('Error', res.message, 'error');
        } catch (err) { Swal.fire('Error', 'Error de conexión', 'error'); }
    });

    document.getElementById('formCierreCaja')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.append('action', 'cerrar');

        const result = await Swal.fire({
            title: '¿Cerrar turno?',
            text: "Se generará el reporte de arqueo y se cerrará la sesión de caja.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, cerrar caja',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch('arqueo_api.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                });
                const res = await response.json();
                if (res.ok) {
                    Swal.fire('Caja Cerrada', 'Turno finalizado correctamente.', 'success').then(() => {
                        location.href = 'arqueo.php';
                    });
                } else Swal.fire('Error', res.message, 'error');
            } catch (err) { Swal.fire('Error', 'Error al procesar', 'error'); }
        }
    });
</script>