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

    // Buscamos solo a los usuarios que son empleados y ya completaron su registro
    $query = "SELECT id, nombre FROM usuarios WHERE rol = 'empleado' AND registro_completo = 1 ORDER BY nombre ASC";
    $stmt = $conexion->prepare($query);
    $stmt->execute();
    
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $empleados
    ]);

} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error de BD: " . $e->getMessage()]);
}
?>