<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;

$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Validation functions with stricter rules
function is_valid_name($name) {
    $name = trim($name);
    // Remove common OCR artifacts
    $name = preg_replace('/[0-9]/', '', $name);
    
    // Must be at least 2 characters
    if (strlen($name) < 2) return false;
    
    // Must contain only letters, spaces, hyphens, apostrophes
    if (!preg_match('/^[A-Za-z\s\-\']+$/', $name)) return false;
    
    // Must have at least 2 alphabetic characters
    if (strlen(preg_replace('/[^A-Za-z]/', '', $name)) < 2) return false;
    
    // Reject if it looks like garbage (too many consecutive consonants/vowels)
    if (preg_match('/[bcdfghjklmnpqrstvwxyz]{6,}/i', $name)) return false;
    
    // CRITICAL: Reject common form field labels that OCR might mistake for names
    $invalidNames = ['first', 'last', 'name', 'middle', 'firstname', 'lastname', 'middlename', 
                     'sex', 'male', 'female', 'address', 'birthday', 'date', 'birth'];
    if (in_array(strtolower($name), $invalidNames)) return false;
    
    // Reject single words that are too short (likely OCR errors)
    if (strlen($name) < 3 && !preg_match('/^[A-Z][a-z]$/', $name)) return false;
    
    return true;
}

function is_valid_date($date) {
    $date = trim($date);
    
    // Try YYYY-MM-DD or YYYY/MM/DD
    if (preg_match('/^(\d{4})[\/\-](\d{2})[\/\-](\d{2})$/', $date, $m)) {
        $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
    } elseif (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $date, $m2)) {
        // MM/DD/YYYY
        $y = (int)$m2[3]; $mo = (int)$m2[1]; $d = (int)$m2[2];
    } else {
        return false;
    }
    
    // Validate date components
    if ($y < 1900 || $y > date('Y')) return false;
    if ($mo < 1 || $mo > 12) return false;
    if ($d < 1 || $d > 31) return false;
    
    if (!checkdate($mo, $d, $y)) return false;
    
    $dt = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $y, $mo, $d));
    return ($dt ? $dt->format('Y-m-d') : false);
}

function compute_age_from_dob($yyyy_mm_dd) {
    try {
        $dob = new DateTime($yyyy_mm_dd);
        $today = new DateTime();
        return $today->diff($dob)->y;
    } catch (Exception $e) {
        return false;
    }
}

function is_valid_address($a) {
    $a = trim($a);
    
    // Must be at least 10 characters for a valid address
    if (strlen($a) < 10) return false;
    
    // Must contain at least one letter
    if (!preg_match('/[A-Za-z]/', $a)) return false;
    
    // Should contain common address indicators
    $hasAddressIndicators = preg_match('/(street|st|road|rd|avenue|ave|blvd|quezon|city|barangay|brgy)/i', $a);
    
    // Or should have commas (address separator)
    $hasCommas = strpos($a, ',') !== false;
    
    return $hasAddressIndicators || $hasCommas;
}

function calculate_confidence_score($ocrText) {
    $score = 0;
    
    // Check for key ID markers
    if (stripos($ocrText, 'DRIVER') !== false) $score += 20;
    if (stripos($ocrText, 'LICENSE') !== false) $score += 20;
    if (stripos($ocrText, 'PHILIPPINES') !== false) $score += 15;
    if (stripos($ocrText, 'REPUBLIC') !== false) $score += 10;
    if (preg_match('/\d{4}[\/\-]\d{2}[\/\-]\d{2}/', $ocrText)) $score += 20;
    if (preg_match('/[A-Z]+,\s*[A-Z]+/', $ocrText)) $score += 15;
    
    return $score;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['valid_id_image']) || !isset($_FILES['selfie_image'])) {
        die('Files not uploaded.');
    }

    $valid_id_image_path = $uploadDir . uniqid() . '_' . basename($_FILES['valid_id_image']['name']);
    $selfie_image_path   = $uploadDir . uniqid() . '_' . basename($_FILES['selfie_image']['name']);

    move_uploaded_file($_FILES['valid_id_image']['tmp_name'], $valid_id_image_path);
    move_uploaded_file($_FILES['selfie_image']['tmp_name'], $selfie_image_path);

    // Run OCR with multiple PSM modes for better results
    try {
        $ocrText = (new TesseractOCR($valid_id_image_path))
            ->lang('eng')
            ->psm(6)
            ->run();
    } catch (Exception $e) {
        // Fallback to PSM 3 if PSM 6 fails
        $ocrText = (new TesseractOCR($valid_id_image_path))
            ->lang('eng')
            ->psm(3)
            ->run();
    }

    // Save raw OCR for debugging
    file_put_contents($uploadDir . 'ocr_log.txt', $ocrText);

    // Normalize OCR text
    $ocrClean = preg_replace('/[^\x20-\x7E\r\n]/', ' ', $ocrText);
    $ocrClean = preg_replace('/[ \t]+/', ' ', $ocrClean);
    $ocrClean = preg_replace("/\r\n|\r/", "\n", $ocrClean);
    $ocrClean = trim($ocrClean);
    file_put_contents($uploadDir . 'ocr_clean_log.txt', $ocrClean);

    $ocrUpper = strtoupper($ocrClean);
    
    // Calculate confidence score
    $confidence = calculate_confidence_score($ocrText);
    $minConfidence = 50; // Minimum confidence threshold

    // Initialize all fields as empty
    $first_name = '';
    $middle_name = '';
    $last_name = '';
    $birthday = '';
    $sex = '';
    $address = '';
    $age = '';

    // Only extract if confidence is high enough
    if ($confidence >= $minConfidence) {
        
        // --- Name Extraction with Multiple Attempts ---
        $nameExtracted = false;
        
        // Pattern 1: "LASTNAME, FIRSTNAME MIDDLENAME"
        // Must have at least 3 words total to be considered valid (last, first, middle)
        if (!$nameExtracted && preg_match('/([A-Z][A-Z\-\']{2,}),\s*([A-Z][A-Z\-\']{2,})(?:\s+([A-Z][A-Z\-\']{2,}))?/i', $ocrClean, $m)) {
            $candidate_last = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\']/', '', $m[1]))));
            $candidate_first = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\']/', '', $m[2]))));
            $candidate_middle = isset($m[3]) ? ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\']/', '', $m[3])))) : '';

            // All name parts must pass validation
            $valid_first = is_valid_name($candidate_first) && strlen($candidate_first) >= 3;
            $valid_last = is_valid_name($candidate_last) && strlen($candidate_last) >= 3;
            $valid_middle = empty($candidate_middle) || (is_valid_name($candidate_middle) && strlen($candidate_middle) >= 3);

            if ($valid_first && $valid_last && $valid_middle) {
                $first_name = $candidate_first;
                $last_name = $candidate_last;
                $middle_name = $candidate_middle;
                $nameExtracted = true;
            }
        }
        
        // Pattern 2: Look for "Last Name" header
        if (!$nameExtracted && preg_match('/Last\s+Name[^\n]*\n([^\n]+)/i', $ocrClean, $m2)) {
            $line = trim($m2[1]);
            if (strpos($line, ',') !== false) {
                list($l, $rest) = array_map('trim', explode(',', $line, 2));
                $parts = preg_split('/\s+/', $rest);
                $first = $parts[0] ?? '';
                $middle = count($parts) > 1 ? $parts[1] : '';
                
                $candidate_last = ucwords(strtolower(preg_replace('/[^A-Za-z\-\s]/','',$l)));
                $candidate_first = ucwords(strtolower(preg_replace('/[^A-Za-z\-\s]/','',$first)));
                $candidate_middle = ucwords(strtolower(preg_replace('/[^A-Za-z\-\s]/','',$middle)));
                
                // Stricter validation - require minimum length
                $valid_first = is_valid_name($candidate_first) && strlen($candidate_first) >= 3;
                $valid_last = is_valid_name($candidate_last) && strlen($candidate_last) >= 3;
                $valid_middle = empty($candidate_middle) || (is_valid_name($candidate_middle) && strlen($candidate_middle) >= 3);
                
                if ($valid_first && $valid_last && $valid_middle) {
                    $first_name = $candidate_first;
                    $last_name = $candidate_last;
                    $middle_name = $candidate_middle;
                    $nameExtracted = true;
                }
            }
        }

        // --- Birthday extraction with validation ---
        $birthday_candidate = '';
        
        // Look for YYYY/MM/DD or YYYY-MM-DD format
        if (preg_match('/(\d{4})[\/\-](\d{2})[\/\-](\d{2})/', $ocrClean, $dateMatch)) {
            $birthday_candidate = $dateMatch[0];
        } elseif (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $ocrClean, $dateMatch2)) {
            $birthday_candidate = $dateMatch2[0];
        }
        
        if ($birthday_candidate) {
            $normalized = is_valid_date($birthday_candidate);
            if ($normalized !== false) {
                $computed_age = compute_age_from_dob($normalized);
                // Age must be reasonable (5-120 years)
                if ($computed_age !== false && $computed_age >= 5 && $computed_age <= 120) {
                    $birthday = $normalized;
                    $age = $computed_age;
                }
            }
        }

        // --- Sex extraction ---
        if (!empty($birthday_candidate)) {
            $pos = stripos($ocrUpper, str_replace(['/', '-'], ['/', '-'], $birthday_candidate));
            if ($pos !== false) {
                $lookBackStart = max(0, $pos - 80);
                $preWindow = substr($ocrUpper, $lookBackStart, min(80, $pos - $lookBackStart));
                
                // Look for standalone M or F
                if (preg_match('/\b(M|F)\b/', $preWindow, $sMatch)) {
                    $sex = ($sMatch[1] === 'M') ? 'Male' : 'Female';
                }
            }
        }
        
        // Fallback: look for "SEX" label
        if (empty($sex) && preg_match('/SEX[:\s]*([MF])\b/i', $ocrUpper, $s2)) {
            $sex = ($s2[1] === 'M') ? 'Male' : 'Female';
        }

        // --- Address extraction ---
        $addrRaw = '';
        
        // Look for QUEZON in address
        if (preg_match('/([^\n]{15,200}(?:QUEZON|AGDANGAN)[^\n]{0,50})/i', $ocrClean, $aMatch)) {
            $addrRaw = $aMatch[1];
        } elseif (preg_match('/Address[:\s]*([^\n]+)/i', $ocrClean, $aMatch2)) {
            $addrRaw = $aMatch2[1];
        }

        if (!empty($addrRaw)) {
            // Clean address
            $addrClean = preg_replace('/[^\w\s,\.\-]/', ' ', $addrRaw);
            $addrClean = preg_replace('/\s+/', ' ', $addrClean);
            $addrClean = trim($addrClean);
            
            if (is_valid_address($addrClean)) {
                $address = ucwords(strtolower($addrClean));
            }
        }
    }

    // Store in session
    $_SESSION['ocr_data'] = [
        'valid_id_image' => $valid_id_image_path,
        'selfie_image'   => $selfie_image_path,
        'first_name'     => $first_name,
        'middle_name'    => $middle_name,
        'last_name'      => $last_name,
        'birthday'       => $birthday,
        'age'            => $age,
        'sex'            => $sex,
        'address'        => $address,
        'ocr_raw'        => $ocrText,
        'confidence'     => $confidence
    ];

    header("Location: register_review.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Upload ID - LGU QuickAppoint</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="container mt-5">
  <h2>Step 1: Upload Your Valid ID and Selfie</h2>
  <div class="alert alert-info">
    <strong>Tips for best results:</strong>
    <ul>
      <li>Use good lighting when taking photos</li>
      <li>Ensure the ID is flat and in focus</li>
      <li>Avoid glare and shadows</li>
      <li>Capture the entire ID clearly</li>
    </ul>
  </div>
  <form method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <label for="valid_id_image">Upload Valid ID</label>
      <input type="file" name="valid_id_image" class="form-control-file" accept="image/*" required>
    </div>
    <div class="form-group">
      <label for="selfie_image">Upload Selfie</label>
      <input type="file" name="selfie_image" class="form-control-file" accept="image/*" required>
    </div>
    <button type="submit" class="btn btn-primary">Continue</button>
  </form>
</body>
</html>