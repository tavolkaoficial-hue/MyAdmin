<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

// Manejo del Preflight de seguridad de los navegadores
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $conexion = new PDO("mysql:host=localhost;dbname=MyAdmin;charset=utf8mb4", "root", "");
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents("php://input"), true);

    // Validación flexible: el teléfono puede ser opcional o venir vacío
    if (!empty($data['id']) && !empty($data['nombre']) && !empty($data['correo'])) {
        
        $stmt = $conexion->prepare("UPDATE usuarios SET nombre = :nom, correo = :corr, telefono = :tel WHERE id = :id");
        $stmt->execute([
            ':nom'      => $data['nombre'],
            ':corr'     => $data['correo'],
            ':tel'      => isset($data['telefono']) ? $data['telefono'] : null,
            ':id'       => intval($data['id'])
        ]);

        echo json_encode(["status" => "success", "message" => "Usuario actualizado correctamente."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Faltan campos obligatorios (ID, Nombre o Correo)."]);
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error de BD: " . $e->getMessage()]);
}
?>