<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$servidor = "localhost";
$usuario  = "root";
$password = ""; 
$base_datos = "MyAdmin";

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4", $usuario, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Selecciona los usuarios con rol 'empleado' y que además estén activos (activo = 1)
    $query = "SELECT id, nombre, correo, telefono FROM usuarios WHERE rol = 'empleado' AND activo = 1 ORDER BY nombre ASC";
    $stmt = $conexion->prepare($query);
    $stmt->execute();
    
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $empleados
    ]);

} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>