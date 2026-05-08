<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess('admin');

$pageTitle = 'Categorías - AbdiSoft CORE';
$active = 'categorias';
$baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : '/Filacell/';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

require_once __DIR__ . '/../layout/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="content">
        <div class="topbar">
            <div>
                <h1 class="h4 title-strong mb-1">Gestión de Categorías</h1>
                <div class="text-muted" style="font-size:.92rem">Crear, editar y gestionar categorías de productos.
                </div>
            </div>
            <button class="btn btn-primary" id="btnNuevo" type="button">+ Nueva Categoría</button>
        </div>

        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="categoriasTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Fecha Registro</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Nueva/Editar Categoría -->
<div class="modal fade modal-themed" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCategoria">
                <div class="modal-body">
                    <input type="hidden" id="categoriaId" value="">

                    <div class="mb-3">
                        <label class="form-label form-label-compact">Nombre *</label>
                        <input type="text" class="form-control themed-input" id="nombre" placeholder="Ej: Accesorios"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label form-label-compact">Descripción</label>
                        <textarea class="form-control themed-input" id="descripcion" rows="3"
                            placeholder="Descripción opcional de la categoría"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-theme-outline" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Categoría</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    (() => {
        const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES); ?>;
        const apiUrl = baseUrl + 'views/modules/categorias_api.php';
        const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        let table;

        function initTable() {
            table = new DataTable('#categoriasTable', {
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: apiUrl + '?action=list_datatables',
                    type: 'GET',
                },
                columns: [
                    { data: 'nombre', className: 'fw-bold text-heading' },
                    { data: 'descripcion', render: (v) => v || '—' },
                    { data: 'created_at', render: (v) => v ? new Date(v).toLocaleDateString() : '—' },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-end',
                        render: (data, type, row) => {
                            return `
                            <div class="d-flex gap-2 justify-content-end">
                                <button class="btn btn-sm btn-theme-outline edit-btn" data-id="${row.id}"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-theme-outline text-danger delete-btn" data-id="${row.id}"><i class="bi bi-trash"></i></button>
                            </div>
                        `;
                        }
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                },
                pageLength: 10
            });
        }

        function resetForm() {
            document.getElementById('categoriaId').value = '';
            document.getElementById('formCategoria').reset();
            document.getElementById('modalTitle').textContent = 'Nueva Categoría';
        }

        async function submitForm(e) {
            e.preventDefault();
            const categoriaId = document.getElementById('categoriaId').value;
            const nombre = document.getElementById('nombre').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();

            if (!nombre) {
                Toast.fire({ icon: 'warning', title: 'El nombre es obligatorio.' });
                return;
            }

            const action = categoriaId ? 'update' : 'create';
            const formData = new FormData();
            formData.append('action', action);
            formData.append('csrf_token', csrfToken);
            formData.append('nombre', nombre);
            formData.append('descripcion', descripcion);
            if (categoriaId) formData.append('id', categoriaId);

            const btn = document.querySelector('#formCategoria button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Guardando...';

            try {
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                const json = await res.json();
                if (!json.ok) throw new Error(json.message || 'Error');

                bootstrap.Modal.getInstance(document.getElementById('modalCategoria')).hide();
                table.ajax.reload(null, false);
                resetForm();
                Toast.fire({
                    icon: 'success',
                    title: categoriaId ? 'Categoría actualizada correctamente.' : 'Categoría creada correctamente.'
                });
            } catch (e) {
                Toast.fire({ icon: 'error', title: e.message || 'Error al guardar.' });
            } finally {
                btn.disabled = false;
                btn.textContent = 'Guardar Categoría';
            }
        }

        async function editCategoria(id) {
            try {
                const res = await fetch(apiUrl + '?action=get&id=' + id);
                const json = await res.json();
                if (!json.ok) throw new Error(json.message || 'Error');

                const categoria = json.data;
                document.getElementById('categoriaId').value = categoria.id;
                document.getElementById('nombre').value = categoria.nombre;
                document.getElementById('descripcion').value = categoria.descripcion || '';
                document.getElementById('modalTitle').textContent = 'Editar Categoría';

                new bootstrap.Modal(document.getElementById('modalCategoria')).show();
            } catch (e) {
                Toast.fire({ icon: 'error', title: e.message || 'Error al cargar categoría.' });
            }
        }

        async function deleteCategoria(id) {
            const result = await Swal.fire({
                title: '¿Eliminar categoría?',
                text: 'Esta acción no se puede deshacer. Los productos quedarán sin categoría.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('csrf_token', csrfToken);
            formData.append('id', id);

            try {
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                const json = await res.json();
                if (!json.ok) throw new Error(json.message || 'Error');
                table.ajax.reload(null, false);
                Toast.fire({ icon: 'success', title: 'Categoría eliminada correctamente.' });
            } catch (e) {
                Toast.fire({ icon: 'error', title: e.message || 'Error al eliminar.' });
            }
        }

        if (typeof jQuery !== 'undefined') initTable();
        else document.addEventListener('DOMContentLoaded', initTable);

        document.getElementById('btnNuevo').addEventListener('click', () => {
            resetForm();
            new bootstrap.Modal(document.getElementById('modalCategoria')).show();
        });

        document.getElementById('formCategoria').addEventListener('submit', submitForm);

        document.addEventListener('click', (e) => {
            const btnEdit = e.target.closest('.edit-btn');
            const btnDelete = e.target.closest('.delete-btn');
            if (btnEdit) editCategoria(btnEdit.getAttribute('data-id'));
            if (btnDelete) deleteCategoria(btnDelete.getAttribute('data-id'));
        });
    })();
</script>