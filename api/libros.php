<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $q = trim($_GET['q'] ?? '');
        $categoria = trim($_GET['categoria'] ?? '');
        $disponible = $_GET['disponible'] ?? '';

        $sql = "SELECT * FROM libros WHERE 1=1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (titulo LIKE :q OR autor LIKE :q OR isbn LIKE :q)";
            $params[':q'] = "%$q%";
        }
        if ($categoria !== '' && $categoria !== 'Todas') {
            $sql .= " AND categoria = :categoria";
            $params[':categoria'] = $categoria;
        }
        if ($disponible === '1' || $disponible === '0') {
            $sql .= " AND disponible = :disponible";
            $params[':disponible'] = (int)$disponible;
        }

        $sql .= " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $libros = $stmt->fetchAll();

        // Obtener categorías únicas para llenar el selector
        $cats = $pdo->query("SELECT DISTINCT categoria FROM libros ORDER BY categoria ASC")
                    ->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'ok' => true,
            'total' => count($libros),
            'categorias' => $cats,
            'data' => $libros
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($method === 'POST') {
        $requeridos = ['titulo', 'autor', 'categoria', 'anio_publicacion', 'isbn', 'editorial', 'sinopsis'];
        foreach ($requeridos as $campo) {
            if (empty(trim((string)($input[$campo] ?? '')))) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => "El campo '$campo' es obligatorio."]);
                exit;
            }
        }

        $stmt = $pdo->prepare(
            "INSERT INTO libros (titulo, autor, categoria, anio_publicacion, isbn, editorial, paginas, sinopsis, disponible)
             VALUES (:titulo, :autor, :categoria, :anio, :isbn, :editorial, :paginas, :sinopsis, 1)"
        );
        $stmt->execute([
            ':titulo'    => trim($input['titulo']),
            ':autor'     => trim($input['autor']),
            ':categoria' => trim($input['categoria']),
            ':anio'      => (int)$input['anio_publicacion'],
            ':isbn'      => trim($input['isbn']),
            ':editorial' => trim($input['editorial']),
            ':paginas'   => max(1, (int)($input['paginas'] ?? 100)),
            ':sinopsis'  => trim($input['sinopsis']),
        ]);

        http_response_code(201);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId(), 'mensaje' => 'Libro registrado.']);
        exit;
    }

    if ($method === 'PUT') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE libros SET disponible = NOT disponible WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['ok' => true, 'mensaje' => 'Disponibilidad actualizada.']);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? ($input['id'] ?? 0));
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM libros WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['ok' => true, 'mensaje' => 'Libro eliminado.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error en base de datos: ' . $e->getMessage()]);
}
