<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess('admin');

$pageTitle = 'Reportes - AbdiSoft CORE';
$active = 'reportes';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : '/Filacell/';

$extraJs = [
    'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js',
];
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
];
require_once __DIR__ . '/../layout/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="content">
        <h1 class="h4 title-strong mb-3">Módulo de Reportes</h1>

        <!-- Filtros Globales -->
        <div class="card p-3 mb-4">
            <form class="row g-3 align-items-end" id="formRep">
                <div class="col-12 col-md-2">
                    <label class="form-label form-label-compact small">Desde</label>
                    <input type="date" class="form-control themed-input" id="r_desde" required>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label form-label-compact small">Hasta</label>
                    <input type="date" class="form-control themed-input" id="r_hasta" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label form-label-compact small">Estado de Pago</label>
                    <select class="form-select themed-input" id="r_estado">
                        <option value="">Todos</option>
                        <option value="cobrado">Pagado</option>
                        <option value="pendiente">Pendiente</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label form-label-compact small">Usuario</label>
                    <select class="form-select themed-input" id="r_usuario">
                        <option value="">Todos los Usuarios</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button class="btn btn-primary w-100" type="submit" id="btnGenerar">Generar</button>
                </div>
            </form>
            <div class="alert alert-danger mt-3 d-none" id="repError"
                style="background: rgba(220,53,69,.1); border-color: #dc3545; color:#ffb3b3;"></div>
        </div>

        <!-- Navegación por Pestañas -->
        <ul class="nav nav-tabs mb-4" id="repTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabVentas" type="button"
                    role="tab">Resumen General</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabUsuarios" type="button"
                    role="tab">Ventas por Usuario</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabProductos" type="button"
                    role="tab">Ventas por Producto</button>
            </li>
        </ul>

        <div class="d-flex justify-content-end gap-2 mb-3">
            <button class="btn btn-outline-danger btn-sm" id="btnPdfGeneral" type="button">
                <i class="bi bi-file-earmark-pdf"></i> PDF General
            </button>
            <button class="btn btn-outline-danger btn-sm" id="btnPdfUsuarios" type="button">
                <i class="bi bi-file-earmark-pdf"></i> PDF Usuarios
            </button>
            <button class="btn btn-outline-danger btn-sm" id="btnPdfProductos" type="button">
                <i class="bi bi-file-earmark-pdf"></i> PDF Productos
            </button>
        </div>

        <div class="tab-content" id="repTabsContent">
            <!-- TAB: Resumen General -->
            <div class="tab-pane fade show active" id="tabVentas" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-3">
                        <div class="card p-3 border-start border-primary border-4 shadow-sm">
                            <div class="text-muted small fw-bold">Total Ventas</div>
                            <div class="h4 mb-0 fw-bold" id="kpiTotal">0.00</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="card p-3 border-start border-success border-4 shadow-sm">
                            <div class="text-muted small fw-bold">Total Efectivo</div>
                            <div class="h4 mb-0 fw-bold text-success" id="kpiEfectivo">0.00</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="card p-3 border-start border-info border-4 shadow-sm">
                            <div class="text-muted small fw-bold">Total QR</div>
                            <div class="h4 mb-0 fw-bold text-info" id="kpiQr">0.00</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="card p-3 border-start border-dark border-4 shadow-sm">
                            <div class="text-muted small fw-bold">Ganancia Neta</div>
                            <div class="h4 mb-0 fw-bold" id="kpiGanancia">0.00</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="card p-3 border-start border-danger border-4 shadow-sm">
                            <div class="text-muted small fw-bold">Descuentos</div>
                            <div class="h4 mb-0 fw-bold text-danger" id="kpiDescuento">0.00</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <div class="card p-3 h-100">
                            <h2 class="h6 title-strong mb-3">Resumen Diario</h2>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0" id="tablaDias">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th class="text-end">Ventas</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-8">
                        <div class="card p-3 h-100">
                            <h2 class="h6 title-strong mb-3">Listado de Ventas</h2>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0" id="tablaVentas" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th class="text-end">Subtotal</th>
                                            <th class="text-end">Desc.</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-end">Efec.</th>
                                            <th class="text-end">QR</th>
                                            <th>Estado</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: Ventas por Usuario -->
            <div class="tab-pane fade" id="tabUsuarios" role="tabpanel">
                <div class="card p-3">
                    <h2 class="h6 title-strong mb-3">Desempeño por Usuario (Cajeros)</h2>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tablaUsuariosRep" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th class="text-end">Cant. Ventas</th>
                                    <th class="text-end">Importe Total</th>
                                    <th class="text-end text-success">Total Efectivo</th>
                                    <th class="text-end text-info">Total QR</th>
                                    <th class="text-end fw-bold">Ganancia</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB: Ventas por Producto -->
            <div class="tab-pane fade" id="tabProductos" role="tabpanel">
                <div class="card p-3">
                    <h2 class="h6 title-strong mb-3">Ranking de Productos Vendidos</h2>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tablaProductosRep" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Código</th>
                                    <th class="text-end">Unidades</th>
                                    <th class="text-end">Monto Venta</th>
                                    <th class="text-end text-success">Ganancia</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Detalle -->
<div class="modal fade modal-themed" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Venta #<span id="detId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="tablaDetalle">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-end">Cant</th>
                                <th class="text-end">Costo</th>
                                <th class="text-end">Venta</th>
                                <th class="text-end text-success">Ganancia</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 p-2 bg-light rounded">
                    <div class="fw-bold">TOTAL DE LA VENTA:</div>
                    <div class="h5 mb-0 fw-bold" id="detTotal">0.00</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script>
    (() => {
        const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES); ?>;
        const apiUrl = baseUrl + 'views/modules/reportes_api.php';
        const rDesde = document.getElementById('r_desde');
        const rHasta = document.getElementById('r_hasta');
        const rEstado = document.getElementById('r_estado');
        const rUsuario = document.getElementById('r_usuario');
        const errorBox = document.getElementById('repError');

        function money(n) { return Number(n || 0).toFixed(2); }

        // Default dates: Today
        const todayStr = new Date().toISOString().slice(0, 10);
        rDesde.value = todayStr;
        rHasta.value = todayStr;

        const dtVentas = $('#tablaVentas').DataTable({
            columns: [
                { data: 'id', className: 'fw-bold' },
                { data: 'fecha' },
                { data: 'cliente_nombre', render: (v) => v || '<span class="text-muted small">Venta Directa</span>' },
                { data: 'subtotal', className: 'text-end', render: (v) => money(v || 0) },
                { data: 'descuento', className: 'text-end', render: (v) => Number(v || 0) > 0 ? `<span class="text-danger">-${money(v)}</span>` : '<span class="text-muted">-</span>' },
                { data: 'total', className: 'text-end fw-bold', render: (v) => money(v) },
                { data: 'pago_efectivo', className: 'text-end', render: (v) => money(v || 0) },
                { data: 'pago_qr', className: 'text-end', render: (v) => money(v || 0) },
                { data: 'estado_pago', className: 'text-center', render: (v) => v === 'cobrado' ? '<span class="badge bg-success">Pagado</span>' : '<span class="badge bg-warning text-dark">Pendiente</span>' },
                {
                    data: null, orderable: false, className: 'text-center', render: (row) => `
                <button class="btn btn-sm btn-theme-outline btn-view" data-id="${row.id}" data-total="${row.total}">
                    <i class="bi bi-eye"></i>
                </button>`
                }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
            order: [[0, 'desc']]
        });

        async function cargarReportes() {
            const d = rDesde.value;
            const h = rHasta.value;
            const s = rEstado.value;
            const u = rUsuario.value;
            if (!d || !h) return;

            const btn = document.getElementById('btnGenerar');
            btn.disabled = true;
            btn.textContent = 'Procesando...';

            try {
                // 1. General
                const resG = await fetch(`${apiUrl}?action=ventas&desde=${d}&hasta=${h}&estado=${s}&usuario_id=${u}`);
                const jsonG = await resG.json();
                if (!jsonG.ok) throw new Error(jsonG.message);

                document.getElementById('kpiTotal').textContent = money(jsonG.dias.reduce((a, b) => a + Number(b.total), 0));
                document.getElementById('kpiEfectivo').textContent = money(jsonG.dias.reduce((a, b) => a + Number(b.efectivo), 0));
                document.getElementById('kpiQr').textContent = money(jsonG.dias.reduce((a, b) => a + Number(b.qr), 0));
                document.getElementById('kpiGanancia').textContent = money(jsonG.dias.reduce((a, b) => a + Number(b.ganancia), 0));
                document.getElementById('kpiDescuento').textContent = money(jsonG.ventas.reduce((a, b) => a + Number(b.descuento || 0), 0));

                const tbodyDias = document.querySelector('#tablaDias tbody');
                tbodyDias.innerHTML = '';
                jsonG.dias.forEach(dia => {
                    tbodyDias.innerHTML += `<tr><td>${dia.dia}</td><td class="text-end">${dia.num_ventas}</td><td class="text-end fw-bold">${money(dia.total)}</td></tr>`;
                });
                dtVentas.clear().rows.add(jsonG.ventas).draw();

                // 2. Usuarios
                const resU = await fetch(`${apiUrl}?action=por_usuario&desde=${d}&hasta=${h}&usuario_id=${u}`);
                const jsonU = await resU.json();
                const tbodyU = document.querySelector('#tablaUsuariosRep tbody');
                tbodyU.innerHTML = '';
                jsonU.data.forEach(u => {
                    tbodyU.innerHTML += `<tr>
                    <td class="fw-bold">${u.usuario || 'N/A'}</td>
                    <td class="text-end">${u.num_ventas}</td>
                    <td class="text-end fw-bold">${money(u.total)}</td>
                    <td class="text-end text-success">${money(u.efectivo)}</td>
                    <td class="text-end text-info">${money(u.qr)}</td>
                    <td class="text-end fw-bold">${money(u.ganancia)}</td>
                </tr>`;
                });

                // 3. Productos
                const resP = await fetch(`${apiUrl}?action=por_producto&desde=${d}&hasta=${h}&usuario_id=${u}`);
                const jsonP = await resP.json();
                const tbodyP = document.querySelector('#tablaProductosRep tbody');
                tbodyP.innerHTML = '';
                jsonP.data.forEach(p => {
                    tbodyP.innerHTML += `<tr><td>${p.producto}</td><td><span class="badge bg-light text-dark border">${p.codigo}</span></td><td class="text-end fw-bold">${p.unidades}</td><td class="text-end">${money(p.total)}</td><td class="text-end text-success fw-bold">${money(p.ganancia)}</td></tr>`;
                });

            } catch (e) {
                console.error(e);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Generar Reportes';
            }
        }

        document.getElementById('formRep').addEventListener('submit', (e) => {
            e.preventDefault();
            cargarReportes();
        });

        async function cargarUsuarios() {
            try {
                const res = await fetch(baseUrl + 'views/modules/usuarios_api.php?action=list');
                const json = await res.json();
                if (json.ok) {
                    json.data.forEach(user => {
                        const opt = document.createElement('option');
                        opt.value = user.id;
                        opt.textContent = user.nombre + ' (' + user.rol + ')';
                        rUsuario.appendChild(opt);
                    });
                }
            } catch (e) {
                console.error('Error cargando usuarios:', e);
            }
        }

        cargarUsuarios();

        $('#tablaVentas').on('click', '.btn-view', async function () {
            const id = this.getAttribute('data-id');
            const total = this.getAttribute('data-total');
            document.getElementById('detId').textContent = id;
            document.getElementById('detTotal').textContent = money(total);
            const tbodyDet = document.querySelector('#tablaDetalle tbody');
            tbodyDet.innerHTML = '<tr><td colspan="5">Cargando...</td></tr>';
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();

            try {
                const res = await fetch(`${apiUrl}?action=detalle&id=${id}`);
                const json = await res.json();
                tbodyDet.innerHTML = '';
                json.items.forEach(it => {
                    const gan = Number(it.subtotal) - (Number(it.cantidad) * Number(it.precio_compra));
                    tbodyDet.innerHTML += `<tr><td>${it.nombre}</td><td class="text-end">${it.cantidad}</td><td class="text-end text-muted">${money(it.precio_compra)}</td><td class="text-end fw-bold">${money(it.precio_unitario)}</td><td class="text-end text-success fw-bold">${money(gan)}</td></tr>`;
                });
            } catch (e) { }
        });

        // PDF Buttons
        function getFilters() {
            const d = rDesde.value;
            const h = rHasta.value;
            const s = rEstado.value;
            const u = rUsuario.value;
            return `desde=${d}&hasta=${h}&estado=${s}&usuario_id=${u}`;
        }

        document.getElementById('btnPdfGeneral').addEventListener('click', () => {
            window.open(baseUrl + 'views/modules/reportes_pdf.php?tipo=general&' + getFilters(), '_blank');
        });
        document.getElementById('btnPdfUsuarios').addEventListener('click', () => {
            window.open(baseUrl + 'views/modules/reportes_pdf.php?tipo=usuarios&' + getFilters(), '_blank');
        });
        document.getElementById('btnPdfProductos').addEventListener('click', () => {
            window.open(baseUrl + 'views/modules/reportes_pdf.php?tipo=productos&' + getFilters(), '_blank');
        });

        cargarReportes();
    })();
</script>