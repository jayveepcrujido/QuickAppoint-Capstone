<?php
/**
 * Postal ID Extractor
 * Extracts data from Philippine Postal ID
 */

function extract_data_postal_id($ocrClean, $ocrUpper) {
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
    
    // Method 1: Comma-separated format "LASTNAME, FIRSTNAME MIDDLENAME"
    // This is common in Postal IDs
    if (preg_match('/([A-Z][A-Z\-\']{2,}),\s*([A-Z][A-Z\-\']{2,})(?:\s+([A-Z][A-Z\-\']{2,}))?/i', $ocrClean, $m)) {
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
        }
    }
    
    // Method 2: Look for labeled fields
    
    // Last Name
    if (empty($data['last_name']) && preg_match('/(?:Last\s+Name|Surname)[:\s]*([A-Z][A-Za-z\s\-\']+)/i', $ocrClean, $m)) {
        $candidate = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\s]/', '', $m[1]))));
        if (is_valid_name($candidate) && strlen($candidate) >= 3) {
            $data['last_name'] = $candidate;
        }
    }
    
    // First Name
    if (empty($data['first_name']) && preg_match('/(?:First\s+Name|Given\s+Name)[:\s]*([A-Z][A-Za-z\s\-\']+)/i', $ocrClean, $m)) {
        $candidate = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\s]/', '', $m[1]))));
        if (is_valid_name($candidate) && strlen($candidate) >= 3) {
            $data['first_name'] = $candidate;
        }
    }
    
    // Middle Name
    if (empty($data['middle_name']) && preg_match('/Middle\s+Name[:\s]*([A-Z][A-Za-z\s\-\']+)/i', $ocrClean, $m)) {
        $candidate = ucwords(strtolower(trim(preg_replace('/[^A-Za-z\-\s]/', '', $m[1]))));
        if (is_valid_name($candidate) && strlen($candidate) >= 3) {
            $data['middle_name'] = $candidate;
        }
    }
    
    // Method 3: Look for "Name" with full name on next line
    if (empty($data['first_name']) && empty($data['last_name'])) {
        if (preg_match('/Name[:\s]*\n?([A-Z][A-Z\s\-\']{5,})/i', $ocrClean, $m)) {
            $fullName = trim($m[1]);
            // Try to split into parts
            if (strpos($fullName, ',') !== false) {
                list($last, $rest) = array_map('trim', explode(',', $fullName, 2));
                $parts = preg_split('/\s+/', $rest);
                
                $candidate_last = ucwords(strtolower(preg_replace('/[^A-Za-z\-\s]/', '', $last)));
                $candidate_first = isset($parts[0]) ? ucwords(strtolower(preg_replace('/[^A-Za-z\-\s]/', '', $parts[0]))) : '';
                $candidate_middle = isset($parts[1]) ? ucwords(strtolower(preg_replace('/[^A-Za-z\-\s]/', '', $parts[1]))) : '';
                
                if (is_valid_name($candidate_last) && strlen($candidate_last) >= 3) {
                    $data['last_name'] = $candidate_last;
                }
                if (is_valid_name($candidate_first) && strlen($candidate_first) >= 3) {
                    $data['first_name'] = $candidate_first;
                }
                if (!empty($candidate_middle) && is_valid_name($candidate_middle) && strlen($candidate_middle) >= 3) {
                    $data['middle_name'] = $candidate_middle;
                }
            }
        }
    }
    
    // ===================================
    // BIRTHDAY EXTRACTION
    // ===================================
    
    // Method 1: Look for "Date of Birth", "Birthday", or "Birth Date" label
    if (preg_match('/(?:Date\s+of\s+Birth|Birth\s*date|Birthday)[:\s]*(\d{4}[\/\-]\d{2}[\/\-]\d{2}|\d{2}[\/\-]\d{2}[\/\-]\d{4})/i', $ocrClean, $m)) {
        $normalized = is_valid_date($m[1]);
        if ($normalized !== false) {
            $computed_age = compute_age_from_dob($normalized);
            if ($computed_age !== false && $computed_age >= 5 && $computed_age <= 120) {
                $data['birthday'] = $normalized;
                $data['age'] = $computed_age;
            }
        }
    }
    
    // Method 2: Look for YYYY-MM-DD or YYYY/MM/DD format
    if (empty($data['birthday']) && preg_match('/(\d{4})[\/\-](\d{2})[\/\-](\d{2})/', $ocrClean, $dateMatch)) {
        $normalized = is_valid_date($dateMatch[0]);
        if ($normalized !== false) {
            $computed_age = compute_age_from_dob($normalized);
            if ($computed_age !== false && $computed_age >= 5 && $computed_age <= 120) {
                $data['birthday'] = $normalized;
                $data['age'] = $computed_age;
            }
        }
    }
    
    // Method 3: Look for MM/DD/YYYY format
    if (empty($data['birthday']) && preg_match('/\b(\d{2})[\/\-](\d{2})[\/\-](\d{4})\b/', $ocrClean, $dateMatch2)) {
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
    
    // Method 1: Look for "Sex" or "Gender" label with full word
    if (preg_match('/(?:Sex|Gender)[:\s]*(Male|Female)\b/i', $ocrClean, $sMatch)) {
        $data['sex'] = ucfirst(strtolower($sMatch[1]));
    }
    
    // Method 2: Look for "Sex" label with M/F
    if (empty($data['sex']) && preg_match('/(?:Sex|Gender)[:\s]*([MF])\b/i', $ocrUpper, $sMatch2)) {
        $data['sex'] = ($sMatch2[1] === 'M') ? 'Male' : 'Female';
    }
    
    // Method 3: Look near birthday for M or F
    if (empty($data['sex']) && !empty($data['birthday'])) {
        $pos = stripos($ocrUpper, str_replace(['/', '-'], ['/', '-'], $data['birthday']));
        if ($pos !== false) {
            $lookBackStart = max(0, $pos - 80);
            $preWindow = substr($ocrUpper, $lookBackStart, min(80, $pos - $lookBackStart));
            
            if (preg_match('/\b(M|F)\b/', $preWindow, $sMatch3)) {
                $data['sex'] = ($sMatch3[1] === 'M') ? 'Male' : 'Female';
            }
        }
    }
    
    // ===================================
    // ADDRESS EXTRACTION
    // ===================================
    
    // Method 1: Look for "Address" label
    if (preg_match('/Address[:\s]*([^\n]{15,})/i', $ocrClean, $aMatch)) {
        $addrRaw = $aMatch[1];
        $addrClean = preg_replace('/[^\w\s,\.\-]/', ' ', $addrRaw);
        $addrClean = preg_replace('/\s+/', ' ', $addrClean);
        $addrClean = trim($addrClean);
        
        if (is_valid_address($addrClean)) {
            $data['address'] = ucwords(strtolower($addrClean));
        }
    }
    
    // Method 2: Look for common address patterns with city/province names
    if (empty($data['address']) && preg_match('/([^\n]{15,200}(?:QUEZON|MANILA|CITY|BARANGAY|BRGY|PROVINCE)[^\n]{0,50})/i', $ocrClean, $aMatch2)) {
        $addrRaw = $aMatch2[1];
        $addrClean = preg_replace('/[^\w\s,\.\-]/', ' ', $addrRaw);
        $addrClean = preg_replace('/\s+/', ' ', $addrClean);
        $addrClean = trim($addrClean);
        
        if (is_valid_address($addrClean)) {
            $data['address'] = ucwords(strtolower($addrClean));
        }
    }
    
    // Method 3: Look for lines with commas (typical address format)
    if (empty($data['address'])) {
        if (preg_match('/([A-Za-z0-9\s\-,\.]{20,}(?:,\s*[A-Za-z\s]+){2,})/i', $ocrClean, $aMatch3)) {
            $addrRaw = $aMatch3[1];
            $addrClean = preg_replace('/[^\w\s,\.\-]/', ' ', $addrRaw);
            $addrClean = preg_replace('/\s+/', ' ', $addrClean);
            $addrClean = trim($addrClean);
            
            if (is_valid_address($addrClean)) {
                $data['address'] = ucwords(strtolower($addrClean));
            }
        }
    }
    
    return $data;
}