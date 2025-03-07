<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, PUT, POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: *");  // More permissive for testing
header("Access-Control-Allow-Credentials: true");

// Include database and auth connection
include_once 'config/connect.php';
include_once 'config/auth.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Add debugging for request method
$method = $_SERVER['REQUEST_METHOD'];
error_log("Request Method: " . $method);

// Enable error reporting
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ERROR | E_PARSE);

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
        "status"  => "error",
        "message" => "Invalid credentials",
        "debug"   => array(
            "header" => $auth_header,
            "server" => $_SERVER
        )
    ));
    exit();
}

switch($method) {
    case 'GET':
        try {
            // Check if PasienId parameter exists
            $PasienId = isset($_GET['pasienid']) ? $_GET['pasienid'] : null;
            
            // Base query
            $query = "SELECT * FROM v_medcheck_lab WHERE status_lis = '0'";
            
            // Add filter if PasienId is provided
            if ($PasienId !== null) {
                $query .= " AND PasienId = :PasienId";
            }
            
            $query .= " ORDER BY id DESC LIMIT 50";

            $stmt = $db->prepare($query);
            
            // Bind parameter if PasienId is provided
            if ($PasienId !== null) {
                $stmt->bindParam(':PasienId', $PasienId, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            
            $medlabs = array();
            $rowCount = $stmt->rowCount();
            
            if($rowCount > 0) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Get lab items
                    $item_query = "SELECT TindakanId, TindakanName FROM v_medcheck_lab_item WHERE id_lab = :id_lab";
                    $item_stmt = $db->prepare($item_query);
                    $item_stmt->bindParam(':id_lab', $row['id'], PDO::PARAM_INT);
                    $item_stmt->execute();
                    
                    $items = array();
                    while($item = $item_stmt->fetch(PDO::FETCH_ASSOC)) {
                        $items[] = $item;
                    }

                    $medlab = array(
                        "OrderDateTime"     => $row['OrderDateTime'],
                        "PasienId"          => $row['PasienId'],
                        "NoRM"              => $row['no_rm'],
                        "TrxID"             => $row['TrxID'],
                        "idMedcheck"        => $row['id_medcheck'],
                        "Nik"               => $row['Nik'],
                        "PasienName"        => $row['PasienName'],
                        "Address"           => $row['Address'],
                        "Gender"            => $row['Gender'],
                        "BirthDate"         => $row['BirthDate'],
                        "BirthPlace"        => $row['BirthPlace'],
                        "DokterPerujukName" => $row['DokterPerujukName'],
                        "items"             => $items
                    );

                    array_push($medlabs, $medlab);
                }
                
                http_response_code(200);
                echo json_encode(array(
                    "status"        => "success",
                    "message"       => "Data found",
                    "total_data"    => $rowCount,
                    "data"          => $medlabs
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

    case 'PUT':
        try {
            // Get posted data
            $data = json_decode(file_get_contents("php://input"));
            
            if(!empty($data->no_lab)) {
                $query = "UPDATE tbl_trans_medcheck_lab 
                         SET status = :status,
                             status_cvd   = :status_cvd,
                             status_duplo = :status_duplo,
                             tgl_modif    = NOW()
                         WHERE no_lab     = :no_lab";
                
                $stmt = $db->prepare($query);
                
                // Sanitize and bind data
                $no_lab         = htmlspecialchars(strip_tags($data->no_lab));
                $status         = isset($data->status) ? htmlspecialchars(strip_tags($data->status)) : '0';
                $status_cvd     = isset($data->status_cvd) ? htmlspecialchars(strip_tags($data->status_cvd)) : '0';
                $status_duplo   = isset($data->status_duplo) ? htmlspecialchars(strip_tags($data->status_duplo)) : '0';
                
                $stmt->bindParam(":no_lab", $no_lab);
                $stmt->bindParam(":status", $status);
                $stmt->bindParam(":status_cvd", $status_cvd);
                $stmt->bindParam(":status_duplo", $status_duplo);
                
                if($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode(array(
                        "status"    => "success",
                        "message"   => "Medical lab record updated successfully"
                    ));
                } else {
                    http_response_code(503);
                    echo json_encode(array(
                        "status"    => "error",
                        "message"   => "Unable to update medical lab record"
                    ));
                }
            } else {
                http_response_code(400);
                echo json_encode(array(
                    "status"    => "error",
                    "message"   => "Unable to update. No lab number provided."
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
        try {
            // Get parameters from URL
            $id_medcheck    = isset($_GET['id_medcheck']) ? $_GET['id_medcheck'] : null;
            $selesai        = isset($_GET['selesai']) ? $_GET['selesai'] : null;
            $tgl_periksa    = isset($_GET['tgl_periksa']) ? $_GET['tgl_periksa'] : null;
            $OrderNumber    = isset($_GET['OrderNumber']) ? $_GET['OrderNumber'] : null;
            $result_detail  = isset($_GET['result_detail']) ? json_decode($_GET['result_detail']) : null;
            
            // If not in URL, try to get from POST body
            if (!$result_detail) {
                $rawData    = file_get_contents("php://input");
                $postData   = json_decode($rawData);
                
                if ($postData) {
                    $id_medcheck    = isset($postData->id_medcheck) ? $postData->id_medcheck : $id_medcheck;
                    $selesai        = isset($postData->selesai) ? $postData->selesai : $selesai;
                    $tgl_periksa    = isset($postData->tgl_periksa) ? $postData->tgl_periksa : $tgl_periksa;
                    $OrderNumber    = isset($postData->OrderNumber) ? $postData->OrderNumber : $OrderNumber;
                    $result_detail  = isset($postData->result_detail) ? $postData->result_detail : $result_detail;
                }
            }
            
            if(!empty($id_medcheck) && !empty($result_detail)) {
                $db->beginTransaction();
                
                // Update results for each parameter
                $updateQuery = "UPDATE tbl_trans_medcheck_lab_hsl 
                              SET 
                                item_name = :item_name,
                                item_hasil = :item_hasil,
                                item_value = :item_value,
                                item_satuan = :item_satuan,
                                keterangan = :keterangan,
                                tgl_simpan = NOW()
                              WHERE id = :id";
                               
                $updateStmt = $db->prepare($updateQuery);
                
                foreach($result_detail as $detail) {
                    // Safely get properties with defaults
                    $id_medcheck_itm    = isset($detail->id_medcheck_itm) ? $detail->id_medcheck_itm : '';
                    $parameter          = isset($detail->Parameter) ? $detail->Parameter : '';
                    $hasil              = isset($detail->hasil) ? $detail->hasil : '';
                    $satuan             = isset($detail->satuan) ? $detail->satuan : '';
                    $n_rujukan          = isset($detail->n_rujukan) ? $detail->n_rujukan : '';
                    $flag               = isset($detail->flag) ? $detail->flag : '';
                    $keterangan         = isset($detail->metoda) ? $detail->metoda : '';
                    
                    // Make sure to bind ALL parameters including id_medcheck
                    $updateStmt->bindParam(":id", $id_medcheck_itm);
                    $updateStmt->bindParam(":item_name", $parameter);
                    $updateStmt->bindParam(":item_hasil", $hasil);
                    $updateStmt->bindParam(":item_value", $n_rujukan);
                    $updateStmt->bindParam(":item_satuan", $satuan);
                    $updateStmt->bindParam(":keterangan", $keterangan);

                    // Try to update first
                    if(!$updateStmt->execute()) {
                        throw new Exception("Failed to update result for parameter: " . $parameter);
                    }
                }
                
                // Update status in tbl_trans_medcheck_lab
                $updateQuery = "UPDATE tbl_trans_medcheck_lab 
                              SET status_lis = '1', tgl_modif = NOW() 
                              WHERE id_medcheck = :id_medcheck";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(":id_medcheck", $id_medcheck);
                $updateStmt->execute();
                
                $db->commit();
                
                http_response_code(200);
                echo json_encode(array(
                    "status" => "success",
                    "message" => "Lab results updated successfully"
                ));                
            } else {
                http_response_code(400);
                echo json_encode(array(
                    "status" => "error",
                    "message" => "Unable to update lab results. Required data missing (id_medcheck and result_detail required)"
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