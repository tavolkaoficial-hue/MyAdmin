<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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

if (
    !empty($datos->nombre_proyecto) && 
    !empty($datos->direccion) && 
    !empty($datos->id_usuario) && 
    !empty($datos->fecha_laboral) && 
    !empty($datos->detalles_trabajo)
) {
    try {
        $conexion->beginTransaction();

        $queryProyecto = "INSERT INTO proyectos (nombre_proyecto, direccion, empresa_aliada) 
                          VALUES (:nombre, :direccion, :aliado)";
        $stmtP = $conexion->prepare($queryProyecto);
        $stmtP->bindParam(":nombre", $datos->nombre_proyecto);
        $stmtP->bindParam(":direccion", $datos->direccion);
        $stmtP->bindParam(":aliado", $datos->empresa_aliada); 
        $stmtP->execute();

        $idProyectoCreado = $conexion->lastInsertId();

        $queryAsignacion = "INSERT INTO asignaciones (id_usuario, id_proyecto, fecha_laboral, horario_estimado, detalles_trabajo) 
                            VALUES (:id_usuario, :id_proyecto, :fecha, :horario, :detalles)";
        $stmtA = $conexion->prepare($queryAsignacion);
        
        $horario_por_defecto = !empty($datos->horario_estimado) ? $datos->horario_estimado : "08:00 - 17:00";

        $stmtA->bindParam(":id_usuario", $datos->id_usuario);
        $stmtA->bindParam(":id_proyecto", $idProyectoCreado);
        $stmtA->bindParam(":fecha", $datos->fecha_laboral);
        $stmtA->bindParam(":horario", $horario_por_defecto);
        $stmtA->bindParam(":detalles", $datos->detalles_trabajo);
        $stmtA->execute();

        $conexion->commit();

        echo json_encode([
            "status" => "success",
            "message" => "Proyecto creado y jornada asignada exitosamente."
        ]);

    } catch (Exception $e) {
        $conexion->rollBack();
        echo json_encode(["status" => "error", "message" => "Error en el proceso: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Por favor, completa todos los campos obligatorios."]);
}
?>