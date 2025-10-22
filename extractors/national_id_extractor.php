<?php
/**
 * National ID (PhilSys) Extractor
 * Extracts data from Philippine National ID
 */

function extract_data_national_id($ocrClean, $ocrUpper) {
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
    // National ID format: Given Name, Middle Name, Surname
    // TODO: Implement National ID specific name extraction
    
    // Pattern 1: Look for "Given Name" or "First Name"
    if (preg_match('/(?:Given|First)\s+Name[:\s]*([A-Z][A-Za-z\s\-\']+)/i', $ocrClean, $m)) {
        $candidate_first = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\s]/', '', $m[1]))));
        if (is_valid_name($candidate_first) && strlen($candidate_first) >= 3) {
            $data['first_name'] = $candidate_first;
        }
    }
    
    // Pattern 2: Look for "Surname" or "Last Name"
    if (preg_match('/(?:Surname|Last\s+Name)[:\s]*([A-Z][A-Za-z\s\-\']+)/i', $ocrClean, $m)) {
        $candidate_last = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\s]/', '', $m[1]))));
        if (is_valid_name($candidate_last) && strlen($candidate_last) >= 3) {
            $data['last_name'] = $candidate_last;
        }
    }
    
    // Pattern 3: Look for "Middle Name"
    if (preg_match('/Middle\s+Name[:\s]*([A-Z][A-Za-z\s\-\']+)/i', $ocrClean, $m)) {
        $candidate_middle = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\s]/', '', $m[1]))));
        if (is_valid_name($candidate_middle) && strlen($candidate_middle) >= 3) {
            $data['middle_name'] = $candidate_middle;
        }
    }

    // ===================================
    // BIRTHDAY EXTRACTION
    // ===================================
    $birthday_candidate = '';
    
    // Look for "Date of Birth" label
    if (preg_match('/(?:Date\s+of\s+Birth|Birth\s*date)[:\s]*(\d{4}[\/\-]\d{2}[\/\-]\d{2}|\d{2}[\/\-]\d{2}[\/\-]\d{4})/i', $ocrClean, $m)) {
        $birthday_candidate = $m[1];
    }
    // Fallback: any date pattern
    elseif (preg_match('/(\d{4})[\/\-](\d{2})[\/\-](\d{2})/', $ocrClean, $dateMatch)) {
        $birthday_candidate = $dateMatch[0];
    }
    
    if ($birthday_candidate) {
        $normalized = is_valid_date($birthday_candidate);
        if ($normalized !== false) {
            $computed_age = compute_age_from_dob($normalized);
            if ($computed_age !== false && $computed_age >= 5 && $computed_age <= 120) {
                $data['birthday'] = $normalized;
                $data['age'] = $computed_age;
            }
        }
    }

    // ===================================
    // SEX EXTRACTION
    // ===================================
    if (preg_match('/Sex[:\s]*(Male|Female|M|F)\b/i', $ocrUpper, $sMatch)) {
        $sex = strtoupper($sMatch[1]);
        if ($sex === 'M' || $sex === 'MALE') {
            $data['sex'] = 'Male';
        } elseif ($sex === 'F' || $sex === 'FEMALE') {
            $data['sex'] = 'Female';
        }
    }

    // ===================================
    // ADDRESS EXTRACTION
    // ===================================
    if (preg_match('/Address[:\s]*([^\n]{15,})/i', $ocrClean, $aMatch)) {
        $addrRaw = $aMatch[1];
        $addrClean = preg_replace('/[^\w\s,\.\-]/', ' ', $addrRaw);
        $addrClean = preg_replace('/\s+/', ' ', $addrClean);
        $addrClean = trim($addrClean);
        
        if (is_valid_address($addrClean)) {
            $data['address'] = ucwords(strtolower($addrClean));
        }
    }
    
    return $data;
}