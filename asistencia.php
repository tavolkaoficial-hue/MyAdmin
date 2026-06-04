<?php
// Configuración de cabeceras seguras para comunicación móvil y web
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

// Capturar los datos desde la App Móvil / Frontend
$datos_recibidos = json_decode(file_get_contents("php://input"));

if (
    !empty($datos_recibidos->id_usuario) && 
    !empty($datos_recibidos->accion) && 
    !empty($datos_recibidos->descriptor_vivo)
) {
    
    $id_usuario      = intval($datos_recibidos->id_usuario);
    $accion          = strtolower($datos_recibidos->accion); // 'entrada' o 'salida'
    $gps             = !empty($datos_recibidos->gps) ? $datos_recibidos->gps : "0,0 (GPS Desactivado)";
    $descriptor_vivo = json_decode($datos_recibidos->descriptor_vivo, true);
    
    date_default_timezone_set('America/Bogota'); 
    $hora_actual     = date("Y-m-d H:i:s");

    try {
        // ========================================================
        // PROCESO 1: VALIDACIÓN BIOMÉTRICA (FACE ID)
        // ========================================================
        $stmtUser = $conexion->prepare("SELECT biometrico_rostro FROM usuarios WHERE id = :id LIMIT 1");
        $stmtUser->bindParam(":id", $id_usuario, PDO::PARAM_INT);
        $stmtUser->execute();
        $usuario_db = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$usuario_db || empty($usuario_db['biometrico_rostro'])) {
            echo json_encode(["status" => "error", "message" => "El usuario no cuenta con un registro biométrico previo."]);
            exit();
        }

        $descriptor_original = json_decode($usuario_db['biometrico_rostro'], true);

        // Algoritmo de Distancia Euclidiana
        $suma_cuadrados = 0.0;
        for ($i = 0; $i < count($descriptor_original); $i++) {
            $diferencia = $descriptor_original[$i] - $descriptor_vivo[$i];
            $suma_cuadrados += $diferencia * $diferencia;
        }
        $distancia_final = sqrt($suma_cuadrados);
        $umbral_seguridad = 0.55; 

        if ($distancia_final > $umbral_seguridad) {
            echo json_encode([
                "status" => "error", 
                "message" => "Reconocimiento Facial Fallido. Identidad no verificada."
            ]);
            exit();
        }

        // ========================================================
        // PROCESO 2: BUSCAR LA ASIGNACIÓN ACTIVA DEL EMPLEADO
        // ========================================================
        $fecha_hoy = date("Y-m-d");
        $stmtAsig = $conexion->prepare("SELECT id FROM asignaciones WHERE id_usuario = :id AND fecha_laboral = :fecha_hoy ORDER BY id DESC LIMIT 1");
        $stmtAsig->bindParam(":id", $id_usuario, PDO::PARAM_INT);
        $stmtAsig->bindParam(":fecha_hoy", $fecha_hoy);
        $stmtAsig->execute();
        $asignacion = $stmtAsig->fetch(PDO::FETCH_ASSOC);

        if (!$asignacion) {
            echo json_encode(["status" => "error", "message" => "No tienes ninguna obra asignada para el día de hoy."]);
            exit();
        }

        $id_asignacion = $asignacion['id'];

        // ========================================================
        // PROCESO 3: CONTROL DE RELOJ CHECADOR (ENTRADA / SALIDA)
        // ========================================================
        if ($accion === 'entrada') {
            // Verificar duplicados de entrada
            $checkStmt = $conexion->prepare("SELECT id FROM asistencias WHERE id_asignacion = :id_asig LIMIT 1");
            $checkStmt->bindParam(":id_asig", $id_asignacion);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                echo json_encode(["status" => "error", "message" => "Ya has registrado tu entrada para el día de hoy."]);
                exit();
            }

            // Insertar asistencia de entrada
            $query = "INSERT INTO asistencias (id_asignacion, hora_entrada, gps_entrada) 
                      VALUES (:id_asignacion, :hora_entrada, :gps_entrada)";
            $stmt = $conexion->prepare($query);
            $stmt->bindParam(":id_asignacion", $id_asignacion);
            $stmt->bindParam(":hora_entrada", $hora_actual);
            $stmt->bindParam(":gps_entrada", $gps);
            $stmt->execute();

            echo json_encode([
                "status" => "success", 
                "message" => "Entrada registrada exitosamente. ¡Buen turno!",
                "hora_registro" => $hora_actual
            ]);

        } elseif ($accion === 'salida') {
            // Buscar entrada previa obligatoria
            $stmtBuscar = $conexion->prepare("SELECT id, hora_salida FROM asistencias WHERE id_asignacion = :id_asig LIMIT 1");
            $stmtBuscar->bindParam(":id_asig", $id_asignacion);
            $stmtBuscar->execute();

            if ($stmtBuscar->rowCount() > 0) {
                $asistencia = $stmtBuscar->fetch(PDO::FETCH_ASSOC);
                $id_asistencia = $asistencia['id'];

                if (!empty($asistencia['hora_salida'])) {
                    echo json_encode(["status" => "error", "message" => "Ya has registrado tu salida para esta jornada."]);
                    exit();
                }

                // VALIDACIÓN DE SEGURIDAD BASE64 UNIFICADA: Validar que el combo de fotos y las novedades no vengan vacíos
                if (empty($datos_recibidos->novedades) || empty($datos_recibidos->fotos_combo)) {
                    echo json_encode(["status" => "error", "message" => "Faltan datos obligatorios. Debes ingresar comentarios y capturar ambas fotos de evidencia antes de enviar."]);
                    exit();
                }

                // Recibimos las variables puras listas para inyectar en la base de datos
                $fotos_urls_string = $datos_recibidos->fotos_combo; 
                $novedades_texto   = trim($datos_recibidos->novedades);

                // ========================================================
                // TRANSACCIÓN SQL: ACTUALIZAR SALIDA E INSERTAR REPORTE
                // ========================================================
                $conexion->beginTransaction();

                // 1. Actualizar salida en la tabla asistencias
                $queryUpdate = "UPDATE asistencias 
                                SET hora_salida = :hora_salida, gps_salida = :gps_salida 
                                WHERE id = :id_asistencia";
                $stmtUpdate = $conexion->prepare($queryUpdate);
                $stmtUpdate->bindParam(":hora_salida", $hora_actual);
                $stmtUpdate->bindParam(":gps_salida", $gps);
                $stmtUpdate->bindParam(":id_asistencia", $id_asistencia);
                $stmtUpdate->execute();

                // 2. Insertar las cadenas Base64 completas directamente en la columna fotos_urls de reportes_diarios
                $queryReporte = "INSERT INTO reportes_diarios (id_asistencia, novedades, fotos_urls) 
                                 VALUES (:id_asistencia, :novedades, :fotos_urls)";
                $stmtReporte = $conexion->prepare($queryReporte);
                $stmtReporte->bindParam(":id_asistencia", $id_asistencia);
                $stmtReporte->bindParam(":novedades", $novedades_texto);
                $stmtReporte->bindParam(":fotos_urls", $fotos_urls_string); // El texto largo Base64 se guarda aquí
                $stmtReporte->execute();

                // Confirmar cambios de forma segura en la base de datos
                $conexion->commit();

                echo json_encode([
                    "status" => "success", 
                    "message" => "Reporte diario subido en Base64 y salida registrada correctamente. Jornada finalizada.",
                    "hora_registro" => $hora_actual
                ]);

            } else {
                echo json_encode(["status" => "error", "message" => "No se encontró un registro de entrada previo para esta jornada."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "La acción biométrica solicitada no es válida."]);
        }

    } catch (Exception $e) {
        // Si algo truena en la inserción, deshacer los cambios en la BD para evitar corrupción de datos
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        echo json_encode(["status" => "error", "message" => "Error en las operaciones del servidor: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Datos incompletos para procesar la solicitud."]);
}
?>