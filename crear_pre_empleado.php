<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['nombre']) || empty($input['telefono']) || empty($input['correo'])) {
    echo json_encode(["status" => "error", "message" => "Faltan campos obligatorios."]);
    exit();
}

$servidor = "localhost";
$usuario  = "root";
$password_db = ""; 
$base_datos = "MyAdmin";

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4", $usuario, $password_db);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $token = bin2hex(random_bytes(16));
    $pass_temporal = password_hash("Temporal123*", PASSWORD_BCRYPT);
    $rol = "empleado";

    $query = "INSERT INTO usuarios (nombre, correo, telefono, password, rol, token_registro, registro_completo) 
              VALUES (:nombre, :correo, :telefono, :password, :rol, :token, 0)";
              
    $stmt = $conexion->prepare($query);
    $stmt->bindParam(":nombre", $input['nombre']);
    $stmt->bindParam(":correo", $input['correo']);
    $stmt->bindParam(":telefono", $input['telefono']);
    $stmt->bindParam(":password", $pass_temporal);
    $stmt->bindParam(":rol", $rol);
    $stmt->bindParam(":token", $token);
    
    $stmt->execute();

    // Generamos el enlace único
    $enlace_registro = "http://localhost/MyAdmin/completar_registro.html?token=" . $token;

    // Enviamos el enlace de vuelta al HTML de forma directa
    echo json_encode([
        "status" => "success",
        "message" => "Pre-registro guardado con éxito.",
        "url_enviada_simulada" => $enlace_registro
    ]);

} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error de BD: " . $e->getMessage()]);
}
?>