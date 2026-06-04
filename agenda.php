<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$servidor = "localhost";
$usuario  = "root";
$password = ""; 
$base_datos = "MyAdmin";

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4", $usuario, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error de conexión: " . $e->getMessage()]);
    exit();
}

$datos = json_decode(file_get_contents("php://input"));

$id_usuario = isset($datos->id_usuario) ? $datos->id_usuario : (isset($_POST['id_usuario']) ? $_POST['id_usuario'] : null);
$fecha_busqueda = isset($datos->fecha) ? $datos->fecha : (isset($_POST['fecha']) ? $_POST['fecha'] : date("Y-m-d"));

if(!empty($id_usuario)) {
    try {
        // CONSULTA AJUSTADA DETALLADAMENTE A TU ESTRUCTURA REAL
        $query = "SELECT 
                    p.nombre_proyecto, 
                    p.direccion, 
                    p.empresa_aliada, 
                    a.horario_estimado, 
                    a.detalles_trabajo 
                  FROM asignaciones a
                  INNER JOIN proyectos p ON a.id_proyecto = p.id
                  WHERE a.id_usuario = :id_usuario AND a.fecha_laboral = :fecha
                  LIMIT 1";
                  
        $stmt = $conexion->prepare($query);
        $stmt->bindParam(":id_usuario", $id_usuario);
        $stmt->bindParam(":fecha", $fecha_busqueda);
        $stmt->execute();
        
        $jornada = $stmt->fetch(PDO::FETCH_ASSOC);

        if($jornada) {
            echo json_encode([
                "status" => "success",
                "jornada" => $jornada
            ]);
        } else {
            echo json_encode([
                "status" => "empty",
                "message" => "No tienes obras asignadas para el día de hoy."
            ]);
        }
    } catch(Exception $e) {
        echo json_encode(["status" => "error", "message" => "Error en consulta: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Falta el ID de usuario."]);
}
?>