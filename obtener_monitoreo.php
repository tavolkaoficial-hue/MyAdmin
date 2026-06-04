<?php
// Habilitar visualización de errores por si hay fallos internos
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cabeceras de acceso absoluto para evitar bloqueos en tu Mac
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Configurar la zona horaria correcta para asegurar sincronización de fechas
date_default_timezone_set('America/Bogota'); 

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

// Obtener la fecha de hoy en formato AAAA-MM-DD
$fecha_hoy = date("Y-m-d");

try {
    // Consulta SQL unificando asignación, asistencia y el reporte diario en Base64
    $query = "SELECT 
                u.nombre AS empleado,
                p.nombre_proyecto AS obra,
                p.direccion AS direccion,
                p.empresa_aliada AS aliado,
                a.horario_estimado AS horario_asignado,
                asist.hora_entrada,
                asist.hora_salida,
                asist.gps_entrada AS gps_real,
                r.novedades AS novedades_reporte,
                r.fotos_urls AS fotos_reporte
              FROM asignaciones a
              INNER JOIN usuarios u ON a.id_usuario = u.id
              INNER JOIN proyectos p ON a.id_proyecto = p.id
              LEFT JOIN asistencias asist ON asist.id_asignacion = a.id
              LEFT JOIN reportes_diarios r ON r.id_asistencia = asist.id
              WHERE a.fecha_laboral = :fecha_hoy
              ORDER BY a.id DESC";

    $stmt = $conexion->prepare($query);
    $stmt->bindParam(":fecha_hoy", $fecha_hoy);
    $stmt->execute();
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $monitoreo = [];
    foreach ($resultados as $row) {
        
        // 1. Manejo del estado del tiempo laborado
        $horas_calculadas = "No ha iniciado turno";
        
        if (!empty($row['hora_entrada'])) {
            $hora_inicio = new DateTime($row['hora_entrada']);
            
            if (!empty($row['hora_salida']) && $row['hora_salida'] !== '00:00:00') {
                $hora_fin = new DateTime($row['hora_salida']);
                $intervalo = $hora_inicio->diff($hora_fin);
                $horas_calculadas = $intervalo->format('%H:%I:%S') . " (Finalizado)";
            } else {
                $hora_actual = new DateTime();
                $intervalo = $hora_inicio->diff($hora_actual);
                $horas_calculadas = $intervalo->format('%H:%I:%S') . " (En curso ⏱️)";
            }
        } else {
            $horas_calculadas = $row['horario_asignado'];
        }

        // 2. Control de ubicación GPS
        $gps_status = !empty($row['gps_real']) ? $row['gps_real'] : "Pendiente de Check-In";

        // 3. Control de Novedades Exclusivas del Trabajador
        $novedades_status = !empty($row['novedades_reporte']) ? $row['novedades_reporte'] : "Sin novedades del operario.";
        
        // 4. LIMPIEZA Y FILTRADO DE FOTOS CORRUPTAS / VACÍAS
        $fotos_status = "";
        if (!empty($row['fotos_reporte'])) {
            // Separamos la cadena por comas por si vienen múltiples imágenes
            $array_fotos = explode(',', $row['fotos_reporte']);
            
            // Filtramos el array conservando solo los elementos válidos
            $fotos_validas = array_filter($array_fotos, function($imagen) {
                $imagen_limpia = trim($imagen);
                // Validamos que no esté vacío y que tenga un tamaño coherente de Base64 válido (mayor a 50 caracteres)
                return !empty($imagen_limpia) && strlen($imagen_limpia) > 50;
            });
            
            // Volvemos a unir el array limpio con comas para mantener la estructura que espera el Javascript
            $fotos_status = implode(',', array_map('trim', $fotos_validas));
        }

        // Claves emparejadas con las que busca el Javascript del administrador
        $monitoreo[] = [
            "empleado"             => $row['empleado'],
            "obra"                 => $row['obra'],
            "direccion"            => $row['direccion'],
            "aliado"               => $row['aliado'] ? $row['aliado'] : "Ninguno",
            "horas_trabajadas"     => $horas_calculadas,
            "gps"                  => $gps_status,
            "novedades_reportadas" => $novedades_status,
            "fotos_urls"           => $fotos_status // Envía únicamente el combo de fotos 100% reales y legibles
        ];
    }

    echo json_encode([
        "status" => "success",
        "data" => $monitoreo
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error al consultar: " . $e->getMessage()]);
}
?>