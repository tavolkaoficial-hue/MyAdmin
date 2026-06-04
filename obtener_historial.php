<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$servidor = "localhost";
$usuario  = "root";
$password = ""; 
$base_datos = "MyAdmin";

$id_usuario = isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : 0;

if ($id_usuario === 0) {
    echo json_encode(["status" => "error", "message" => "ID de usuario inválido."]);
    exit();
}

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4", $usuario, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // CAMBIO CLAVE: Calculamos en SECOND (segundos) para no perder ni un pestañeo
    // Dividimos entre 3600 para pasarlo a horas decimales exactas con 4 decimales para la calculadora
    $query = "SELECT 
                a.id AS id_asignacion,
                a.fecha_laboral,
                p.nombre_proyecto,
                p.direccion,
                a.horario_estimado,
                asist.hora_entrada,
                asist.hora_salida,
                ROUND(TIMESTAMPDIFF(SECOND, asist.hora_entrada, asist.hora_salida) / 3600, 4) AS horas_reales
              FROM asignaciones a
              INNER JOIN proyectos p ON a.id_proyecto = p.id
              LEFT JOIN asistencias asist ON asist.id_asignacion = a.id
              WHERE a.id_usuario = :id_usuario
              ORDER BY a.fecha_laboral DESC, a.id DESC";

    $stmt = $conexion->prepare($query);
    $stmt->bindParam(":id_usuario", $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $resultados
    ]);

} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
}
?>