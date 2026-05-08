<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Caja.php';

Auth::checkAccess();

$database = new Database();
$db = $database->getConnection();

// Filtro: Si no es admin, solo ve sus arqueos
$where = "";
$params = [];
if (!Auth::isAdmin()) {
    $where = "WHERE c.id_usuario = ?";
    $params[] = $_SESSION['user_id'];
}

$stmt = $db->prepare("
    SELECT c.*, u.nombre as usuario_nombre,
           COALESCE((SELECT SUM(monto) FROM gastos_extra WHERE id_caja = c.id), 0) as total_gastos
    FROM cajas c
    JOIN usuarios u ON c.id_usuario = u.id
    $where
    ORDER BY c.fecha_apertura DESC
    LIMIT 100
");
$stmt->execute($params);
$historial = $stmt->fetchAll();

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
            
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1 class="h3 title-strong mb-1">Historial de Arqueos</h1>
                    <p class="text-muted mb-0">Auditoría y consulta de cierres de caja pasados.</p>
                </div>
                <a href="arqueo.php" class="btn btn-theme-outline rounded-pill">
                    <i class="bi bi-arrow-left me-2"></i> Volver a Corte
                </a>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4">Usuario</th>
                                <th>Apertura</th>
                                <th>Cierre</th>
                                <th class="text-end">Inicial</th>
                                <th class="text-end">Efectivo</th>
                                <th class="text-end">QR</th>
                                <th class="text-end">Gastos</th>
                                <th class="text-end">Sistema</th>
                                <th class="text-end">Real</th>
                                <th class="text-end">Diff</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $h): ?>
                                <tr>
                                    <td class="px-4">
                                        <div class="fw-bold"><?= htmlspecialchars($h['usuario_nombre']) ?></div>
                                    </td>
                                    <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($h['fecha_apertura'])) ?></small></td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $h['fecha_cierre'] ? date('d/m/Y H:i', strtotime($h['fecha_cierre'])) : '<span class="badge bg-success-soft text-success">En curso</span>' ?>
                                        </small>
                                    </td>
                                    <td class="text-end">Bs <?= number_format($h['monto_inicial'], 2) ?></td>
                                    <td class="text-end text-success">Bs <?= number_format($h['total_efectivo'] ?? 0, 2) ?></td>
                                    <td class="text-end text-info">Bs <?= number_format($h['total_qr'] ?? 0, 2) ?></td>
                                    <td class="text-end">
                                        <span class="text-danger">Bs <?= number_format($h['total_gastos'] ?? 0, 2) ?></span>
                                        <?php if (($h['total_gastos'] ?? 0) > 0): ?>
                                            <button class="btn btn-sm btn-link p-0 ms-1" onclick="verGastos(<?= $h['id'] ?>)" title="Ver detalle">
                                                <i class="bi bi-list-ul small"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-muted">Bs <?= number_format($h['monto_final_sistema'], 2) ?></td>
                                    <td class="text-end fw-bold">Bs <?= number_format($h['monto_final_real'], 2) ?></td>
                                    <td class="text-end">
                                        <?php if ($h['estado'] == 'cerrada'): ?>
                                            <span class="fw-bold <?= $h['diferencia'] < 0 ? 'text-danger' : ($h['diferencia'] > 0 ? 'text-warning' : 'text-success') ?>">
                                                <?= $h['diferencia'] > 0 ? '+' : '' ?> Bs <?= number_format($h['diferencia'], 2) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($h['estado'] == 'abierta'): ?>
                                            <span class="badge bg-success rounded-pill px-3">Abierta</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill px-3">Cerrada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($h['estado'] == 'cerrada'): ?>
                                            <button class="btn btn-sm btn-outline-primary rounded-pill me-1" onclick="verDetalles(<?= $h['id'] ?>)" title="Ver detalles">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="imprimirArqueo(<?= $h['id'] ?>)" title="Imprimir">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($historial)): ?>
                                <tr>
                                    <td colspan="12" class="text-center py-5 text-muted">No se encontraron registros de arqueo.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<style>
    .bg-success-soft { background-color: rgba(25, 135, 84, 0.1); }
    .table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 0; }
</style>

<!-- Modal Detalle Gastos -->
<div class="modal fade" id="modalGastos" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold">Detalle de Gastos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="listaGastos">
                    <p class="text-muted text-center">Cargando...</p>
                </div>
                <div class="border-top pt-3 mt-3">
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total:</span>
                        <span id="totalGastosModal" class="text-danger">Bs 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalles Completos -->
<div class="modal fade" id="modalDetalles" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold">Detalles del Cierre</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" tabindex="-1">
                <div id="detalleCierre">
                    <p class="text-muted text-center">Cargando...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function verGastos(idCaja) {
    const modal = new bootstrap.Modal(document.getElementById('modalGastos'));
    document.getElementById('listaGastos').innerHTML = '<p class="text-muted text-center">Cargando...</p>';
    modal.show();

    try {
        const response = await fetch(`arqueo_api.php?action=obtener_gastos&id_caja=${idCaja}`);
        const data = await response.json();

        if (data.ok) {
            let html = '';
            let total = 0;

            if (data.gastos.length === 0) {
                html = '<p class="text-muted text-center">No hay gastos registrados</p>';
            } else {
                data.gastos.forEach(g => {
                    html += `
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>${g.descripcion}</span>
                            <span class="text-danger fw-bold">- Bs ${parseFloat(g.monto).toFixed(2)}</span>
                        </div>
                    `;
                    total += parseFloat(g.monto);
                });
            }

            document.getElementById('listaGastos').innerHTML = html;
            document.getElementById('totalGastosModal').textContent = 'Bs ' + total.toFixed(2);
        } else {
            document.getElementById('listaGastos').innerHTML = '<p class="text-danger text-center">Error al cargar</p>';
        }
    } catch (err) {
        document.getElementById('listaGastos').innerHTML = '<p class="text-danger text-center">Error de conexión</p>';
    }
}

async function verDetalles(idCaja) {
    const modalEl = document.getElementById('modalDetalles');
    const modal = new bootstrap.Modal(modalEl);
    const detalleDiv = document.getElementById('detalleCierre');
    detalleDiv.innerHTML = '<p class="text-muted text-center">Cargando...</p>';
    modal.show();

    modalEl.addEventListener('shown.bs.modal', function () {
        modalEl.querySelector('.modal-body').focus();
    }, { once: true });

    try {
        const response = await fetch(`arqueo_api.php?action=obtener_detalles_cierre&id_caja=${idCaja}`);
        const data = await response.json();

        if (data.ok) {
            let html = `
                <div class="mb-4">
                    <h6 class="fw-bold mb-3">Información General</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted">Usuario:</small>
                            <div class="fw-bold">${data.info.usuario_nombre}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Fechas:</small>
                            <div>${data.info.fecha_apertura} - ${data.info.fecha_cierre}</div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold mb-3">Resumen de Ventas</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th class="text-end">Efectivo</th>
                                    <th class="text-end">QR</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            let totalEf = 0, totalQR = 0, totalGen = 0;
            data.ventas.forEach(v => {
                html += `
                    <tr>
                        <td>${v.id}</td>
                        <td>${v.cliente || 'N/A'}</td>
                        <td class="text-end">Bs ${parseFloat(v.pago_efectivo || 0).toFixed(2)}</td>
                        <td class="text-end">Bs ${parseFloat(v.pago_qr || 0).toFixed(2)}</td>
                        <td class="text-end fw-bold">Bs ${parseFloat(v.total).toFixed(2)}</td>
                    </tr>
                `;
                totalEf += parseFloat(v.pago_efectivo || 0);
                totalQR += parseFloat(v.pago_qr || 0);
                totalGen += parseFloat(v.total || 0);
            });

            html += `
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td colspan="2">Totales:</td>
                                    <td class="text-end">Bs ${totalEf.toFixed(2)}</td>
                                    <td class="text-end">Bs ${totalQR.toFixed(2)}</td>
                                    <td class="text-end">Bs ${totalGen.toFixed(2)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="mb-3">
                    <h6 class="fw-bold mb-3">Gastos Extra</h6>
            `;

            if (data.gastos.length === 0) {
                html += '<p class="text-muted">No hay gastos registrados</p>';
            } else {
                html += '<div class="list-group list-group-flush">';
                let totalGastos = 0;
                data.gastos.forEach(g => {
                    html += `
                        <div class="list-group-item px-0 d-flex justify-content-between">
                            <span>${g.descripcion}</span>
                            <span class="text-danger fw-bold">- Bs ${parseFloat(g.monto).toFixed(2)}</span>
                        </div>
                    `;
                    totalGastos += parseFloat(g.monto);
                });
                html += `</div><div class="border-top pt-2 mt-2 d-flex justify-content-between fw-bold">
                    <span>Total Gastos:</span>
                    <span class="text-danger">Bs ${totalGastos.toFixed(2)}</span>
                </div>`;
            }

            html += `</div>`;
            document.getElementById('detalleCierre').innerHTML = html;
        } else {
            document.getElementById('detalleCierre').innerHTML = '<p class="text-danger text-center">Error al cargar</p>';
        }
    } catch (err) {
        document.getElementById('detalleCierre').innerHTML = '<p class="text-danger text-center">Error de conexión</p>';
    }
}

function imprimirArqueo(idCaja) {
    window.open(`imprimir_arqueo.php?id=${idCaja}`, '_blank');
}
</script>

<?php include_once __DIR__ . '/../layout/footer.php'; ?>
