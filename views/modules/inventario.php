<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess();

$pageTitle = 'Inventario - AbdiSoft CORE';
$active = 'inventario';
$baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : '/Filacell/';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css',
    'https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css'
];
$extraJs = [
    'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js',
    'https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js',
    'https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js',
    'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js',
];
require_once __DIR__ . '/../layout/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="content">
        <div class="topbar">
            <div>
                <h1 class="h4 title-strong mb-1">Inventario</h1>
                <div class="text-muted" style="font-size:.92rem">Gestión centralizada de productos y stock.</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if (Auth::isAdmin()): ?>
                <button class="btn btn-primary" type="button" id="btnNuevo"><i class="bi bi-plus-lg"></i> Nuevo</button>
                
                <div class="dropdown">
                    <button class="btn btn-theme-outline dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-file-earmark-arrow-up"></i> Importar
                    </button>
                    <div class="dropdown-menu p-3" style="width: 250px;">
                        <form id="importarExcelForm" enctype="multipart/form-data">
                            <label class="form-label small fw-bold">Plantilla Excel/CSV</label>
                            <input type="file" name="excel_file" class="form-control form-control-sm mb-2" accept=".xlsx, .xls, .csv" required>
                            <button type="submit" class="btn btn-success btn-sm w-100">Cargar Datos</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <div class="btn-group">
                    <a href="<?= $baseUrl ?>views/modules/inventario_reporte.php" target="_blank" class="btn btn-theme-outline text-danger">
                        <i class="bi bi-file-pdf"></i> Stock
                    </a>
                    <a href="<?= $baseUrl ?>views/modules/inventario_cliente_pdf.php" target="_blank" class="btn btn-theme-outline text-info">
                        <i class="bi bi-person-lines-fill"></i> Clientes
                    </a>
                </div>
            </div>
        </div>

        <div id="loading" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1050; background: rgba(19,36,61,0.9); color: white; padding: 24px; border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow-lg);">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <div class="fw-bold">Procesando información...</div>
        </div>

        <div class="card p-3">
            <div class="table-responsive">
                <table id="tablaProductos" class="table table-hover align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imagen</th>
                        <th>Producto</th>
                        <th class="text-end">Costo</th>
                        <th class="text-end">Venta</th>
                        <th class="text-end">Stock</th>
                        <th class="text-end">Mín</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Producto -->
<div class="modal fade modal-themed" id="modalProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Detalles del Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formProducto">
                <div class="modal-body">
                    <input type="hidden" name="id" id="p_id" value="0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label form-label-compact">Nombre del Producto *</label>
                            <input class="form-control themed-input" name="nombre" id="p_nombre" required placeholder="Ej: Protector de Pantalla">
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label form-label-compact">P. Compra</label>
                            <input type="number" step="0.01" class="form-control themed-input" name="precio_compra1" id="p_compra1" value="0">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label form-label-compact">P. Venta</label>
                            <input type="number" step="0.01" class="form-control themed-input" name="precio_venta" id="p_venta" value="0">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label form-label-compact">Stock Actual</label>
                            <input type="number" step="0.001" class="form-control themed-input" name="stock_actual" id="p_stock" value="0">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label form-label-compact">Stock Mínimo</label>
                            <input type="number" step="0.001" class="form-control themed-input" name="stock_minimo" id="p_min" value="0">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label form-label-compact">Estado</label>
                            <select class="form-select themed-input" name="estado" id="p_estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label form-label-compact">Imagen del Producto</label>
                            <input type="file" name="imagen" id="p_imagen" class="form-control themed-input" accept="image/*" capture="environment">
                            <div class="form-text">Formatos: JPG, PNG, GIF, WebP. Se convertirá automáticamente a WebP. En móvil permite tomar foto.</div>
                            <div id="preview_imagen" class="mt-2" style="display:none;">
                                <img src="" alt="Preview" style="max-height: 100px; border-radius: 8px;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                        <button type="button" class="btn btn-theme-outline" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ver Imagen -->
<div class="modal fade" id="modalVerImagen" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Imagen del Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imgProductoGrande" src="" alt="Imagen del producto" style="max-width: 100%; border-radius: 8px;">
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script>
    (() => {
        const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES); ?>;
        const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;
        const apiUrl = baseUrl + 'views/modules/productos_api.php';

        const modalEl = document.getElementById('modalProducto');
        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('formProducto');

        function fillForm(data) {
            document.getElementById('p_id').value = data?.id ?? 0;
            document.getElementById('p_nombre').value = data?.nombre ?? '';
            document.getElementById('p_compra1').value = data?.precio_compra1 ?? 0;
            document.getElementById('p_venta').value = data?.precio_venta ?? 0;
            document.getElementById('p_stock').value = data?.stock_actual ?? 0;
            document.getElementById('p_min').value = data?.stock_minimo ?? 5;
            document.getElementById('p_estado').value = (data?.estado ?? 1).toString();
            document.getElementById('p_imagen').value = '';
            
            const preview = document.getElementById('preview_imagen');
            if (data?.imagen) {
                preview.style.display = 'block';
                preview.querySelector('img').src = baseUrl + data.imagen;
            } else {
                preview.style.display = 'none';
            }
        }

        const dt = $('#tablaProductos').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            pageLength: 25,
            ajax: {
                url: apiUrl,
                type: 'GET',
                data: (d) => { d.action = 'list'; }
            },
            order: [[0, 'desc']],
            columns: [
                { data: 'id', className: 'text-muted small' },
                { 
                    data: 'imagen',
                    orderable: false,
                    render: (imagen, type, row) => {
                        if (imagen) {
                            return `<img src="${baseUrl}${imagen}" alt="${row.nombre}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; cursor: pointer;" onclick="verImagen('${baseUrl}${imagen}')">`;
                        }
                        return '<span class="text-muted">Sin imagen</span>';
                    }
                },
                { data: 'nombre', className: 'fw-bold text-heading' },
                { data: 'precio_compra1', className: 'text-end', render: (v) => Number(v || 0).toFixed(2) },
                { data: 'precio_venta', className: 'text-end', render: (v) => Number(v || 0).toFixed(2) },
                { data: 'stock_actual', className: 'text-end fw-bold' },
                { data: 'stock_minimo', className: 'text-end text-muted' },
                { 
                    data: 'estado', 
                    className: 'text-center',
                    render: (v) => v == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' 
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-end',
                    visible: <?php echo Auth::isAdmin() ? 'true' : 'false'; ?>,
                    render: (row) => `
                        <button class="btn btn-sm btn-theme-outline btn-edit" data-id="${row.id}">
                            <i class="bi bi-pencil"></i>
                        </button>`
                }
            ],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
        });

        document.getElementById('btnNuevo').addEventListener('click', () => {
            document.getElementById('modalTitle').textContent = 'Nuevo Producto';
            fillForm(null);
            modal.show();
        });

        $('#tablaProductos').on('click', '.btn-edit', async function () {
            const id = this.getAttribute('data-id');
            document.getElementById('modalTitle').textContent = 'Editar Producto #' + id;
            try {
                const res = await fetch(apiUrl + '?action=get&id=' + encodeURIComponent(id));
                const json = await res.json();
                if (!json.ok) throw new Error(json.message);
                fillForm(json.data);
                modal.show();
            } catch (e) { alert(e.message); }
        });

        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const fd = new FormData(form);
            fd.append('action', 'save');
            const btn = document.getElementById('btnGuardar');
            btn.disabled = true;
            btn.textContent = 'Guardando...';

            try {
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-CSRF-Token': csrfToken }
                });
                const json = await res.json();
                if (!json.ok) throw new Error(json.message);
                modal.hide();
                dt.ajax.reload(null, false);
            } catch (e) { alert(e.message); }
            finally {
                btn.disabled = false;
                btn.textContent = 'Guardar Cambios';
            }
        });

        // Preview de imagen
        document.getElementById('p_imagen').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('preview_imagen');
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.style.display = 'block';
                    preview.querySelector('img').src = e.target.result;
                }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });

        // Ver imagen grande
        window.verImagen = function(url) {
            document.getElementById('imgProductoGrande').src = url;
            new bootstrap.Modal(document.getElementById('modalVerImagen')).show();
        }

        document.getElementById('importarExcelForm').onsubmit = async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            btn.disabled = true;
            btn.textContent = 'Cargando...';
            try {
                const res = await fetch('procesar_excel.php', { method: 'POST', body: new FormData(e.target) });
                const json = await res.json();
                alert(json.message);
                if (json.status === 'success') location.reload();
            } catch (error) { alert('Error en el servidor'); }
            finally { btn.disabled = false; btn.textContent = 'Cargar Datos'; }
        };
    })();
</script>