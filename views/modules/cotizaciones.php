<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess();

$pageTitle = 'Historial de Cotizaciones - AbdiSoft CORE';
$active = 'cotizaciones';
$baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : '/';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$csrfToken = $_SESSION['csrf_token'] ?? '';

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
                <h1 class="h3 title-strong mb-1">Historial de Cotizaciones</h1>
                <p class="text-muted mb-0">Administra y consulta las cotizaciones realizadas.</p>
            </div>
            <a href="pos.php" class="btn btn-theme-outline rounded-pill">
                <i class="bi bi-cart-plus me-2"></i> Nueva Venta
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table id="tablaCotizaciones" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Usuario</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Válido hasta</th>
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

<!-- Modal Detalle Cotización -->
<div class="modal fade modal-themed" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-text me-2"></i> Detalle de Cotización</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detalleBody">
                <div class="text-center p-5">
                    <div class="spinner-border text-secondary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-lg px-4" id="btnAnular">
                        <i class="bi bi-x-circle me-2"></i> Anular
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-light btn-lg px-4" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success btn-lg px-4 shadow-sm" id="btnPrint">
                        <i class="bi bi-printer me-2"></i> Imprimir
                    </button>
                    <button type="button" class="btn btn-primary btn-lg px-4 shadow-sm" id="btnConvertirVenta">
                        <i class="bi bi-cash-stack me-2"></i> Convertir en Venta
                    </button>
                </div>
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

    .badge-activa { background-color: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
    .badge-aceptada { background-color: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
    .badge-vencida { background-color: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
    .badge-cancelada { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

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
let currentCotizacionId = null;

$(document).ready(function() {
    const apiUrl = '<?= $baseUrl ?>views/modules/cotizaciones_api.php';
    const moneda = '<?= $moneda ?>';

    const table = $('#tablaCotizaciones').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        ajax: {
            url: apiUrl,
            data: function(d) {
                d.action = 'list_cotizaciones';
            }
        },
        columns: [
            { data: 'codigo', render: (v) => `<span class="fw-bold">${v}</span>` },
            { data: 'created_at', render: (v) => v ? new Date(v).toLocaleString() : '' },
            { data: 'cliente', render: (v) => v || '<span class="text-muted small">Sin cliente</span>' },
            { data: 'usuario' },
            {
                data: 'total',
                className: 'text-end fw-bold',
                render: (v) => moneda + ' ' + Number(v).toFixed(2)
            },
            {
                data: 'fecha_validez',
                className: 'text-center',
                render: function(v, type, row) {
                    if (!v) return '<span class="text-muted">-</span>';
                    const hoy = new Date();
                    const val = new Date(v + 'T23:59:59');
                    const days = Math.ceil((val - hoy) / (1000 * 60 * 60 * 24));
                    if (days < 0) return `<span class="text-danger fw-bold">${v} (Vencida)</span>`;
                    if (days <= 3) return `<span class="text-warning fw-bold">${v} (${days}d)</span>`;
                    return v;
                }
            },
            {
                data: 'estado',
                className: 'text-center',
                render: (v) => {
                    const cls = 'badge-' + v;
                    return `<span class="badge ${cls} text-uppercase" style="font-size:0.7rem">${v}</span>`;
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: (row) => `
                    <button class="btn btn-sm btn-outline-secondary border-0 btn-view" data-id="${row.id}" title="Ver Detalle">
                        <i class="bi bi-eye-fill" style="font-size:1.1rem"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger border-0 btn-delete" data-id="${row.id}" title="Eliminar">
                        <i class="bi bi-trash-fill" style="font-size:1.1rem"></i>
                    </button>
                `
            }
        ],
        order: [[0, 'desc']]
    });

    // Ver Detalle
    $(document).on('click', '.btn-view', async function() {
        const id = $(this).data('id');
        currentCotizacionId = id;
        const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
        $('#detalleBody').html('<div class="text-center p-5"><div class="spinner-border text-secondary"></div></div>');
        modal.show();

        try {
            const res = await fetch(`${apiUrl}?action=get_cotizacion&id=${id}`);
            const json = await res.json();
            if (!json.ok) throw new Error(json.message);

            const c = json.cotizacion;
            const items = json.detalle || [];

            document.getElementById('btnAnular').style.display = c.estado === 'activa' ? '' : 'none';
            document.getElementById('btnConvertirVenta').style.display = c.estado === 'activa' ? '' : 'none';

            let html = `
                <div class="row mb-4">
                    <div class="col-6">
                        <h6 class="text-muted small text-uppercase fw-bold mb-3">Información</h6>
                        <div class="mb-1"><strong>Código:</strong> ${c.codigo}</div>
                        <div class="mb-1"><strong>Fecha:</strong> ${new Date(c.created_at).toLocaleString()}</div>
                        <div class="mb-1"><strong>Usuario:</strong> ${c.usuario}</div>
                        <div class="mb-1"><strong>Validez:</strong> ${c.fecha_validez || 'No definida'}</div>
                    </div>
                    <div class="col-6 text-end">
                        <h6 class="text-muted small text-uppercase fw-bold mb-3">Cliente</h6>
                        <div class="mb-1"><strong>Nombre:</strong> ${c.cliente || 'Sin cliente'}</div>
                        <div class="mt-2"><span class="badge badge-${c.estado} text-uppercase">${c.estado}</span></div>
                    </div>
                </div>
                ${c.notas ? `<div class="alert alert-light mb-4"><strong>Notas:</strong> ${c.notas}</div>` : ''}
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Desc.</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(d => `
                            <tr>
                                <td>${d.nombre}<br><small class="text-muted">${d.codigo || ''}</small></td>
                                <td class="text-center">${d.cantidad}</td>
                                <td class="text-end">${moneda} ${Number(d.precio_unitario).toFixed(2)}</td>
                                <td class="text-end">${Number(d.descuento || 0) > 0 ? Number(d.descuento) + '%' : '-'}</td>
                                <td class="text-end fw-bold">${moneda} ${Number(d.subtotal).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end py-3">TOTAL</th>
                            <th class="text-end py-3 text-secondary h5 fw-bold">${moneda} ${Number(c.total).toFixed(2)}</th>
                        </tr>
                    </tfoot>
                </table>
            `;
            $('#detalleBody').html(html);
        } catch (err) {
            $('#detalleBody').html(`<div class="alert alert-danger">${err.message}</div>`);
        }
    });

    // Imprimir
    $('#btnPrint').on('click', function() {
        if (currentCotizacionId) {
            window.open('cotizacion_detalle.php?id=' + currentCotizacionId, '_blank');
        }
    });

    // Anular
    $('#btnAnular').on('click', async function() {
        if (!currentCotizacionId) return;

        const result = await Swal.fire({
            title: '¿Anular cotización?',
            text: 'La cotización pasará a estado "cancelada".',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        });

        if (!result.isConfirmed) return;

        try {
            const formData = new FormData();
            formData.append('action', 'update_cotizacion_estado');
            formData.append('id', currentCotizacionId);
            formData.append('estado', 'cancelada');
            formData.append('csrf_token', '<?= $csrfToken ?>');

            const res = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });
            const json = await res.json();
            if (!json.ok) throw new Error(json.message);

            Swal.fire({
                icon: 'success',
                title: 'Anulada',
                text: 'Cotización anulada correctamente.',
                timer: 2000,
                showConfirmButton: false
            });

            bootstrap.Modal.getInstance(document.getElementById('modalDetalle')).hide();
            table.ajax.reload();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
    });

    // Convertir en Venta
    $('#btnConvertirVenta').on('click', async function() {
        if (!currentCotizacionId) return;

        const result = await Swal.fire({
            title: '¿Convertir en venta?',
            text: 'Se creará una venta basada en esta cotización. El stock se descontará.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, convertir',
            cancelButtonText: 'Cancelar'
        });

        if (!result.isConfirmed) return;

        try {
            const formData = new FormData();
            formData.append('action', 'convertir_cotizacion_a_venta');
            formData.append('id', currentCotizacionId);
            formData.append('csrf_token', '<?= $csrfToken ?>');

            const res = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });
            const json = await res.json();
            if (!json.ok) throw new Error(json.message);

            Swal.fire({
                icon: 'success',
                title: 'Venta Creada',
                text: `Venta #${json.venta_id} generada a partir de la cotización.`,
                timer: 3000,
                showConfirmButton: false
            });

            bootstrap.Modal.getInstance(document.getElementById('modalDetalle')).hide();
            table.ajax.reload();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
    });

    // Eliminar
    $(document).on('click', '.btn-delete', async function() {
        const id = $(this).data('id');

        const result = await Swal.fire({
            title: '¿Eliminar cotización?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (!result.isConfirmed) return;

        try {
            const formData = new FormData();
            formData.append('action', 'delete_cotizacion');
            formData.append('id', id);
            formData.append('csrf_token', '<?= $csrfToken ?>');

            const res = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });
            const json = await res.json();
            if (!json.ok) throw new Error(json.message);

            Swal.fire({
                icon: 'success',
                title: 'Eliminada',
                text: 'Cotización eliminada correctamente.',
                timer: 2000,
                showConfirmButton: false
            });

            table.ajax.reload();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
    });
});
</script>
