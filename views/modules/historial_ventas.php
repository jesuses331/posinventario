<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess();

$pageTitle = 'Historial de Ventas - AbdiSoft CORE';
$active = 'historial_ventas';
$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '/';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Config moneda
$moneda = 'Bs';
try {
    $db = (new Database())->getConnection();
    $stmtCfg = $db->query("SELECT moneda FROM configuracion ORDER BY updated_at DESC LIMIT 1");
    $rowCfg = $stmtCfg->fetch();
    if ($rowCfg) $moneda = $rowCfg['moneda'];
} catch (Throwable $e) {}

$extraCss = [
    'https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
];
$extraJs = [
    'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
    'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
];

require_once __DIR__ . '/../layout/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="content">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 title-strong mb-1">Historial de Ventas</h1>
                <div class="text-muted">Consulta y gestiona las ventas realizadas.</div>
            </div>
        </div>

        <?php if (Auth::isAdmin()): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form id="filterForm" class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-bold small text-muted">Fecha Inicio</label>
                        <input type="date" class="form-control" id="f_start" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-bold small text-muted">Fecha Fin</label>
                        <input type="date" class="form-control" id="f_end" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12 col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table id="tablaVentas" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Fecha/Hora</th>
                                <th>Cliente</th>
                                <th>Usuario</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-end">Desc.</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Efectivo</th>
                                <th class="text-end">QR</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Detalle Venta -->
<div class="modal fade modal-themed" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2"></i> Detalle de Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detalleBody">
                <!-- Se carga vía JS -->
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light btn-lg px-4" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success btn-lg px-4 shadow-sm" id="btnPrint">
                    <i class="bi bi-printer me-2"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .app-shell { background-color: #f8fafc; min-height: 100vh; }
    .content { padding: 2rem; }
    .card { border-radius: 1rem; overflow: hidden; }
    .title-strong { font-weight: 800; color: #1e293b; }
    
    .table thead th {
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #64748b;
        background-color: #f1f5f9;
        border-bottom: 0;
    }

    .badge-cobrado { background-color: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
    .badge-pendiente { background-color: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }

    @media print {
        body * { visibility: hidden; }
        #modalDetalle, #modalDetalle * { visibility: visible; }
        #modalDetalle { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; }
        .btn-close, .modal-footer { display: none !important; }
        .modal-header { background: #fff !important; color: #000 !important; border-bottom: 1px solid #eee !important; }
        .modal-content { border: none !important; box-shadow: none !important; }
    }
</style>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script>
$(document).ready(function() {
    const apiUrl = '<?= $baseUrl ?>views/modules/historial_api.php';
    const moneda = '<?= $moneda ?>';

    const table = $('#tablaVentas').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        ajax: {
            url: apiUrl,
            data: function(d) {
                d.action = 'list_ventas';
                d.start = $('#f_start').val();
                d.end = $('#f_end').val();
            }
        },
        columns: [
            { data: 'id' },
            { data: 'fecha', render: (v) => v ? new Date(v).toLocaleString() : '' },
            { data: 'cliente', render: (v) => v || '<span class="text-muted small">Venta Directa</span>' },
            { data: 'usuario' },
            {
                data: 'subtotal',
                className: 'text-end',
                render: (v) => moneda + ' ' + Number(v || 0).toFixed(2)
            },
            {
                data: 'descuento',
                className: 'text-end',
                render: (v) => {
                    const d = Number(v || 0);
                    return d > 0 ? `<span class="text-danger">-${moneda} ${d.toFixed(2)}</span>` : '<span class="text-muted">-</span>';
                }
            },
            {
                data: 'total',
                className: 'text-end fw-bold',
                render: (v) => moneda + ' ' + Number(v).toFixed(2)
            },
            {
                data: 'pago_efectivo',
                className: 'text-end',
                render: (v) => moneda + ' ' + Number(v || 0).toFixed(2)
            },
            {
                data: 'pago_qr',
                className: 'text-end',
                render: (v) => moneda + ' ' + Number(v || 0).toFixed(2)
            },
            {
                data: 'estado_pago',
                className: 'text-center',
                render: (v) => {
                    const cls = v === 'cobrado' ? 'badge-cobrado' : 'badge-pendiente';
                    return `<span class="badge ${cls} text-uppercase" style="font-size:0.7rem">${v}</span>`;
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: (row) => `
                    <button class="btn btn-sm btn-outline-primary border-0 btn-view" data-id="${row.id}" title="Ver Detalle">
                        <i class="bi bi-eye-fill" style="font-size:1.1rem"></i>
                    </button>
                `
            }
        ],
        order: [[0, 'desc']]
    });

    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });

    // Ver Detalle
    $(document).on('click', '.btn-view', async function() {
        const id = $(this).data('id');
        const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
        $('#detalleBody').html('<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>');
        modal.show();

        try {
            const res = await fetch(`${apiUrl}?action=venta_detalle&id=${id}`);
            const json = await res.json();
            if (!json.ok) throw new Error(json.message);

            const v = json.venta;
            const desc = Number(v.descuento || 0);
            const subtotal = Number(v.total) + desc;
            let html = `
                <div class="row mb-4">
                    <div class="col-6">
                        <h6 class="text-muted small text-uppercase fw-bold mb-3">Información de Venta</h6>
                        <div class="mb-1"><strong>ID:</strong> #${v.id}</div>
                        <div class="mb-1"><strong>Fecha:</strong> ${new Date(v.fecha).toLocaleString()}</div>
                        <div class="mb-1"><strong>Usuario:</strong> ${v.usuario}</div>
                        <div class="mb-1"><strong>Método:</strong> ${v.metodo_pago || 'N/A'}</div>
                    </div>
                    <div class="col-6 text-end">
                        <h6 class="text-muted small text-uppercase fw-bold mb-3">Cliente</h6>
                        <div class="mb-1"><strong>Nombre:</strong> ${v.cliente || 'Venta Directa'}</div>
                        ${v.cliente_cedula ? `<div class="mb-1"><strong>C.I./NIT:</strong> ${v.cliente_cedula}</div>` : ''}
                        <div class="mt-2"><span class="badge ${v.estado_pago === 'cobrado' ? 'badge-cobrado' : 'badge-pendiente'} text-uppercase">${v.estado_pago}</span></div>
                    </div>
                </div>
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${json.detalle.map(d => `
                            <tr>
                                <td>${d.producto}<br><small class="text-muted">${d.codigo}</small></td>
                                <td class="text-center">${d.cantidad}</td>
                                <td class="text-end">${moneda} ${Number(d.precio_unitario).toFixed(2)}</td>
                                <td class="text-end fw-bold">${moneda} ${Number(d.subtotal).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end py-1">SUBTOTAL</th>
                            <th class="text-end py-1">${moneda} ${subtotal.toFixed(2)}</th>
                        </tr>
                        ${desc > 0 ? `
                        <tr>
                            <th colspan="3" class="text-end py-1 text-danger">DESCUENTO</th>
                            <th class="text-end py-1 text-danger fw-bold">-${moneda} ${desc.toFixed(2)}</th>
                        </tr>
                        ` : ''}
                        <tr>
                            <th colspan="3" class="text-end py-3">TOTAL</th>
                            <th class="text-end py-3 text-primary h5 fw-bold">${moneda} ${Number(v.total).toFixed(2)}</th>
                        </tr>
                    </tfoot>
                </table>
                <div class="row mt-3 pt-3 border-top">
                    <div class="col-6">
                        <small class="text-muted">Efectivo: ${moneda} ${Number(v.pago_efectivo || 0).toFixed(2)}</small>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted">QR: ${moneda} ${Number(v.pago_qr || 0).toFixed(2)}</small>
                    </div>
                </div>
            `;
            $('#detalleBody').html(html);
        } catch (err) {
            $('#detalleBody').html(`<div class="alert alert-danger">${err.message}</div>`);
        }
    });

    $('#btnPrint').on('click', function() {
        window.print();
    });
});
</script>
