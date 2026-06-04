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

    if (!empty($data['id'])) {
        
        // Hacemos el borrado lógico poniendo activo = 0 (vimos que ya creaste la columna exitosamente en phpMyAdmin)
        $stmt = $conexion->prepare("UPDATE usuarios SET activo = 0 WHERE id = :id");
        $stmt->execute([':id' => intval($data['id'])]);

        echo json_encode(["status" => "success", "message" => "Empleado dado de baja con éxito."]);
    } else {
        echo json_encode(["status" => "error", "message" => "ID de usuario inválido o ausente."]);
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error de BD: " . $e->getMessage()]);
}
?>