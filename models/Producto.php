<?php

class Producto {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function countAll(string $search = ''): int {
        if ($search === '') {
            $stmt = $this->db->query("SELECT COUNT(*) AS c FROM productos");
            return (int)($stmt->fetch()['c'] ?? 0);
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS c
            FROM productos
            WHERE LOWER(COALESCE(codigo, '')) LIKE :q1 OR LOWER(COALESCE(nombre, '')) LIKE :q2 OR LOWER(COALESCE(lote, '')) LIKE :q3 OR LOWER(COALESCE(imagen, '')) LIKE :q4
        ");
        $q = '%' . mb_strtolower($search, 'UTF-8') . '%';
        $stmt->execute([':q1' => $q, ':q2' => $q, ':q3' => $q, ':q4' => $q]);
        return (int)($stmt->fetch()['c'] ?? 0);
    }

    public function listDataTables(int $start, int $length, string $search, string $orderBy, string $orderDir): array {
        $allowedOrder = [
            'id' => 'id',
            'codigo' => 'codigo',
            'nombre' => 'nombre',
            'precio_compra1' => 'precio_compra1',
            'precio_compra2' => 'precio_compra2',
            'precio_venta' => 'precio_venta',
            'stock_actual' => 'stock_actual',
            'stock_minimo' => 'stock_minimo',
            'fecha_vencimiento' => 'fecha_vencimiento',
            'estado' => 'estado',
        ];
        $orderCol = $allowedOrder[$orderBy] ?? 'id';
        $dir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $start = max(0, $start);
        $length = max(10, min(200, $length));

        $params = [];
        $where = '';
        if ($search !== '') {
            $where = "WHERE p.codigo LIKE :q1 OR p.nombre LIKE :q2 OR p.lote LIKE :q3";
            $q = '%' . $search . '%';
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
        }

        $sql = "
            SELECT p.id, p.codigo, p.nombre, p.precio_compra1, p.precio_compra2, p.precio_venta, p.stock_actual, p.stock_minimo,
                   p.factor_conversion, p.fecha_vencimiento, p.lote, p.estado, p.imagen, p.categoria_id,
                   c.nombre AS categoria_nombre
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            $where
            ORDER BY $orderCol $dir
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function listSimple(int $offset, int $perPage, string $search, string $orderBy, string $orderDir): array {
        $allowedOrder = [
            'id' => 'id',
            'codigo' => 'codigo',
            'nombre' => 'nombre',
            'precio_venta' => 'precio_venta',
            'stock_actual' => 'stock_actual',
        ];
        $orderCol = $allowedOrder[$orderBy] ?? 'nombre';
        $dir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $offset = max(0, $offset);
        $perPage = max(1, min(100, $perPage));

        $params = [];
        $where = '';
        if ($search !== '') {
            $where = "WHERE (LOWER(COALESCE(p.codigo, '')) LIKE :q1 OR LOWER(COALESCE(p.nombre, '')) LIKE :q2 OR LOWER(COALESCE(p.lote, '')) LIKE :q3) AND p.estado = 1";
            $q = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
        } else {
            $where = "WHERE p.estado = 1";
        }

        $sql = "
            SELECT p.id, p.codigo, p.nombre, p.precio_compra1, p.precio_compra2, p.precio_venta, p.stock_actual, p.imagen, p.categoria_id
            FROM productos p
            $where
            ORDER BY $orderCol $dir
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT p.id, p.codigo, p.nombre, p.precio_compra1, p.precio_compra2, p.precio_venta, p.stock_actual, p.stock_minimo,
                   p.estado, p.imagen, p.categoria_id,
                   c.nombre AS categoria_nombre
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $nombre): ?array {
        $stmt = $this->db->prepare("
            SELECT p.id, p.codigo, p.nombre, p.precio_compra1, p.precio_compra2, p.precio_venta, p.stock_actual, p.stock_minimo,
                   p.estado, p.imagen, p.categoria_id
            FROM productos p
            WHERE LOWER(p.nombre) = :nombre
            LIMIT 1
        ");
        $stmt->execute([':nombre' => mb_strtolower($nombre, 'UTF-8')]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int {
        $fields = "nombre, precio_compra1, precio_compra2, precio_venta, stock_actual, stock_minimo, estado, imagen";
        $values = ":nombre, :precio_compra1, :precio_compra2, :precio_venta, :stock_actual, :stock_minimo, :estado, :imagen";
        $params = [
            ':nombre' => $data['nombre'],
            ':precio_compra1' => $data['precio_compra1'],
            ':precio_compra2' => $data['precio_compra2'],
            ':precio_venta' => $data['precio_venta'],
            ':stock_actual' => $data['stock_actual'],
            ':stock_minimo' => $data['stock_minimo'],
            ':estado' => $data['estado'],
            ':imagen' => $data['imagen'] ?? null,
        ];

        if (array_key_exists('codigo', $data) && $data['codigo'] !== '' && $data['codigo'] !== null) {
            $fields .= ", codigo";
            $values .= ", :codigo";
            $params[':codigo'] = $data['codigo'];
        }

        if (array_key_exists('categoria_id', $data) && $data['categoria_id'] !== '' && $data['categoria_id'] !== null) {
            $fields .= ", categoria_id";
            $values .= ", :categoria_id";
            $params[':categoria_id'] = (int)$data['categoria_id'];
        }

        $stmt = $this->db->prepare("INSERT INTO productos ($fields) VALUES ($values)");
        $stmt->execute($params);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void {
        $fields = "
            nombre = :nombre,
            precio_compra1 = :precio_compra1,
            precio_compra2 = :precio_compra2,
            precio_venta = :precio_venta,
            stock_actual = :stock_actual,
            stock_minimo = :stock_minimo,
            estado = :estado
        ";

        $params = [
            ':id' => $id,
            ':nombre' => $data['nombre'],
            ':precio_compra1' => $data['precio_compra1'],
            ':precio_compra2' => $data['precio_compra2'],
            ':precio_venta' => $data['precio_venta'],
            ':stock_actual' => $data['stock_actual'],
            ':stock_minimo' => $data['stock_minimo'],
            ':estado' => $data['estado'],
        ];

        if (array_key_exists('codigo', $data)) {
            $fields .= ", codigo = :codigo";
            $params[':codigo'] = $data['codigo'];
        }

        if (array_key_exists('categoria_id', $data)) {
            $fields .= ", categoria_id = :categoria_id";
            $params[':categoria_id'] = $data['categoria_id'] !== '' && $data['categoria_id'] !== null ? (int)$data['categoria_id'] : null;
        }

        if (array_key_exists('imagen', $data)) {
            $fields .= ", imagen = :imagen";
            $params[':imagen'] = $data['imagen'];
        }

        $stmt = $this->db->prepare("UPDATE productos SET $fields WHERE id = :id LIMIT 1");
        $stmt->execute($params);
    }
}
