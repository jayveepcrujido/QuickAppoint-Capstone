<?php
// validate_id_ajax.php - AJAX endpoint for ID validation

// Prevent any output before JSON
ob_start();

// Set error handling to not display errors directly
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON header first
header('Content-Type: application/json');

try {
    // Check if validate_id.php exists
    if (!file_exists('validate_id.php')) {
        throw new Exception('validate_id.php file not found');
    }
    
    require_once 'validate_id.php';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_only'])) {
        $valid_id_type = $_POST['valid_id_type'] ?? '';
        
        if (empty($valid_id_type)) {
            echo json_encode([
                'valid' => false,
                'score' => 0,
                'message' => 'ID type not provided'
            ]);
            exit;
        }
        
        // Handle file upload
        if (isset($_FILES['id_front']) && $_FILES['id_front']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'temp_uploads/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }
            
            // Create temporary file
            $temp_file = $uploadDir . 'temp_' . uniqid() . '_' . basename($_FILES['id_front']['name']);
            
            if (move_uploaded_file($_FILES['id_front']['tmp_name'], $temp_file)) {
                // Check if IDValidator class exists
                if (!class_exists('IDValidator')) {
                    throw new Exception('IDValidator class not found in validate_id.php');
                }
                
                // Validate ID
                $validator = new IDValidator();
                $result = $validator->validateID($temp_file, $valid_id_type);
                
                // Clean up temporary file
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
                
                // Clear any buffered output
                ob_end_clean();
                
                // Return JSON response
                echo json_encode([
                    'valid' => $result['valid'],
                    'score' => $result['score'],
                    'message' => $result['message']
                ]);
            } else {
                throw new Exception('Failed to move uploaded file to temporary location');
            }
        } else {
            $error_msg = 'No file uploaded';
            if (isset($_FILES['id_front']['error'])) {
                switch($_FILES['id_front']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_msg = 'File too large';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_msg = 'No file was uploaded';
                        break;
                    default:
                        $error_msg = 'Upload error code: ' . $_FILES['id_front']['error'];
                }
            }
            
            ob_end_clean();
            echo json_encode([
                'valid' => false,
                'score' => 0,
                'message' => $error_msg
            ]);
        }
    } else {
        ob_end_clean();
        echo json_encode([
            'valid' => false,
            'score' => 0,
            'message' => 'Invalid request method or missing validation flag'
        ]);
    }
} catch (Exception $e) {
    // Clear any buffered output
    ob_end_clean();
    
    echo json_encode([
        'valid' => false,
        'score' => 0,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Catch fatal errors too
    ob_end_clean();
    
    echo json_encode([
        'valid' => false,
        'score' => 0,
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
}
?>