<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$servidor = "localhost";
$usuario  = "root";
$password = ""; 
$base_datos = "MyAdmin";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
    exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$documento = isset($_POST['documento']) ? trim($_POST['documento']) : '';
$biometrico = isset($_POST['biometrico']) ? $_POST['biometrico'] : '';

if ($id === 0 || empty($documento) || empty($biometrico) || !isset($_FILES['foto_cedula'])) {
    echo json_encode(["status" => "error", "message" => "Información incompleta para el registro biométrico."]);
    exit();
}

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4", $usuario, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ajustamos la ruta para que use la ruta absoluta completa en la instalación de XAMPP en Mac
    $base_dir = dirname(__FILE__) . '/';
    $carpeta_destino = $base_dir . "uploads_documentos/";

    // Intentamos crear la carpeta forzando los permisos en el sistema de archivos de Apple
    if (!file_exists($carpeta_destino)) {
        mkdir($carpeta_destino, 0777, true);
        chmod($carpeta_destino, 0777); // Refuerza el permiso de escritura para XAMPP
    }

    $extension = strtolower(pathinfo($_FILES['foto_cedula']['name'], PATHINFO_EXTENSION));
    $nombre_archivo = "cedula_" . $id . "_" . time() . "." . $extension;
    
    // Ruta absoluta que necesita move_uploaded_file para Mac
    $ruta_completa_destino = $carpeta_destino . $nombre_archivo;
    
    // Ruta relativa limpia que guardaremos en la base de datos
    $ruta_db = "uploads_documentos/" . $nombre_archivo;

    if (move_uploaded_file($_FILES['foto_cedula']['tmp_name'], $ruta_completa_destino)) {
        
        // Guardar en la Base de Datos y activar la cuenta del empleado
        $query = "UPDATE usuarios SET 
                    documento_identidad = :documento,
                    foto_documento = :foto,
                    biometrico_rostro = :biometrico,
                    registro_completo = 1,
                    token_registro = NULL 
                  WHERE id = :id";

        $stmt = $conexion->prepare($query);
        $stmt->bindParam(":documento", $documento);
        $stmt->bindParam(":foto", $ruta_db);
        $stmt->bindParam(":biometrico", $biometrico);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        
        $stmt->execute();

        echo json_encode(["status" => "success", "message" => "Perfil biométrico configurado exitosamente."]);
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "No se pudo mover el archivo. Intenta cambiar los permisos de la carpeta uploads_documentos en tu Mac usando el Finder (Obtener información -> Compartir y permisos -> Leer y escribir para todos)."
        ]);
    }

} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error al guardar en MySQL: " . $e->getMessage()]);
}
?>