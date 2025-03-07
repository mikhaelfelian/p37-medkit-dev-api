<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database and auth connection
include_once '../config/connect.php';
include_once '../config/auth.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Add debugging for authentication
$auth = new Auth();
$auth_header = $auth->getAuthorizationHeader();

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!$auth_header) {
    http_response_code(401);
    echo json_encode(array(
        "status" => "error",
        "message" => "No authorization header found",
        "debug" => array(
            "headers" => $auth->getAllHeaders(),
            "server" => $auth->getFilteredServer()
        )
    ));
    exit();
}

if (!$auth->validateAuth()) {
    http_response_code(401);
    echo json_encode(array(
        "status" => "error",
        "message" => "Invalid credentials",
        "debug" => array(
            "header" => $auth_header,
            "server" => $_SERVER
        )
    ));
    exit();
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Handle GET request
        try {
            $query = "SELECT * FROM tbl_m_produk";
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            $products = array();
            $rowCount = $stmt->rowCount();
            
            if($rowCount > 0) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $product = array();
                    
                    // Add fields that exist in your table
                    $product['id_produk']   = isset($row['id']) ? $row['id'] : null;
                    $product['nama_produk'] = isset($row['produk']) ? $row['produk'] : null;
                    $product['harga']       = isset($row['harga_jual']) ? $row['harga_jual'] : null;
                    $product['stok']        = isset($row['jml']) ? $row['jml'] : null;
                    
                    array_push($products, $product);
                }
                
                http_response_code(200);
                echo json_encode(array(
                    "status"        => "success",
                    "message"       => "Data found",
                    "total_data"    => $rowCount,
                    "data"          => $products
                ));
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "status"    => "error",
                    "message"   => "No data found",
                    "data"      => array()
                ));
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(array(
                "status"    => "error",
                "message"   => "Database error: " . $e->getMessage(),
                "query"     => $query
            ));
        }
        break;

    case 'POST':
        // Handle POST request
        try {
            // Get posted data
            $data = json_decode(file_get_contents("php://input"));
            
            if(!empty($data->OrderNumber)) {
                $db->beginTransaction();
                
                // Insert order header
                $headerQuery = "INSERT INTO tbl_orders (order_number, tgl_periksa, selesai) 
                               VALUES (:order_number, :tgl_periksa, :selesai)";
                $headerStmt = $db->prepare($headerQuery);
                
                // Sanitize header data
                $orderNumber = htmlspecialchars(strip_tags($data->OrderNumber));
                $tglPeriksa = htmlspecialchars(strip_tags($data->tgl_periksa));
                $selesai = htmlspecialchars(strip_tags($data->selesai));
                
                $headerStmt->bindParam(":order_number", $orderNumber);
                $headerStmt->bindParam(":tgl_periksa", $tglPeriksa);
                $headerStmt->bindParam(":selesai", $selesai);
                
                if($headerStmt->execute()) {
                    $orderId = $db->lastInsertId();
                    
                    // Insert order details
                    $detailQuery = "INSERT INTO tbl_order_details 
                                   (order_id, parameter_id, parameter, hasil, flag, satuan, n_rujukan, metoda) 
                                   VALUES (:order_id, :parameter_id, :parameter, :hasil, :flag, :satuan, :n_rujukan, :metoda)";
                    $detailStmt = $db->prepare($detailQuery);
                    
                    foreach($data->result_detail as $detail) {
                        $detailStmt->bindParam(":order_id", $orderId);
                        $detailStmt->bindParam(":parameter_id", $detail->ParameterID);
                        $detailStmt->bindParam(":parameter", $detail->Parameter);
                        $detailStmt->bindParam(":hasil", $detail->hasil);
                        $detailStmt->bindParam(":flag", $detail->flag);
                        $detailStmt->bindParam(":satuan", $detail->satuan);
                        $detailStmt->bindParam(":n_rujukan", $detail->n_rujukan);
                        $detailStmt->bindParam(":metoda", $detail->metoda);
                        
                        if(!$detailStmt->execute()) {
                            throw new Exception("Failed to insert detail record");
                        }
                    }
                    
                    $db->commit();
                    
                    http_response_code(201);
                    echo json_encode(array(
                        "status" => "success",
                        "message" => "Order created successfully",
                        "order_id" => $orderId
                    ));
                }
            } else {
                http_response_code(400);
                echo json_encode(array(
                    "status" => "error",
                    "message" => "Unable to create order. Data is incomplete."
                ));
            }
        } catch(Exception $e) {
            if($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode(array(
                "status" => "error",
                "message" => "Database error: " . $e->getMessage()
            ));
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(array(
            "status" => "error",
            "message" => "Method not allowed"
        ));
        break;
}
?> 