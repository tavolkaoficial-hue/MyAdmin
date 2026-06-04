<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$servidor = "localhost";
$usuario  = "root";
$password = ""; 
$base_datos = "MyAdmin";

// Capturar el ID que envía la aplicación móvil del trabajador
$id_usuario = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_usuario === 0) {
    echo json_encode(["status" => "error", "message" => "ID de usuario inválido."]);
    exit();
}

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4", $usuario, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Validar que el empleado exista en la base de datos
    $stmtUser = $conexion->prepare("SELECT id, nombre FROM usuarios WHERE id = :id LIMIT 1");
    $stmtUser->bindParam(":id", $id_usuario, PDO::PARAM_INT);
    $stmtUser->execute();
    $empleado = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$empleado) {
        echo json_encode(["status" => "error", "message" => "El empleado no existe en el sistema."]);
        exit();
    }

    // 2. CONSULTA DEFINITIVA: Cruzar la tabla 'asignaciones' con 'proyectos'
    $queryProyecto = "SELECT 
                        a.id_proyecto,
                        p.nombre_proyecto, 
                        p.direccion,
                        a.horario_estimado,
                        a.detalles_trabajo
                      FROM asignaciones a
                      INNER JOIN proyectos p ON a.id_proyecto = p.id
                      WHERE a.id_usuario = :id 
                      ORDER BY a.id DESC LIMIT 1";
                      
    $stmtProj = $conexion->prepare($queryProyecto);
    $stmtProj->bindParam(":id", $id_usuario, PDO::PARAM_INT);
    $stmtProj->execute();
    $proyectoReal = $stmtProj->fetch(PDO::FETCH_ASSOC);

    if ($proyectoReal) {
        // Formateamos las notas y el horario para que queden claros en la tarjeta de la app
        $indicaciones = $proyectoReal['horario_estimado'] . " | " . $proyectoReal['detalles_trabajo'];

        echo json_encode([
            "status" => "success",
            "empleado" => $empleado,
            "proyecto" => [
                "id_proyecto" => $proyectoReal['id_proyecto'],
                "nombre_proyecto" => $proyectoReal['nombre_proyecto'],
                "direccion" => $proyectoReal['direccion'],
                "horario_estimado" => $indicaciones 
            ]
        ]);
    } else {
        // Si el empleado no tiene ninguna asignación guardada
        echo json_encode([
            "status" => "success",
            "empleado" => $empleado,
            "proyecto" => null
        ]);
    }

} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error de base de datos: " . $e->getMessage()]);
}
?>