<?php
/**
 * ID Extractors - Main dispatcher that loads the appropriate extractor
 */

// ============================================
// SHARED VALIDATION FUNCTIONS
// ============================================

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

// ============================================
// CONFIDENCE SCORING
// ============================================

function calculate_confidence_score($ocrText, $idType) {
    $score = 0;
    
    // ID-specific markers
    switch ($idType) {
        case 'drivers_license':
            if (stripos($ocrText, 'DRIVER') !== false) $score += 20;
            if (stripos($ocrText, 'LICENSE') !== false) $score += 20;
            break;
        case 'national_id':
            if (stripos($ocrText, 'PHILSYS') !== false) $score += 20;
            if (stripos($ocrText, 'NATIONAL') !== false) $score += 20;
            break;
        case 'passport':
            if (stripos($ocrText, 'PASSPORT') !== false) $score += 20;
            if (stripos($ocrText, 'REPUBLIC') !== false) $score += 10;
            break;
        case 'umid':
            if (stripos($ocrText, 'UMID') !== false) $score += 20;
            if (stripos($ocrText, 'SSS') !== false) $score += 10;
            break;
        case 'postal_id':
            if (stripos($ocrText, 'POSTAL') !== false) $score += 20;
            if (stripos($ocrText, 'PHLPOST') !== false) $score += 15;
            break;
        case 'tin_id':
            if (stripos($ocrText, 'TIN') !== false) $score += 20;
            if (stripos($ocrText, 'TAX') !== false) $score += 15;
            if (stripos($ocrText, 'BIR') !== false) $score += 15;
            break;
    }
    
    // Common markers
    if (stripos($ocrText, 'PHILIPPINES') !== false) $score += 15;
    if (stripos($ocrText, 'REPUBLIC') !== false) $score += 10;
    if (preg_match('/\d{4}[\/\-]\d{2}[\/\-]\d{2}/', $ocrText)) $score += 20;
    if (preg_match('/[A-Z]+,\s*[A-Z]+/', $ocrText)) $score += 15;
    
    return $score;
}

// ============================================
// MAIN DISPATCHER - Loads appropriate extractor
// ============================================

function extract_id_data($idType, $ocrClean, $ocrUpper) {
    $extractorFile = __DIR__ . '/extractors/' . $idType . '_extractor.php';
    
    if (!file_exists($extractorFile)) {
        // Return empty data if extractor doesn't exist
        return [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'sex' => '',
            'address' => '',
            'age' => ''
        ];
    }
    
    // Load the specific extractor
    require_once $extractorFile;
    
    // Call the extraction function
    $functionName = 'extract_data_' . $idType;
    if (function_exists($functionName)) {
        return $functionName($ocrClean, $ocrUpper);
    }
    
    // Return empty data if function doesn't exist
    return [
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'birthday' => '',
        'sex' => '',
        'address' => '',
        'age' => ''
    ];
}