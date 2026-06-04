<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$servidor = "localhost";
$usuario  = "root";
$password = ""; 
$base_datos = "MyAdmin";

$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    echo json_encode(["status" => "error", "message" => "Acceso denegado: Token vacío."]);
    exit();
}

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4", $usuario, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = "SELECT id, nombre, registro_completo FROM usuarios WHERE token_registro = :token LIMIT 1";
    $stmt = $conexion->prepare($query);
    $stmt->bindParam(":token", $token);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['registro_completo'] == 1) {
            echo json_encode(["status" => "error", "message" => "Este enlace ya fue utilizado anteriormente."]);
        } else {
            echo json_encode([
                "status" => "success",
                "id" => $user['id'],
                "nombre" => $user['nombre']
            ]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "El enlace de registro no es válido o ya expiró."]);
    }

} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error interno: " . $e->getMessage()]);
}
?>