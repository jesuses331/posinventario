<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess('admin');
require_once __DIR__ . '/../../config/verificar_caja.php';

$pageTitle = 'Nueva Compra - AbdiSoft CORE';
$active = 'compras';
$baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : '/';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

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
        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-4">
                        <div class="row g-3 align-items-end mb-4">
                            <div class="col-12 col-md-7">
                                <label class="form-label fw-bold small text-uppercase text-muted">Buscar Producto
                                    Existente</label>
                                <div class="input-group input-group-lg shadow-sm rounded-3">
                                    <span class="input-group-text bg-white border-end-0"><i
                                            class="bi bi-search text-primary"></i></span>
                                    <input type="text" class="form-control border-start-0" id="searchProd"
                                        placeholder="Nombre o código del producto..." style="font-size: 1rem;">
                                </div>
                                <div id="searchSuggestions" class="list-group position-absolute mt-1 shadow-lg d-none"
                                    style="z-index: 1050; width: 50%;"></div>
                            </div>
                            <div class="col-12 col-md-5">
                                <button class="btn btn-outline-primary w-100 btn-lg rounded-3 border-2" id="btnNuevoMod"
                                    type="button" style="height: 48px;">
                                    <i class="bi bi-plus-lg me-2"></i> Nuevo Producto
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tablaCompra">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3">Producto</th>
                                        <th class="text-center py-3">P. Compra 1</th>
                                        <th class="text-center py-3">P. Venta</th>
                                        <th class="text-center py-3">Cantidad</th>
                                        <th class="text-end py-3">Subtotal</th>
                                        <th class="py-3"></th>
                                    </tr>
                                </thead>
                                <tbody id="listaCompra" class="border-top-0">
                                    <tr>
                                        <td colspan="6" class="text-muted text-center p-5">No hay productos en la lista
                                            de compra.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4">Resumen de Compra</h5>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total de productos:</span>
                            <span class="fw-bold" id="itemCount">0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-4">
                            <span class="text-muted">Fecha de registro:</span>
                            <span class="fw-bold"><?= date('d/m/Y') ?></span>
                        </div>

                        <hr class="my-4">

                        <div class="text-center mb-4">
                            <div class="small text-uppercase text-muted mb-1">Inversión Total</div>
                            <div class="display-5 fw-bold text-primary"><?= $moneda ?? 'Bs' ?> <span
                                    id="totalCompra">0.00</span></div>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg py-3 rounded-3 shadow" id="btnProcesar" type="button">
                                <i class="bi bi-check-circle me-2"></i> Procesar Ingreso
                            </button>
                            <button class="btn btn-link text-muted mt-2" id="btnLimpiar" type="button">Vaciar
                                lista</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
    }

    .app-shell {
        background-color: #f8fafc;
        min-height: 100vh;
    }

    .content {
        padding: 2rem;
    }

    .card {
        border-radius: 1rem;
        overflow: hidden;
    }

    .title-strong {
        font-weight: 800;
        color: #1e293b;
    }

    .table thead th {
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #64748b;
        background-color: #f1f5f9;
        border-bottom: 0;
    }

    .qty-input,
    .price-input {
        max-width: 90px;
        margin: 0 auto;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.4rem;
        text-align: center;
        font-weight: 600;
        transition: all 0.2s;
    }

    .qty-input:focus,
    .price-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    .btn-primary {
        background: var(--primary-gradient);
        border: none;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #4338ca 0%, #2563eb 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .list-group-item-action {
        padding: 1rem;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s;
    }

    .list-group-item-action:hover {
        background-color: #f8fafc;
        padding-left: 1.25rem;
    }

    .badge-existent {
        background-color: #ecfdf5;
        color: #059669;
        border: 1px solid #d1fae5;
    }

    .badge-new {
        background-color: #eff6ff;
        color: #2563eb;
        border: 1px solid #dbeafe;
    }
</style>

<!-- Modal Producto Nuevo -->
<div class="modal fade modal-themed" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> Nuevo Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">Nombre del Producto</label>
                    <input type="text" class="form-control form-control-lg bg-light" id="n_nombre" required
                        placeholder="Ej: producto 1 ">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted">Precio Compra</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">$</span>
                            <input type="number" step="0.01"
                                class="form-control form-control-lg bg-light border-start-0" id="n_p1" value="0.00">
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted">Precio Venta</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">$</span>
                            <input type="number" step="0.01"
                                class="form-control form-control-lg bg-light border-start-0" id="n_venta" value="0.00">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">Cantidad Inicial</label>
                    <input type="number" step="0.01" class="form-control form-control-lg bg-light" id="n_cantidad"
                        value="1">
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light btn-lg px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-lg px-4 shadow-sm" id="btnAgregarNuevo">Agregar a
                    Lista</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script>
    (() => {
        document.addEventListener('DOMContentLoaded', () => {
            const baseUrl = '<?= $baseUrl ?>';
            const apiUrl = baseUrl + 'views/modules/compras_api.php';
            const csrfToken = '<?= $csrfToken ?>';

            let comprasItems = []; // {producto_id: -1|id, nombre, precio_compra1, precio_compra2, precio_venta, cantidad}

            const searchProd = document.getElementById('searchProd');
            const searchSuggestions = document.getElementById('searchSuggestions');
            const listaCompra = document.getElementById('listaCompra');
            const totalCompraEl = document.getElementById('totalCompra');

            function money(n) { return Number(n || 0).toFixed(2); }

            function render() {
                if (comprasItems.length === 0) {
                    listaCompra.innerHTML = '<tr><td colspan="6" class="text-muted text-center p-5">No hay productos en la lista de compra.</td></tr>';
                    totalCompraEl.textContent = '0.00';
                    document.getElementById('itemCount').textContent = '0';
                    return;
                }

                listaCompra.innerHTML = '';
                let total = 0;
                comprasItems.forEach((it, idx) => {
                    const sub = it.precio_compra1 * it.cantidad;
                    total += sub;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                    <td>
                        <div class="fw-bold text-heading">${it.nombre}</div>
                        ${it.producto_id > 0
                            ? '<span class="badge badge-existent rounded-pill small" style="font-size:0.65rem">EXISTENTE</span>'
                            : '<span class="badge badge-new rounded-pill small" style="font-size:0.65rem">NUEVO PRODUCTO</span>'}
                    </td>
                    <td class="text-center"><input type="number" step="0.01" class="form-control form-control-sm price-input" value="${it.precio_compra1}" data-field="precio_compra1" data-idx="${idx}"></td>
                    <td class="text-center"><input type="number" step="0.01" class="form-control form-control-sm price-input" value="${it.precio_venta}" data-field="precio_venta" data-idx="${idx}"></td>
                    <td class="text-center"><input type="number" step="0.1" class="form-control form-control-sm qty-input" value="${it.cantidad}" data-field="cantidad" data-idx="${idx}"></td>
                    <td class="text-end fw-bold text-primary" style="font-size:1.1rem">${money(sub)}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-link text-danger btn-del" data-idx="${idx}">
                            <i class="bi bi-trash3-fill" style="font-size: 1.1rem;"></i>
                        </button>
                    </td>
                `;
                    listaCompra.appendChild(tr);
                });

                totalCompraEl.textContent = money(total);
                document.getElementById('itemCount').textContent = comprasItems.length;

                // Events
                listaCompra.querySelectorAll('input').forEach(input => {
                    input.addEventListener('change', (e) => {
                        const idx = e.target.dataset.idx;
                        const field = e.target.dataset.field;
                        comprasItems[idx][field] = parseFloat(e.target.value) || 0;
                        render();
                    });
                });
                listaCompra.querySelectorAll('.btn-del').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const idx = e.currentTarget.dataset.idx;
                        comprasItems.splice(idx, 1);
                        render();
                    });
                });
            }

            // Search existing
            searchProd.addEventListener('input', debounce(async (e) => {
                const val = e.target.value.trim();
                if (val.length < 2) {
                    searchSuggestions.classList.add('d-none');
                    return;
                }

                try {
                    const res = await fetch(apiUrl + '?action=search_products&q=' + encodeURIComponent(val));
                    const json = await res.json();
                    if (json.ok && json.data.length > 0) {
                        renderSuggestions(json.data);
                    } else {
                        searchSuggestions.classList.add('d-none');
                    }
                } catch (err) { console.error(err); }
            }, 300));

            function renderSuggestions(data) {
                searchSuggestions.innerHTML = '';
                data.forEach(p => {
                    const btn = document.createElement('button');
                    btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                    btn.innerHTML = `<div><strong>${p.nombre}</strong><br><small class="text-muted">Stock: ${p.stock_actual}</small></div> <span class="badge bg-primary rounded-pill">P1: ${money(p.precio_compra1)}</span>`;
                    btn.addEventListener('click', () => {
                        addToPurchase({
                            producto_id: p.id,
                            nombre: p.nombre,
                            precio_compra1: p.precio_compra1,
                            precio_compra2: p.precio_compra2,
                            precio_venta: p.precio_venta,
                            cantidad: 1
                        });
                        searchProd.value = '';
                        searchSuggestions.classList.add('d-none');
                    });
                    searchSuggestions.appendChild(btn);
                });
                searchSuggestions.classList.remove('d-none');
            }

            function addToPurchase(item) {
                const existing = comprasItems.find(it => it.producto_id === item.producto_id && it.producto_id > 0);
                if (existing) {
                    existing.cantidad += item.cantidad;
                } else {
                    comprasItems.push(item);
                }
                render();
            }

            // Add New
            const modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo'));
            document.getElementById('btnNuevoMod').addEventListener('click', () => modalNuevo.show());
            document.getElementById('btnAgregarNuevo').addEventListener('click', () => {
                const nombre = document.getElementById('n_nombre').value.trim();
                if (!nombre) return;
                addToPurchase({
                    producto_id: -1,
                    nombre: nombre,
                    precio_compra1: parseFloat(document.getElementById('n_p1').value) || 0,
                    precio_compra2: 0,
                    precio_venta: parseFloat(document.getElementById('n_venta').value) || 0,
                    cantidad: parseFloat(document.getElementById('n_cantidad').value) || 0
                });
                modalNuevo.hide();
                document.getElementById('n_nombre').value = '';
            });

            // Save
            document.getElementById('btnProcesar').addEventListener('click', async () => {
                if (comprasItems.length === 0) return;

                const result = await Swal.fire({
                    title: '¿Confirmar Compra?',
                    text: "Se actualizará el stock y precios de " + comprasItems.length + " productos.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, guardar compra'
                });

                if (result.isConfirmed) {
                    const btn = document.getElementById('btnProcesar');
                    btn.disabled = true;
                    btn.textContent = 'Procesando...';

                    try {
                        const total = parseFloat(totalCompraEl.textContent);
                        const res = await fetch(apiUrl + '?action=save_purchase', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                            body: JSON.stringify({ items: comprasItems, total: total })
                        });
                        const json = await res.json();

                        if (json.ok) {
                            await Swal.fire('Éxito', json.message, 'success');
                            comprasItems = [];
                            render();
                        } else {
                            throw new Error(json.message);
                        }
                    } catch (err) {
                        Swal.fire('Error', err.message, 'error');
                    } finally {
                        btn.disabled = false;
                        btn.textContent = 'Procesar Compra e Ingresar';
                    }
                }
            });

            document.getElementById('btnLimpiar').addEventListener('click', () => {
                comprasItems = [];
                render();
            });

            function debounce(func, wait) {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }

        });
    })();
</script>