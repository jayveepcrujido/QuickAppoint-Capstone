<?php
/**
 * Driver's License Extractor
 * Extracts data from Philippine Driver's License
 */

function extract_data_drivers_license($ocrClean, $ocrUpper) {
    $data = [
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'birthday' => '',
        'sex' => '',
        'address' => '',
        'age' => ''
    ];
    
    // ===================================
    // NAME EXTRACTION
    // ===================================
    $nameExtracted = false;
    
    // Pattern 1: "LASTNAME, FIRSTNAME MIDDLENAME"
    // This is the most common format in Philippine Driver's License
    if (!$nameExtracted && preg_match('/([A-Z][A-Z\-\']{2,}),\s*([A-Z][A-Z\-\']{2,})(?:\s+([A-Z][A-Z\-\']{2,}))?/i', $ocrClean, $m)) {
        $candidate_last = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\']/', '', $m[1]))));
        $candidate_first = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\']/', '', $m[2]))));
        $candidate_middle = isset($m[3]) ? ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\']/', '', $m[3])))) : '';

        $valid_first = is_valid_name($candidate_first) && strlen($candidate_first) >= 3;
        $valid_last = is_valid_name($candidate_last) && strlen($candidate_last) >= 3;
        $valid_middle = empty($candidate_middle) || (is_valid_name($candidate_middle) && strlen($candidate_middle) >= 3);

        if ($valid_first && $valid_last && $valid_middle) {
            $data['first_name'] = $candidate_first;
            $data['last_name'] = $candidate_last;
            $data['middle_name'] = $candidate_middle;
            $nameExtracted = true;
        }
    }
    
    // Pattern 2: Look for "Last Name" header followed by the actual name
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
            
            $valid_first = is_valid_name($candidate_first) && strlen($candidate_first) >= 3;
            $valid_last = is_valid_name($candidate_last) && strlen($candidate_last) >= 3;
            $valid_middle = empty($candidate_middle) || (is_valid_name($candidate_middle) && strlen($candidate_middle) >= 3);
            
            if ($valid_first && $valid_last && $valid_middle) {
                $data['first_name'] = $candidate_first;
                $data['last_name'] = $candidate_last;
                $data['middle_name'] = $candidate_middle;
                $nameExtracted = true;
            }
        }
    }

    // ===================================
    // BIRTHDAY EXTRACTION
    // ===================================
    $birthday_candidate = '';
    
    // Look for YYYY/MM/DD or YYYY-MM-DD format (most common in PH DL)
    if (preg_match('/(\d{4})[\/\-](\d{2})[\/\-](\d{2})/', $ocrClean, $dateMatch)) {
        $birthday_candidate = $dateMatch[0];
    } 
    // Fallback: MM/DD/YYYY format
    elseif (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $ocrClean, $dateMatch2)) {
        $birthday_candidate = $dateMatch2[0];
    }
    
    if ($birthday_candidate) {
        $normalized = is_valid_date($birthday_candidate);
        if ($normalized !== false) {
            $computed_age = compute_age_from_dob($normalized);
            // Age must be reasonable (driver's license age: 17-120 years)
            if ($computed_age !== false && $computed_age >= 17 && $computed_age <= 120) {
                $data['birthday'] = $normalized;
                $data['age'] = $computed_age;
            }
        }
    }

    // ===================================
    // SEX EXTRACTION
    // ===================================
    
    // Method 1: Look near the birthday (usually sex is before the birthday)
    if (!empty($birthday_candidate)) {
        $pos = stripos($ocrUpper, str_replace(['/', '-'], ['/', '-'], $birthday_candidate));
        if ($pos !== false) {
            $lookBackStart = max(0, $pos - 80);
            $preWindow = substr($ocrUpper, $lookBackStart, min(80, $pos - $lookBackStart));
            
            // Look for standalone M or F
            if (preg_match('/\b(M|F)\b/', $preWindow, $sMatch)) {
                $data['sex'] = ($sMatch[1] === 'M') ? 'Male' : 'Female';
            }
        }
    }
    
    // Method 2: Fallback - look for "SEX:" label
    if (empty($data['sex']) && preg_match('/SEX[:\s]*([MF])\b/i', $ocrUpper, $s2)) {
        $data['sex'] = ($s2[1] === 'M') ? 'Male' : 'Female';
    }

    // ===================================
    // ADDRESS EXTRACTION
    // ===================================
    $addrRaw = '';
    
    // Method 1: Look for specific locations (customize based on your area)
    if (preg_match('/([^\n]{15,200}(?:QUEZON|AGDANGAN|MANILA|CITY)[^\n]{0,50})/i', $ocrClean, $aMatch)) {
        $addrRaw = $aMatch[1];
    } 
    // Method 2: Look for "Address:" label
    elseif (preg_match('/Address[:\s]*([^\n]+)/i', $ocrClean, $aMatch2)) {
        $addrRaw = $aMatch2[1];
    }

    if (!empty($addrRaw)) {
        // Clean the address
        $addrClean = preg_replace('/[^\w\s,\.\-]/', ' ', $addrRaw);
        $addrClean = preg_replace('/\s+/', ' ', $addrClean);
        $addrClean = trim($addrClean);
        
        if (is_valid_address($addrClean)) {
            $data['address'] = ucwords(strtolower($addrClean));
        }
    }
    
    return $data;
}