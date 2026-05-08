<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess();
require_once __DIR__ . '/../../config/verificar_caja.php';

$pageTitle = 'Nueva Venta (POS) - AbdiSoft CORE';
$active = 'pos';
$baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : '/';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Config (fila única)
$moneda = 'Bs';
try {
    $db = (new Database())->getConnection();
    $stmtCfg = $db->query("SELECT moneda FROM configuracion ORDER BY updated_at DESC LIMIT 1");
    $rowCfg = $stmtCfg->fetch();
    if ($rowCfg && isset($rowCfg['moneda'])) {
        $moneda = (string) $rowCfg['moneda'];
    }
} catch (Throwable $e) {
}

$extraCss = [
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
];
$extraJs = [
    'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
];
require_once __DIR__ . '/../layout/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="content">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 title-strong mb-1">Nueva Venta (POS)</h1>
                <div class="text-muted" style="font-size:.92rem">Búsqueda por código o nombre · rápido · seguro.</div>
            </div>
            <button class="btn btn-theme-outline" id="btnLimpiar" type="button">Limpiar</button>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-7">
                <div class="card p-3">
                    <label class="form-label form-label-compact">Cliente</label>
                    <div class="input-group mb-3">
                        <select class="form-select themed-input" id="clienteSelect">
                            <option value="">-- Sin cliente (Venta directa) --</option>
                        </select>
                        <button class="btn btn-theme-outline" type="button" id="btnNuevoCliente" title="Nuevo Cliente">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>

                    <label class="form-label form-label-compact">Buscar producto</label>
                    <div class="input-group">
                        <input class="form-control themed-input" id="q" placeholder="Ej: Cargador win o 12345">
                        <button class="btn btn-primary" id="btnBuscar" type="button">Buscar</button>
                    </div>
                    <div class="text-muted mt-2" style="font-size:.85rem" id="hint">Escribe y presiona Enter.</div>

                    <div class="mt-3" id="resultados"></div>

                    <hr>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <div class="form-label-compact">Lista de Productos</div>
                            <div class="text-muted" style="font-size:.9rem">Resultados de la búsqueda principal</div>
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 50vh; overflow:auto;">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-end">P.1</th>
                                    <th class="text-end">Venta</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="productosBody">
                                <tr>
                                    <td colspan="3" class="text-muted">Cargando...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="text-muted" style="font-size:.85rem" id="paginationInfo">Página 1 de 1</div>
                        <div>
                            <button class="btn btn-sm btn-theme-outline" id="btnPrev" disabled>&laquo;</button>
                            <button class="btn btn-sm btn-theme-outline" id="btnNext"> &raquo;</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="card p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <div class="form-label-compact">Carrito</div>
                            <div class="text-muted" style="font-size:.9rem">Items: <span id="itemsCount">0</span></div>
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 45vh; overflow:auto;">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-end" style="width:70px">Cant</th>
                                    <th class="text-end" style="width:110px">P. Venta</th>
                                    <th class="text-end" style="width:90px">Sub</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="carritoBody">
                                <tr>
                                    <td colspan="4" class="text-muted text-center py-4">Sin productos.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Total fijo abajo -->
                    <div class="cart-total-section mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Subtotal</span>
                            <span class="fw-bold" id="subtotalDisplay"><?php echo htmlspecialchars($moneda); ?>
                                0.00</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Descuento</span>
                            <div class="d-flex align-items-center gap-1">
                                <span class="small text-muted"><?php echo htmlspecialchars($moneda); ?></span>
                                <input type="number" step="0.01" min="0" id="discountInput"
                                    class="form-control form-control-sm text-end" style="width:110px" value="0.00"
                                    placeholder="0.00">
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-bold">Total a pagar</span>
                            <div class="cart-total-display">
                                <span class="cart-currency"><?php echo htmlspecialchars($moneda); ?></span>
                                <span class="cart-total-amount" id="total">0.00</span>
                            </div>
                        </div>

                        <div class="alert alert-danger d-none" id="posError"
                            style="background: rgba(220,53,69,.1); border-color: #dc3545; color:#ffb3b3;"></div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg" id="btnCobrar" type="button">
                                <i class="bi bi-cash-stack me-2"></i>Cobrar
                            </button>
                            <button class="btn btn-secondary btn-lg" id="btnCotizar" type="button">
                                <i class="bi bi-file-text me-2"></i>Cotizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Nuevo Cliente -->
<div class="modal fade modal-themed" id="modalNuevoCliente" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevoCliente">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="mb-3">
                        <label class="form-label form-label-compact">Nombre Completo</label>
                        <input type="text" name="nombre" class="form-control themed-input" required
                            placeholder="Ej: Juan Pérez">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form-label-compact">Cédula</label>
                            <input type="text" name="cedula" class="form-control themed-input"
                                placeholder="Ej: 1234567">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form-label-compact">Teléfono</label>
                            <input type="text" name="telefono" class="form-control themed-input"
                                placeholder="Ej: 71234567">
                        </div>
                    </div>
                    <div class="alert alert-danger d-none" id="clienteError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-theme-outline" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCliente">Guardar Clientes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<style>
    /* Estética profesional POS */
    .app-shell {
        min-height: 100vh;
    }

    h1.title-strong {
        font-size: 1.35rem;
        letter-spacing: .02em;
    }

    .card {
        border: 0;
        border-radius: 0.75rem;
        box-shadow: 0 12px 28px rgba(22, 41, 77, .08);
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 33px rgba(22, 41, 77, .14);
    }

    .form-label-compact {
        font-weight: 600;
        color: #4a5568;
    }

    #hint {
        color: #6b7280;
    }

    #resultados {
        max-height: 210px;
        overflow-y: auto;
    }

    .pos-product-item {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: .45rem;
        text-align: left;
        padding: .75rem;
        min-height: 68px;
    }

    .pos-product-item:hover {
        border-color: #3b82f6;
        background: rgba(59, 130, 246, .08);
    }

    .text-code-stock {
        color: var(--text);
    }

    .text-code-stock strong {
        color: var(--heading);
    }

    .stock-low {
        color: #f87171 !important;
        font-weight: 700;
    }

    #btnLimpiar,
    #btnBuscar,
    #btnCobrar {
        border-radius: .6rem;
    }

    #btnCobrar {
        background: linear-gradient(120deg, #2c5282, #2563eb);
        border-color: transparent;
        color: #fff;
        font-weight: 600;
    }

    #btnCobrar:hover {
        background: linear-gradient(120deg, #1e40af, #1d4ed8);
    }

    .kpi {
        font-size: 1.4rem;
        font-weight: 700;
        color: #1e3a8a;
    }

    .table th,
    .table td {
        vertical-align: middle;
        border-top: 0;
    }

    .table-responsive {
        min-height: 260px;
    }

    .alert {
        border-radius: .55rem;
    }

    /* Mejora UX responsiva para POS */
    @media (max-width: 991px) {

        .card .d-flex.justify-content-between,
        .d-flex.align-items-center.justify-content-between {
            flex-wrap: wrap;
            gap: .5rem;
        }

        .d-flex.align-items-center.justify-content-between>* {
            min-width: 100%;
        }

        #btnPrev,
        #btnNext {
            width: 48%;
        }
    }

    @media (max-width: 767px) {
        .card {
            padding: 0.85rem !important;
        }

        .input-group {
            flex-wrap: wrap;
            gap: .5rem;
        }

        .input-group .form-control {
            flex: 0 0 100%;
        }

        .input-group .btn {
            width: 100%;
        }

        #btnBuscar {
            width: 100%;
            margin-top: .35rem;
        }

        #resultados {
            max-height: 160px;
        }

        .table-responsive {
            max-height: 42vh;
        }

        .table th,
        .table td {
            padding: .35rem .45rem;
        }
    }

    @media (max-width: 575px) {

        .col-12.col-lg-5,
        .col-12.col-lg-7 {
            flex: 0 0 100%;
            max-width: 100%;
        }

        #btnPrev,
        #btnNext {
            width: 100%;
        }

        #paginationInfo {
            width: 100%;
            text-align: center;
        }

        .kpi {
            font-size: 1.1rem;
        }
    }

    /* Estilos Premium para selector de costos */
    .cost-selector-group {
        display: flex;
        gap: 2px;
        justify-content: center;
    }

    .btn-xs {
        padding: 0.15rem 0.3rem;
        font-size: 0.7rem;
        font-weight: 700;
        border-radius: 4px;
        min-width: 58px;
    }

    .precio-venta-input {
        font-weight: 700;
        color: var(--accent) !important;
    }

    .btn-outline-primary {
        color: #3b82f6;
        border-color: #3b82f6;
        background-color: transparent;
    }

    [data-theme="dark"] .btn-outline-primary {
        border-color: rgba(255, 255, 255, 0.2);
    }

    .btn-check:checked+.btn-outline-primary {
        background-color: #3b82f6 !important;
        border-color: #3b82f6 !important;
        color: #fff !important;
    }

    .btn-light.text-danger:hover {
        background-color: #fee2e2;
        border-color: #ef4444;
    }

    /* Estilos para el total del carrito */
    .cart-total-section {
        background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
        border-radius: 12px;
        padding: 1rem 1.25rem !important;
        margin: 0 -0.5rem;
    }

    .cart-total-display {
        display: flex;
        align-items: baseline;
        gap: 6px;
    }

    .cart-currency {
        font-size: 1rem;
        font-weight: 600;
        color: #64748b;
    }

    .cart-total-amount {
        font-size: 2rem;
        font-weight: 800;
        color: #1e3a8a;
        letter-spacing: -1px;
        font-family: 'Inter', monospace;
    }

    #btnCobrar {
        background: linear-gradient(135deg, #00aeef 0%, #0081b1 100%);
        border: none;
        font-weight: 700;
        letter-spacing: 0.5px;
        padding: 0.9rem;
        font-size: 1.1rem;
        box-shadow: 0 4px 15px rgba(0, 174, 239, 0.3);
        transition: all 0.3s ease;
    }

    #btnCobrar:hover {
        background: linear-gradient(135deg, #0081b1 0%, #006a94 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 174, 239, 0.4);
    }

    #btnCobrar:active {
        transform: translateY(0);
    }

    /* Estilos mejorados para items del carrito */
    #carritoBody tr {
        transition: background-color 0.2s ease;
    }

    #carritoBody tr:hover {
        background-color: rgba(0, 174, 239, 0.05);
    }

    #carritoBody .qty,
    #carritoBody .precio-venta-input {
        border-radius: 6px;
        font-weight: 600;
    }

    #carritoBody .del {
        transition: all 0.2s ease;
    }

    #carritoBody .del:hover {
        transform: scale(1.1);
    }

    /* Responsive adjustments */
    @media (max-width: 991px) {
        .cart-total-amount {
            font-size: 1.6rem;
        }

        .cart-total-section {
            padding: 0.75rem 1rem !important;
        }
    }

    @media (max-width: 575px) {
        .cart-total-amount {
            font-size: 1.4rem;
        }
    }
</style>

<script>
    (() => {
        const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES); ?>;
        const apiUrl = baseUrl + 'views/modules/pos_api.php';
        const apiClientesUrl = baseUrl + 'views/modules/clientes_api.php';
        const apiProductosUrl = baseUrl + 'views/modules/productos_api.php';
        const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;
        const moneda = <?php echo json_encode($moneda, JSON_UNESCAPED_UNICODE); ?>;

        const q = document.getElementById('q');
        const clienteSelect = document.getElementById('clienteSelect');
        const resultados = document.getElementById('resultados');
        const productosBody = document.getElementById('productosBody');
        const paginationInfo = document.getElementById('paginationInfo');
        const btnPrev = document.getElementById('btnPrev');
        const btnNext = document.getElementById('btnNext');
        const carritoBody = document.getElementById('carritoBody');
        const totalEl = document.getElementById('total');
        const itemsCountEl = document.getElementById('itemsCount');
        const posError = document.getElementById('posError');
        const subtotalDisplay = document.getElementById('subtotalDisplay');

        let carrito = []; // {producto_id,codigo,nombre,precio_compra1,precio_compra2,precio_venta,stock_actual,cantidad,precio_usado,precio_tipo,precio_compra_aplicado}

        let descuentoGlobal = 0;

        function money(n) {
            const x = Number(n || 0);
            return x.toFixed(2);
        }

        function showError(msg) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: msg || 'Ha ocurrido un error',
                toast: true,
                position: 'top-end',
                timer: 4000,
                showConfirmButton: false
            });
        }

        function clearError() {
            posError.classList.add('d-none');
            posError.textContent = '';
        }

        function calc() {
            let subtotal = 0;
            let items = 0;
            for (const it of carrito) {
                subtotal += Number(it.precio_usado) * Number(it.cantidad);
                items += 1;
            }
            const d = Number(descuentoGlobal || 0);
            const total = Math.max(0, subtotal - d);
            if (subtotalDisplay) {
                subtotalDisplay.textContent = moneda + ' ' + money(subtotal);
            }
            totalEl.textContent = money(total);
            itemsCountEl.textContent = String(items);
        }

        function renderCarrito() {
            carritoBody.innerHTML = '';
            if (!carrito.length) {
                carritoBody.innerHTML = `<tr><td colspan="5" class="text-muted text-center p-4">Sin productos en el carrito.</td></tr>`;
                calc();
                return;
            }

            for (const it of carrito) {
                const sub = Number(it.precio_usado) * Number(it.cantidad);
                const imgHtml = it.imagen ? `<img src="${baseUrl}${it.imagen}" alt="${escapeHtml(it.nombre)}" style="width: 35px; height: 35px; object-fit: cover; border-radius: 6px; margin-right: 8px;">` : '';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>
                    <div class="d-flex align-items-center">
                        ${imgHtml}
                        <div>
                            <div class="text-heading" style="font-weight:700; font-size:.92rem">${escapeHtml(it.nombre)}</div>
                            <div class="text-muted" style="font-size:.75rem">${escapeHtml(it.codigo)} · stock: ${escapeHtml(String(it.stock_actual))}</div>
                        </div>
                    </div>
                </td>
                <td class="text-end" style="width:70px">
                    <input type="number" step="1" min="1" class="form-control form-control-sm themed-input text-center qty"
                           value="${escapeHtml(String(it.cantidad))}" style="padding: 0.1rem 0.2rem;">
                </td>
                <td class="text-end" style="width:110px">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm themed-input text-end precio-venta-input"
                           value="${escapeHtml(String(it.precio_usado))}">
                </td>
                <td class="text-end text-heading" style="font-weight:700; font-size:.95rem">${money(sub)}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-theme-outline text-danger del" title="Eliminar" style="border:none;">
                        <i class="bi bi-trash3-fill" style="font-size: 1.1rem;"></i>
                    </button>
                </td>
            `;

                tr.querySelector('.del').addEventListener('click', () => {
                    carrito = carrito.filter(x => x.producto_id !== it.producto_id);
                    renderCarrito();
                });
                tr.querySelector('.qty').addEventListener('change', (ev) => {
                    const v = Number(ev.target.value || 1);
                    it.cantidad = Math.max(1, Math.floor(v));
                    renderCarrito();
                });
                tr.querySelector('.precio-venta-input').addEventListener('change', (ev) => {
                    const v = Number(ev.target.value || 0);
                    it.precio_usado = Math.max(0, v);
                    renderCarrito();
                });
                carritoBody.appendChild(tr);
            }
            calc();
        }

        async function loadClientes() {
            try {
                const res = await fetch(apiClientesUrl + '?action=list_for_pos', { credentials: 'same-origin' });
                const json = await res.json();
                if (!json.ok) throw new Error(json.message || 'Error cargando clientes');

                const clientes = json.data || [];
                clienteSelect.innerHTML = '<option value="">-- Sin cliente (Venta directa) --</option>';
                for (const c of clientes) {
                    const option = document.createElement('option');
                    option.value = c.id;
                    option.textContent = c.nombre + (c.cedula ? ' (' + c.cedula + ')' : '');
                    clienteSelect.appendChild(option);
                }
            } catch (e) {
                console.error(e);
            }
        }

        const modalNuevoClienteEl = document.getElementById('modalNuevoCliente');
        const modalNuevoCliente = new bootstrap.Modal(modalNuevoClienteEl);
        const formNuevoCliente = document.getElementById('formNuevoCliente');
        const clienteError = document.getElementById('clienteError');

        document.getElementById('btnNuevoCliente').addEventListener('click', () => {
            formNuevoCliente.reset();
            clienteError.classList.add('d-none');
            modalNuevoCliente.show();
        });

        formNuevoCliente.addEventListener('submit', async (e) => {
            e.preventDefault();
            clienteError.classList.add('d-none');
            const btn = document.getElementById('btnGuardarCliente');
            btn.disabled = true;
            btn.textContent = 'Guardando...';

            try {
                const formData = new FormData(formNuevoCliente);
                formData.append('action', 'create');

                const res = await fetch(apiClientesUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': csrfToken
                    },
                    credentials: 'same-origin'
                });
                const json = await res.json();
                if (!json.ok) throw new Error(json.message || 'Error guardando cliente');

                await loadClientes();
                clienteSelect.value = json.id; // Seleccionar el nuevo cliente
                modalNuevoCliente.hide();

                Swal.fire({
                    icon: 'success',
                    title: 'Cliente creado',
                    text: json.message,
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    showConfirmButton: false
                });
            } catch (err) {
                clienteError.textContent = err.message;
                clienteError.classList.remove('d-none');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Guardar Cliente';
            }
        });

        async function loadProductos(page = 1, search = '') {
            productosBody.innerHTML = `<tr><td colspan="4" class="text-muted">Cargando...</td></tr>`;
            try {
                const url = apiProductosUrl + '?action=list_for_pos&page=' + page + '&per_page=10&search=' + encodeURIComponent(search);
                const res = await fetch(url, { credentials: 'same-origin' });
                const json = await res.json();
                if (!json.ok) throw new Error(json.message || 'Error cargando productos');

                const rows = json.data || [];
                const pagination = json.pagination || {};

                productosBody.innerHTML = '';
                if (!rows.length) {
                    productosBody.innerHTML = `<tr><td colspan="4" class="text-muted">Sin productos.</td></tr>`;
                    return;
                }

                for (const p of rows) {
                    const stockLow = Number(p.stock_actual) <= 5;
                    const stockClass = stockLow ? 'stock-low' : 'text-code-stock';
                    const imgHtml = p.imagen ? `<img src="${baseUrl}${p.imagen}" alt="${escapeHtml(p.nombre)}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; margin-right: 8px;">` : '';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                    <td>
                        <div class="d-flex align-items-center">
                            ${imgHtml}
                            <div>
                                <div class="text-heading" style="font-weight:700">${escapeHtml(p.nombre)}</div>
                                <div class="${stockClass}" style="font-size:.82rem">${escapeHtml(p.codigo)} · stock: <strong>${escapeHtml(String(p.stock_actual))}</strong></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-end" style="font-size:.85rem">${money(p.precio_compra1)}</td>
                    <td class="text-end text-heading" style="font-weight:700">${money(p.precio_venta)}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary add-to-cart">Agregar</button>
                    </td>
                `;
                    tr.querySelector('.add-to-cart').addEventListener('click', () => {
                        const st = Number(p.stock_actual);
                        if (st <= 0) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Sin Stock',
                                text: `El producto "${p.nombre}" no tiene unidades disponibles.`,
                                toast: true,
                                position: 'top-end',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: false
                            });
                            return;
                        }

                        const existing = carrito.find(x => x.producto_id == p.id);
                        if (existing) {
                            if (existing.cantidad >= st) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Límite alcanzado',
                                    text: 'No hay más unidades en stock.',
                                    toast: true,
                                    position: 'top-end',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                return;
                            }
                            existing.cantidad += 1;
                        } else {
                            carrito.push({
                                producto_id: Number(p.id),
                                codigo: p.codigo,
                                nombre: p.nombre,
                                imagen: p.imagen || null,
                                precio_compra1: Number(p.precio_compra1 || 0),
                                precio_compra2: Number(p.precio_compra2 || 0),
                                precio_venta: Number(p.precio_venta || 0),
                                precio_usado: Number(p.precio_venta || 0),
                                precio_tipo: 'v',
                                precio_compra_aplicado: Number(p.precio_compra1 || 0),
                                stock_actual: Number(p.stock_actual),
                                cantidad: 1
                            });
                        }
                        renderCarrito();
                    });
                    productosBody.appendChild(tr);
                }

                currentPage = pagination.page || 1;
                totalPages = pagination.total_pages || 1;
                paginationInfo.textContent = `Página ${currentPage} de ${totalPages}`;
                btnPrev.disabled = currentPage <= 1;
                btnNext.disabled = currentPage >= totalPages;
            } catch (e) {
                productosBody.innerHTML = `<tr><td colspan="4" class="text-muted">Error cargando productos.</td></tr>`;
                console.error(e);
            }
        }

        function escapeHtml(str) {
            return String(str ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", "&#039;");
        }

        function debounce(fn, delay = 250) {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        }

        async function buscar(showNoTerm = false) {
            clearError();
            const term = q.value.trim();
            currentSearch = term;

            if (!term) {
                resultados.innerHTML = showNoTerm ? `<div class="alert alert-info p-2 mb-0">Escribe código o nombre para buscar.</div>` : '';
                await loadProductos(1, '');
                return;
            }

            resultados.innerHTML = '';
            await loadProductos(1, term);
        }

        document.getElementById('btnBuscar').addEventListener('click', () => buscar(true));
        q.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                buscar(true);
            }
        });
        q.addEventListener('input', debounce(() => buscar(false), 300));

        btnPrev.addEventListener('click', () => {
            if (currentPage > 1) {
                loadProductos(currentPage - 1, currentSearch);
            }
        });

        btnNext.addEventListener('click', () => {
            if (currentPage < totalPages) {
                loadProductos(currentPage + 1, currentSearch);
            }
        });

        document.getElementById('btnLimpiar').addEventListener('click', () => {
            clearError();
            carrito = [];
            resultados.innerHTML = '';
            q.value = '';
            clienteSelect.value = '';
            renderCarrito();
            q.focus();
        });

        document.getElementById('btnCobrar').addEventListener('click', () => {
            clearError();
            if (!carrito.length) {
                showError('Carrito vacío.');
                return;
            }

            const subtotal = carrito.reduce((sum, it) => sum + (Number(it.precio_usado) * Number(it.cantidad)), 0);
            const d = Number(descuentoGlobal || 0);
            const total = Math.max(0, subtotal - d);
            const clienteId = clienteSelect.value || null;

            showPaymentModal(total, clienteId);
        });

        document.getElementById('btnCotizar').addEventListener('click', () => {
            clearError();
            if (!carrito.length) {
                showError('Carrito vacío.');
                return;
            }

            const subtotal = carrito.reduce((sum, it) => sum + (Number(it.precio_usado) * Number(it.cantidad)), 0);
            const d = Number(descuentoGlobal || 0);
            const total = Math.max(0, subtotal - d);
            const clienteId = clienteSelect.value || null;

            const modalId = 'modalCotizar' + Date.now();
            const html = `
            <div class="modal fade modal-themed" id="${modalId}" data-bs-backdrop="static" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content shadow-lg border-0">
                        <div class="modal-header bg-secondary text-white py-3">
                            <h5 class="modal-title fw-bold"><i class="bi bi-file-text me-2"></i>Crear Cotización</h5>
                        </div>
                        <div class="modal-body p-4">
                            <div class="text-center mb-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Total Cotizado</div>
                                <div class="display-5 fw-bold text-secondary">${moneda} ${money(total)}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Notas</label>
                                <textarea class="form-control cotizacion-notas" rows="3" placeholder="Notas opcionales para la cotización..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Días de validez</label>
                                <input type="number" class="form-control cotizacion-validez" value="7" min="1" max="90">
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <div class="d-grid w-100 gap-2">
                                <button class="btn btn-secondary btn-lg fw-bold py-3 btn-confirmar-cotizar">
                                    <i class="bi bi-check2-circle me-2"></i> Generar Cotización
                                </button>
                                <button class="btn btn-danger btn-lg fw-bold py-3 btn-cancelar-cotizar">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;
            document.body.insertAdjacentHTML('beforeend', html);
            const modalEl = document.getElementById(modalId);
            const modal = new bootstrap.Modal(modalEl);

            const btnConfirmar = modalEl.querySelector('.btn-confirmar-cotizar');
            const btnCancelar = modalEl.querySelector('.btn-cancelar-cotizar');

            btnConfirmar.onclick = async () => {
                const notas = modalEl.querySelector('.cotizacion-notas').value.trim();
                const diasValidez = parseInt(modalEl.querySelector('.cotizacion-validez').value) || 7;

                btnConfirmar.disabled = true;
                btnConfirmar.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Generando...';

                try {
                    const cotizacionItems = carrito.map(it => ({
                        producto_id: it.producto_id,
                        codigo: it.codigo,
                        nombre: it.nombre,
                        cantidad: it.cantidad,
                        precio_unitario: it.precio_usado
                    }));

                    const res = await fetch(apiUrl + '?action=create_cotizacion', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            items: cotizacionItems,
                            cliente_id: clienteId,
                            descuento: d,
                            notas: notas,
                            dias_validez: diasValidez
                        })
                    });
                    const json = await res.json();
                    if (!json.ok) throw new Error(json.message || 'Error creando cotización');

                    modal.hide();
                    setTimeout(() => modalEl.remove(), 300);

                    carrito = [];
                    renderCarrito();
                    resultados.innerHTML = '';
                    q.value = '';
                    clienteSelect.value = '';
                    q.focus();

                    Swal.fire({
                        icon: 'success',
                        title: 'Cotización Creada',
                        text: `Cotización #${json.codigo} generada con éxito.`,
                        showCancelButton: true,
                        confirmButtonText: 'Ver Cotización',
                        cancelButtonText: 'Cerrar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.open('cotizacion_detalle.php?id=' + json.id, '_blank');
                        }
                    });

                } catch (e) {
                    Swal.fire('Error', e.message || 'Error creando cotización', 'error');
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="bi bi-check2-circle me-2"></i> Generar Cotización';
                }
            };

            btnCancelar.onclick = () => {
                modal.hide();
                setTimeout(() => modalEl.remove(), 300);
            };

            modal.show();
        });

        // Descuento en tiempo real
        document.getElementById('discountInput').addEventListener('input', () => {
            const input = document.getElementById('discountInput');
            descuentoGlobal = Math.max(0, Number(input.value) || 0);
            renderCarrito();
        });

        // Limpiar también resetea descuento
        document.getElementById('btnLimpiar').addEventListener('click', () => {
            descuentoGlobal = 0;
            const input = document.getElementById('discountInput');
            if (input) input.value = money(0);
        });

        function showPaymentModal(total, clienteId) {
            const modalId = 'modalPago' + Date.now();
            const html = `
            <div class="modal fade modal-themed" id="${modalId}" data-bs-backdrop="static" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content shadow-lg border-0">
                        <div class="modal-header bg-primary text-white py-3">
                            <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i>Confirmar Pago</h5>
                        </div>
                        <div class="modal-body p-4">
                            <div class="text-center mb-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Total</div>
                                <div class="display-5 fw-bold text-primary">${moneda} ${money(total)}</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Efectivo (${moneda})</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-cash"></i></span>
                                        <input type="number" step="0.01" class="form-control form-control-lg p-efectivo" value="${money(total)}">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Pago QR (${moneda})</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-qr-code"></i></span>
                                        <input type="number" step="0.01" class="form-control form-control-lg p-qr" value="0.00">
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 p-3 rounded-3 bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-bold">Recibido</div>
                                    <div class="h4 mb-0 fw-bold p-recibido">0.00</div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small fw-bold" id="labelCambio">Cambio</div>
                                    <div class="h4 mb-0 fw-bold text-success p-cambio">0.00</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <div class="d-grid w-100 gap-2">
                                <button class="btn btn-primary btn-lg fw-bold py-3 btn-confirmar-pago">
                                    <i class="bi bi-check2-circle me-2"></i> Finalizar Venta
                                </button>
                                <button class="btn btn-danger btn-lg fw-bold py-3 btn-cancelar">
                                    Cancelar Venta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;
            document.body.insertAdjacentHTML('beforeend', html);
            const modalEl = document.getElementById(modalId);
            const modal = new bootstrap.Modal(modalEl);

            const inputEfectivo = modalEl.querySelector('.p-efectivo');
            const inputQr = modalEl.querySelector('.p-qr');
            const elRecibido = modalEl.querySelector('.p-recibido');
            const elCambio = modalEl.querySelector('.p-cambio');
            const labelCambio = modalEl.querySelector('#labelCambio');
            const btnConfirmar = modalEl.querySelector('.btn-confirmar-pago');
            const btnCancelar = modalEl.querySelector('.btn-cancelar');

            function updateCalculos() {
                const ef = parseFloat(inputEfectivo.value) || 0;
                const qr = parseFloat(inputQr.value) || 0;
                const recibido = ef + qr;
                const cambio = recibido - total;

                elRecibido.textContent = money(recibido);
                elCambio.textContent = money(Math.abs(cambio));

                if (cambio < 0) {
                    labelCambio.textContent = 'Faltante';
                    elCambio.classList.remove('text-success');
                    elCambio.classList.add('text-danger');
                    btnConfirmar.disabled = true;
                } else {
                    labelCambio.textContent = 'Cambio';
                    elCambio.classList.remove('text-danger');
                    elCambio.classList.add('text-success');
                    btnConfirmar.disabled = false;
                }
            }

            inputEfectivo.addEventListener('input', () => {
                const ef = parseFloat(inputEfectivo.value) || 0;
                if (ef < total) {
                    inputQr.value = (total - ef).toFixed(2);
                } else {
                    inputQr.value = "0.00";
                }
                updateCalculos();
            });
            inputQr.addEventListener('input', () => {
                const qr = parseFloat(inputQr.value) || 0;
                if (qr < total) {
                    inputEfectivo.value = (total - qr).toFixed(2);
                } else {
                    inputEfectivo.value = money(total);
                }
                updateCalculos();
            });

            btnConfirmar.onclick = async () => {
                const ef = parseFloat(inputEfectivo.value) || 0;
                const qr = parseFloat(inputQr.value) || 0;
                let metodo = 'efectivo';
                if (ef > 0 && qr > 0) metodo = 'mixto';
                else if (qr > 0) metodo = 'qr';

                btnConfirmar.disabled = true;
                btnConfirmar.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Procesando...';

                try {
                    // 1. Crear la venta
                    const saleItems = carrito.map(it => ({
                        producto_id: it.producto_id,
                        cantidad: it.cantidad,
                        precio_venta: it.precio_usado,
                        precio_compra: it.precio_compra_aplicado
                    }));

                    const resSale = await fetch(apiUrl + '?action=create_sale', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ items: saleItems, cliente_id: clienteId, descuento: descuentoGlobal })
                    });
                    const jsonSale = await resSale.json();
                    if (!jsonSale.ok) throw new Error(jsonSale.message || 'Error creando venta');

                    // 2. Confirmar el pago
                    const ventaId = jsonSale.venta_id;
                    const formData = new FormData();
                    formData.append('action', 'confirm_payment');
                    formData.append('csrf_token', csrfToken);
                    formData.append('venta_id', ventaId);
                    formData.append('efectivo', ef - (parseFloat(elCambio.textContent) || 0));
                    formData.append('qr', qr);
                    formData.append('metodo_pago', metodo);
                    formData.append('estado_pago', 'cobrado');

                    const resPay = await fetch(apiUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    });
                    const jsonPay = await resPay.json();
                    if (!jsonPay.ok) throw new Error(jsonPay.message || 'Error confirmando pago');

                    // Éxito
                    modal.hide();
                    setTimeout(() => modalEl.remove(), 300);

                    carrito = [];
                    renderCarrito();
                    resultados.innerHTML = '';
                    q.value = '';
                    clienteSelect.value = '';
                    q.focus();

                    Swal.fire({
                        icon: 'success',
                        title: 'Venta Finalizada',
                        text: `La venta #${ventaId} ha sido registrada con éxito.`,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });

                } catch (e) {
                    Swal.fire('Error', e.message || 'Error procesando la venta', 'error');
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="bi bi-check2-circle me-2"></i> Finalizar Venta';
                }
            };

            btnCancelar.onclick = async () => {
                try {
                    modal.hide();
                    setTimeout(() => modalEl.remove(), 300);

                    Swal.fire({
                        icon: 'error',
                        title: 'Venta Cancelada',
                        text: `La venta se cancelo.`,
                        timer: 2000,
                        showConfirmButton: false
                    })

                } catch (e) {
                    Swal.fire('Error', e.message || 'Error', 'error');
                }
            };

            modal.show();
            updateCalculos();
            setTimeout(() => inputEfectivo.select(), 500);
        }

        renderCarrito();
        loadProductos();
        loadClientes();
        q.focus();
    })();
</script>