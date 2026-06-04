<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// INICIO MODIFICACIÓN: Iniciar sesiones en el servidor para proteger rutas administrativas
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// FIN MODIFICACIÓN

$servidor = "localhost";
$usuario  = "root";
$password = ""; 
$base_datos = "MyAdmin";

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4", $usuario, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    exit();
}

$datos_recibidos = json_decode(file_get_contents("php://input"));

if (!empty($datos_recibidos->correo) && !empty($datos_recibidos->password)) {
    
    $correo_usuario = $datos_recibidos->correo;
    $password_usuario = $datos_recibidos->password;

    try {
        $query = "SELECT id, nombre, correo, password, rol FROM usuarios WHERE correo = :correo LIMIT 0,1";
        $stmt = $conexion->prepare($query);
        $stmt->bindParam(":correo", $correo_usuario);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($password_usuario === $fila['password']) {
                
                // INICIO MODIFICACIÓN: Si el usuario es administrador, creamos la sesión en el servidor
                if ($fila['rol'] === 'admin') {
                    $_SESSION['id_admin'] = $fila['id'];
                    $_SESSION['nombre_admin'] = $fila['nombre'];
                    $_SESSION['rol'] = $fila['rol'];
                }
                // FIN MODIFICACIÓN

                echo json_encode([
                    "status" => "success",
                    "message" => "Acceso concedido.",
                    "user" => [
                        "id" => $fila['id'],
                        "nombre" => $fila['nombre'],
                        "correo" => $fila['correo'],
                        "rol" => $fila['rol']
                    ]
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Contraseña incorrecta."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "El correo electrónico no está registrado."]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Datos incompletos."]);
}
?>