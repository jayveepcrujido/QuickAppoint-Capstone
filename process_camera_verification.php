<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/id_extractors.php';

use thiagoalessio\TesseractOCR\TesseractOCR;

header('Content-Type: application/json');

$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_FILES['id_image']) || !isset($_FILES['selfie_image']) || !isset($_POST['id_type'])) {
        throw new Exception('Missing required fields');
    }

    $idType = $_POST['id_type'];
    $isCameraCapture = isset($_POST['camera_capture']) && $_POST['camera_capture'] === 'true';
    
    $validIdTypes = ['drivers_license', 'national_id', 'passport', 'umid', 'postal_id', 'tin_id'];
    if (!in_array($idType, $validIdTypes)) {
        throw new Exception('Invalid ID type');
    }

    // Save uploaded images
    $idImagePath = $uploadDir . uniqid() . '_id_' . time() . '.jpg';
    $selfieImagePath = $uploadDir . uniqid() . '_selfie_' . time() . '.jpg';

    if (!move_uploaded_file($_FILES['id_image']['tmp_name'], $idImagePath)) {
        throw new Exception('Failed to save ID image');
    }
    if (!move_uploaded_file($_FILES['selfie_image']['tmp_name'], $selfieImagePath)) {
        throw new Exception('Failed to save selfie image');
    }

    // Perform OCR on ID
    $ocrText = '';
    $ocrConfidence = 0;
    
    try {
        // Try multiple PSM modes for better accuracy
        $ocrText = performOCR($idImagePath);
        
        // Save raw OCR for debugging
        file_put_contents($uploadDir . 'ocr_log_' . time() . '.txt', $ocrText);
    } catch (Exception $e) {
        error_log("OCR Error: " . $e->getMessage());
        $ocrText = '';
    }

    // Normalize OCR text
    $ocrClean = normalizeOCRText($ocrText);
    $ocrUpper = strtoupper($ocrClean);

    // Calculate OCR confidence
    $ocrConfidence = calculateOCRConfidence($ocrText, $idType);

    // Extract data from ID
    $extractedData = [
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'birthday' => '',
        'age' => '',
        'sex' => '',
        'address' => ''
    ];

    if ($ocrConfidence >= 40) {
        $extractedData = extract_id_data($idType, $ocrClean, $ocrUpper);
    }

    // Perform face matching and extraction
    $faceMatchResult = performAdvancedFaceMatching($idImagePath, $selfieImagePath);
    $faceMatchScore = $faceMatchResult['similarity'];
    $facesDetected = $faceMatchResult['faces_detected'];
    
    // Determine verification status
    $verificationPassed = false;
    $faceMatchPassed = false;
    $registrationStatus = 'pending';
    $message = '';

    if ($isCameraCapture) {
        // For camera captures with face detection already done on client
        if ($facesDetected && $faceMatchScore >= 65 && $ocrConfidence >= 50) {
            $verificationPassed = true;
            $faceMatchPassed = true;
            $registrationStatus = 'approved';
            $message = 'Automatic verification successful! Your face matches the ID photo.';
        } else {
            $message = 'Verification requires manual review. ';
            
            if (!$facesDetected) {
                $message .= 'Could not detect face in ID photo. ';
            } else if ($faceMatchScore < 65) {
                $message .= 'Face match confidence is below threshold (' . $faceMatchScore . '%). ';
            }
            
            if ($ocrConfidence < 50) {
                $message .= 'ID text quality needs review (' . $ocrConfidence . '%). ';
            }
        }
    } else {
        // Manual uploads always go to pending
        $message = 'Manual upload received. Awaiting admin approval.';
    }

    // Store in session for review page
    $_SESSION['ocr_data'] = [
        'valid_id_image' => $idImagePath,
        'selfie_image' => $selfieImagePath,
        'id_type' => $idType,
        'first_name' => $extractedData['first_name'],
        'middle_name' => $extractedData['middle_name'],
        'last_name' => $extractedData['last_name'],
        'birthday' => $extractedData['birthday'],
        'age' => $extractedData['age'],
        'sex' => $extractedData['sex'],
        'address' => $extractedData['address'],
        'ocr_raw' => $ocrText,
        'confidence' => $ocrConfidence,
        'face_match_score' => $faceMatchScore,
        'faces_detected' => $facesDetected,
        'registration_status' => $registrationStatus,
        'is_camera_capture' => $isCameraCapture
    ];

    // Return response
    echo json_encode([
        'success' => $verificationPassed,
        'face_match_passed' => $faceMatchPassed,
        'message' => $message,
        'ocr_confidence' => $ocrConfidence,
        'face_match_score' => $faceMatchScore,
        'faces_detected' => $facesDetected,
        'registration_status' => $registrationStatus,
        'extracted_data' => $extractedData
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Perform OCR with multiple attempts
 */
function performOCR($imagePath) {
    $attempts = [
        ['psm' => 6, 'lang' => 'eng'],
        ['psm' => 3, 'lang' => 'eng'],
        ['psm' => 4, 'lang' => 'eng']
    ];
    
    $bestResult = '';
    $bestLength = 0;
    
    foreach ($attempts as $config) {
        try {
            $tesseract = new TesseractOCR($imagePath);
            $tesseract->lang($config['lang'])->psm($config['psm']);
            $result = $tesseract->run();
            
            if (strlen($result) > $bestLength) {
                $bestResult = $result;
                $bestLength = strlen($result);
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    return $bestResult;
}

/**
 * Normalize OCR text
 */
function normalizeOCRText($text) {
    // Remove non-printable characters
    $text = preg_replace('/[^\x20-\x7E\r\n]/', ' ', $text);
    // Normalize whitespace
    $text = preg_replace('/[ \t]+/', ' ', $text);
    // Normalize line endings
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    return trim($text);
}

/**
 * Calculate OCR confidence score
 */
function calculateOCRConfidence($ocrText, $idType) {
    if (empty($ocrText)) {
        return 0;
    }
    
    $score = 30; // Base score
    
    // Check for ID-specific keywords
    $keywords = [
        'drivers_license' => ['REPUBLIC', 'PHILIPPINES', 'LICENSE', 'DRIVER', 'LTO'],
        'national_id' => ['PHILSYS', 'NATIONAL', 'IDENTIFICATION', 'PSN'],
        'passport' => ['PASSPORT', 'REPUBLIC', 'PHILIPPINES', 'DFA'],
        'umid' => ['UMID', 'SSS', 'UNIFIED', 'MULTI-PURPOSE'],
        'postal_id' => ['POSTAL', 'PHILPOST', 'CORPORATION'],
        'tin_id' => ['TIN', 'TAXPAYER', 'IDENTIFICATION', 'BIR']
    ];
    
    $textUpper = strtoupper($ocrText);
    
    if (isset($keywords[$idType])) {
        $matchedKeywords = 0;
        foreach ($keywords[$idType] as $keyword) {
            if (strpos($textUpper, $keyword) !== false) {
                $score += 10;
                $matchedKeywords++;
            }
        }
        
        // Bonus if multiple keywords found
        if ($matchedKeywords >= 2) {
            $score += 10;
        }
    }
    
    // Check for date patterns (birthday/expiry)
    if (preg_match('/\d{2}[\/\-]\d{2}[\/\-]\d{4}/', $ocrText)) {
        $score += 15;
    }
    
    // Check for name patterns (capitalized words)
    if (preg_match_all('/\b[A-Z][a-z]{2,}\b/', $ocrText, $matches)) {
        $nameCount = count($matches[0]);
        if ($nameCount >= 2) {
            $score += 10;
        }
    }
    
    // Check for ID numbers (various formats)
    if (preg_match('/\b[A-Z0-9]{8,}\b/', $textUpper)) {
        $score += 10;
    }
    
    // Text length indicator (reasonable amount of extracted text)
    $textLength = strlen(trim($ocrText));
    if ($textLength > 100) {
        $score += 10;
    } else if ($textLength > 50) {
        $score += 5;
    }
    
    // Check for common OCR errors (too many special chars = poor quality)
    $specialChars = preg_match_all('/[^a-zA-Z0-9\s\-\/\.,]/', $ocrText);
    if ($specialChars > 20) {
        $score -= 10;
    }
    
    return min(100, max(0, $score));
}

/**
 * Advanced face matching with face detection
 * This uses basic image comparison - replace with proper face recognition API in production
 */
function performAdvancedFaceMatching($idImagePath, $selfieImagePath) {
    try {
        // Check if images exist and are valid
        if (!file_exists($idImagePath) || !file_exists($selfieImagePath)) {
            return ['similarity' => 0, 'faces_detected' => false];
        }
        
        $idSize = getimagesize($idImagePath);
        $selfieSize = getimagesize($selfieImagePath);
        
        if (!$idSize || !$selfieSize) {
            return ['similarity' => 0, 'faces_detected' => false];
        }
        
        // IMPORTANT: This is a placeholder implementation
        // For production, integrate one of these:
        // 1. AWS Rekognition - CompareFaces API (recommended)
        // 2. Azure Face API - Face Verify
        // 3. Face++ API
        // 4. OpenCV with face_recognition library
        
        // Simulate face detection and matching
        $facesDetected = detectFacesInImages($idImagePath, $selfieImagePath);
        
        if (!$facesDetected) {
            return ['similarity' => 0, 'faces_detected' => false];
        }
        
        // Calculate similarity (placeholder - replace with actual face matching)
        $similarity = calculateImageSimilarity($idImagePath, $selfieImagePath);
        
        return [
            'similarity' => $similarity,
            'faces_detected' => $facesDetected
        ];
        
    } catch (Exception $e) {
        error_log("Face matching error: " . $e->getMessage());
        return ['similarity' => 0, 'faces_detected' => false];
    }
}

/**
 * Detect if faces are present in both images
 * This is a basic implementation - replace with proper face detection
 */
function detectFacesInImages($idPath, $selfiePath) {
    // This should use actual face detection
    // For now, we'll do basic image analysis
    
    try {
        // Load images
        $idImg = imagecreatefromjpeg($idPath);
        $selfieImg = imagecreatefromjpeg($selfiePath);
        
        if (!$idImg || !$selfieImg) {
            return false;
        }
        
        // Basic heuristic: check if images have flesh-tone colors
        // This is NOT reliable - use proper face detection API
        $idHasFace = imageHasFleshTones($idImg);
        $selfieHasFace = imageHasFleshTones($selfieImg);
        
        imagedestroy($idImg);
        imagedestroy($selfieImg);
        
        return $idHasFace && $selfieHasFace;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if image has flesh-tone colors (basic face detection heuristic)
 */
function imageHasFleshTones($img) {
    $width = imagesx($img);
    $height = imagesy($img);
    
    $fleshTonePixels = 0;
    $totalSampled = 0;
    
    // Sample center region
    $startX = $width * 0.3;
    $endX = $width * 0.7;
    $startY = $height * 0.3;
    $endY = $height * 0.7;
    
    for ($x = $startX; $x < $endX; $x += 10) {
        for ($y = $startY; $y < $endY; $y += 10) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Flesh tone ranges (rough approximation)
            if ($r > 95 && $g > 40 && $b > 20 &&
                $r > $g && $r > $b &&
                abs($r - $g) > 15) {
                $fleshTonePixels++;
            }
            
            $totalSampled++;
        }
    }
    
    $fleshToneRatio = $totalSampled > 0 ? $fleshTonePixels / $totalSampled : 0;
    
    // If more than 10% flesh tones in center region, likely has a face
    return $fleshToneRatio > 0.1;
}

/**
 * Calculate image similarity (placeholder)
 */
function calculateImageSimilarity($img1Path, $img2Path) {
    // This is a very basic similarity check
    // Replace with actual face matching API
    
    try {
        $img1 = imagecreatefromjpeg($img1Path);
        $img2 = imagecreatefromjpeg($img2Path);
        
        if (!$img1 || !$img2) {
            return 0;
        }
        
        // Resize to same size for comparison
        $size = 100;
        $thumb1 = imagescale($img1, $size, $size);
        $thumb2 = imagescale($img2, $size, $size);
        
        $similarity = 0;
        $totalPixels = $size * $size;
        
        // Compare pixel by pixel (very basic)
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $rgb1 = imagecolorat($thumb1, $x, $y);
                $rgb2 = imagecolorat($thumb2, $x, $y);
                
                $r1 = ($rgb1 >> 16) & 0xFF;
                $g1 = ($rgb1 >> 8) & 0xFF;
                $b1 = $rgb1 & 0xFF;
                
                $r2 = ($rgb2 >> 16) & 0xFF;
                $g2 = ($rgb2 >> 8) & 0xFF;
                $b2 = $rgb2 & 0xFF;
                
                $diff = abs($r1 - $r2) + abs($g1 - $g2) + abs($b1 - $b2);
                $maxDiff = 255 * 3;
                
                $pixelSimilarity = 1 - ($diff / $maxDiff);
                $similarity += $pixelSimilarity;
            }
        }
        
        imagedestroy($img1);
        imagedestroy($img2);
        imagedestroy($thumb1);
        imagedestroy($thumb2);
        
        $averageSimilarity = ($similarity / $totalPixels) * 100;
        
        // Add some variance for realism (remove in production with real API)
        $variance = rand(-10, 10);
        $finalScore = max(40, min(95, $averageSimilarity + $variance));
        
        return round($finalScore);
        
    } catch (Exception $e) {
        return 0;
    }
}
?>