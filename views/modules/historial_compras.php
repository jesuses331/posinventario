<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess();

$pageTitle = 'Historial de Compras - AbdiSoft CORE';
$active = 'historial_compras';
$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '/';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
                <h1 class="h3 title-strong mb-1">Historial de Compras</h1>
                <div class="text-muted">Consulta los ingresos de mercadería realizados.</div>
            </div>
        </div>

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

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table id="tablaCompras" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Fecha/Hora</th>
                                <th>Usuario</th>
                                <th class="text-end">Total Inversión</th>
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

<!-- Modal Detalle Compra -->
<div class="modal fade modal-themed" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-bag-check me-2"></i> Detalle de Compra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detalleBody">
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

    const table = $('#tablaCompras').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        ajax: {
            url: apiUrl,
            data: function(d) {
                d.action = 'list_compras';
                d.start = $('#f_start').val();
                d.end = $('#f_end').val();
            }
        },
        columns: [
            { data: 'id' },
            { data: 'fecha', render: (v) => v ? new Date(v).toLocaleString() : '' },
            { data: 'usuario' },
            { 
                data: 'total', 
                className: 'text-end fw-bold',
                render: (v) => moneda + ' ' + Number(v).toFixed(2)
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
            const res = await fetch(`${apiUrl}?action=compra_detalle&id=${id}`);
            const json = await res.json();
            if (!json.ok) throw new Error(json.message);

            const c = json.compra;
            let html = `
                <div class="row mb-4">
                    <div class="col-6">
                        <h6 class="text-muted small text-uppercase fw-bold mb-3">Información de Ingreso</h6>
                        <div class="mb-1"><strong>Compra ID:</strong> #${c.id}</div>
                        <div class="mb-1"><strong>Fecha:</strong> ${new Date(c.fecha).toLocaleString()}</div>
                        <div class="mb-1"><strong>Usuario:</strong> ${c.usuario}</div>
                    </div>
                </div>
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-end">P. Compra</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${json.detalle.map(d => `
                            <tr>
                                <td>${d.producto}<br><small class="text-muted">${d.codigo}</small></td>
                                <td class="text-center">${d.cantidad}</td>
                                <td class="text-end">${moneda} ${Number(d.precio_compra1).toFixed(2)}</td>
                                <td class="text-end fw-bold">${moneda} ${Number(d.subtotal).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end py-3">TOTAL INVERSIÓN</th>
                            <th class="text-end py-3 text-primary h5 fw-bold">${moneda} ${Number(c.total).toFixed(2)}</th>
                        </tr>
                    </tfoot>
                </table>
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
