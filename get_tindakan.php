<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Credentials: true");

// Include database and auth connection
include_once 'config/connect.php';
include_once 'config/auth.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Move OPTIONS handling before authentication
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Add debugging for authentication
$auth = new Auth();
$auth_header = $auth->getAuthorizationHeader();

if (!$auth_header) {
    http_response_code(401);
    echo json_encode(array(
        "status" => "error",
        "message" => "No authorization header found"
    ));
    exit();
}

if (!$auth->validateAuth()) {
    http_response_code(401);
    echo json_encode(array(
        "status" => "error",
        "message" => "Invalid credentials"
    ));
    exit();
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Base query
        $query = "SELECT * FROM tbl_m_produk_ref_input WHERE status = '3'";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $tindakan = array();
        $rowCount = $stmt->rowCount();
        
        if($rowCount > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tindakan_item = array(
                    "id" => $row['id'],
                    "item_name" => $row['item_name']
                );
                array_push($tindakan, $tindakan_item);
            }
            
            http_response_code(200);
            echo json_encode(array(
                "status" => "success",
                "message" => "Data found",
                "total_data" => $rowCount,
                "data" => $tindakan
            ));
        } else {
            http_response_code(404);
            echo json_encode(array(
                "status" => "error",
                "message" => "No data found",
                "data" => array()
            ));
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(array(
            "status" => "error",
            "message" => "Database error: " . $e->getMessage()
        ));
    }
} else {
    http_response_code(405);
    echo json_encode(array(
        "status" => "error",
        "message" => "Method not allowed"
    ));
}
?> 