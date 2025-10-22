<?php
/**
 * Philippine Passport Extractor
 * Extracts data from Philippine Passport
 */

function extract_data_passport($ocrClean, $ocrUpper) {
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
    // NAME EXTRACTION FROM MRZ
    // ===================================
    // Passport uses MRZ (Machine Readable Zone)
    // Format: P<PHLSURNAME<<GIVENNAME<MIDDLENAME<<<<<<<<<<<<
    
    // Look for MRZ line
    if (preg_match('/P<PHL([A-Z<]+)<<([A-Z<]+)/i', $ocrUpper, $m)) {
        // Extract surname
        $surname = str_replace('<', ' ', trim($m[1]));
        $surname = trim($surname);
        if (is_valid_name($surname) && strlen($surname) >= 3) {
            $data['last_name'] = ucwords(strtolower($surname));
        }
        
        // Extract given names
        $givenNames = str_replace('<', ' ', trim($m[2]));
        $givenNames = trim($givenNames);
        $nameParts = preg_split('/\s+/', $givenNames);
        
        if (count($nameParts) >= 1 && !empty($nameParts[0])) {
            $firstName = trim($nameParts[0]);
            if (is_valid_name($firstName) && strlen($firstName) >= 3) {
                $data['first_name'] = ucwords(strtolower($firstName));
            }
        }
        
        if (count($nameParts) >= 2 && !empty($nameParts[1])) {
            $middleName = trim($nameParts[1]);
            if (is_valid_name($middleName) && strlen($middleName) >= 3) {
                $data['middle_name'] = ucwords(strtolower($middleName));
            }
        }
    }
    
    // ===================================
    // NAME EXTRACTION FROM DATA PAGE
    // ===================================
    
    // Look for "Surname" in passport data page
    if (empty($data['last_name']) && preg_match('/Surname[:\s]*([A-Z][A-Za-z\s\-\']+)/i', $ocrClean, $m)) {
        $candidate = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\s]/', '', $m[1]))));
        if (is_valid_name($candidate) && strlen($candidate) >= 3) {
            $data['last_name'] = $candidate;
        }
    }
    
    // Look for "Given Names"
    if (empty($data['first_name']) && preg_match('/Given\s+Names?[:\s]*([A-Z][A-Za-z\s\-\']+)/i', $ocrClean, $m)) {
        $names = trim($m[1]);
        $nameParts = preg_split('/\s+/', $names);
        
        if (count($nameParts) >= 1) {
            $firstName = ucwords(strtolower(preg_replace('/[^A-Za-z\-\s]/', '', $nameParts[0])));
            if (is_valid_name($firstName) && strlen($firstName) >= 3) {
                $data['first_name'] = $firstName;
            }
        }
        
        if (count($nameParts) >= 2) {
            $middleName = ucwords(strtolower(preg_replace('/[^A-Za-z\-\s]/', '', $nameParts[1])));
            if (is_valid_name($middleName) && strlen($middleName) >= 3) {
                $data['middle_name'] = $middleName;
            }
        }
    }
    
    // ===================================
    // BIRTHDAY EXTRACTION FROM MRZ
    // ===================================
    // Birthday from MRZ (YYMMDD format at specific position)
    if (preg_match('/P<PHL[A-Z<]+\n[A-Z0-9<]{9}(\d{6})/i', $ocrUpper, $dateMatch)) {
        $yymmdd = $dateMatch[1];
        $yy = substr($yymmdd, 0, 2);
        $mm = substr($yymmdd, 2, 2);
        $dd = substr($yymmdd, 4, 2);
        
        // Assume 20xx for years 00-30, 19xx for 31-99
        $yyyy = ((int)$yy <= 30) ? "20" . $yy : "19" . $yy;
        
        $birthday_candidate = "$yyyy-$mm-$dd";
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
    // BIRTHDAY EXTRACTION FROM DATA PAGE
    // ===================================
    // Fallback: Look for "Date of Birth" label
    if (empty($data['birthday']) && preg_match('/(?:Date\s+of\s+Birth|Birth\s*date)[:\s]*(\d{2}[\/\-]\d{2}[\/\-]\d{4}|\d{4}[\/\-]\d{2}[\/\-]\d{2})/i', $ocrClean, $m)) {
        $normalized = is_valid_date($m[1]);
        if ($normalized !== false) {
            $computed_age = compute_age_from_dob($normalized);
            if ($computed_age !== false && $computed_age >= 5 && $computed_age <= 120) {
                $data['birthday'] = $normalized;
                $data['age'] = $computed_age;
            }
        }
    }
    
    // Additional fallback: any date pattern
    if (empty($data['birthday']) && preg_match('/(\d{4})[\/\-](\d{2})[\/\-](\d{2})/', $ocrClean, $dateMatch2)) {
        $normalized = is_valid_date($dateMatch2[0]);
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
    // Sex from MRZ (M/F at specific position)
    if (preg_match('/[<\s]([MF])[<\s]/', $ocrUpper, $sMatch)) {
        $data['sex'] = ($sMatch[1] === 'M') ? 'Male' : 'Female';
    }
    
    // Fallback: Look for "Sex" label
    if (empty($data['sex']) && preg_match('/Sex[:\s]*(Male|Female|M|F)\b/i', $ocrUpper, $s2)) {
        $sex = strtoupper($s2[1]);
        $data['sex'] = (in_array($sex, ['M', 'MALE'])) ? 'Male' : 'Female';
    }
    
    // ===================================
    // ADDRESS EXTRACTION
    // ===================================
    // Passports typically don't have addresses on the data page
    // But we'll try to extract if available
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