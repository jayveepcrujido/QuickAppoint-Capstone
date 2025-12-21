<?php
// validate_id.php - Validates uploaded ID against selected ID type using Tesseract OCR

class IDValidator {
    private $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'; // Adjust path as needed
    
    // Keywords for each ID type
    private $idKeywords = [
        'National ID (Card type)' => [
            'required' => ['philippine identification', 'philsys', 'pcn', 'psa'],
            'optional' => ['national id', 'republic', 'philippines']
        ],
        'National ID (Paper type) / Digital National ID / ePhilID' => [
            'required' => ['philippine identification', 'philsys', 'pcn'],
            'optional' => ['national id', 'psa', 'republic']
        ],
        'Passport' => [
            'required' => ['passport', 'republic of the philippines', 'dfa'],
            'optional' => ['passport no', 'surname', 'given name']
        ],
        'HDMF (Pag-IBIG Loyalty Plus) ID' => [
            'required' => ['pag-ibig', 'hdmf'],
            'optional' => ['loyalty', 'fund', 'member']
        ],
        "Driver's License (including BLTO Driver's License)" => [
            'required' => ['driver', 'license', 'lto'],
            'optional' => ['land transportation', 'restriction', 'dl no']
        ],
        'Philippine Postal ID' => [
            'required' => ['postal', 'phlpost'],
            'optional' => ['philippine postal', 'corporation', 'id']
        ],
        'PRC ID (Professional Regulation Commission ID)' => [
            'required' => ['prc', 'professional regulation'],
            'optional' => ['commission', 'license', 'professional']
        ],
        'UMID (Unified Multi-Purpose ID)' => [
            'required' => ['umid', 'unified'],
            'optional' => ['multi-purpose', 'sss', 'gsis', 'philhealth']
        ],
        'SSS ID' => [
            'required' => ['sss', 'social security'],
            'optional' => ['system', 'member', 'ss no']
        ]
    ];
    
    public function extractTextFromImage($imagePath) {
        try {
            // Check if Tesseract executable exists
            if (!file_exists($this->tesseractPath)) {
                throw new Exception('Tesseract OCR not found at: ' . $this->tesseractPath);
            }
            
            // Check if image file exists
            if (!file_exists($imagePath)) {
                throw new Exception('Image file not found: ' . $imagePath);
            }
            
            // Prepare temporary file for tesseract output
            $outputFile = sys_get_temp_dir() . '/' . uniqid('ocr_');
            
            // Escape the paths for command line
            $escapedImagePath = escapeshellarg($imagePath);
            $escapedOutputFile = escapeshellarg($outputFile);
            $escapedTesseract = escapeshellarg($this->tesseractPath);
            
            // Run Tesseract OCR
            $command = "$escapedTesseract $escapedImagePath $escapedOutputFile";
            exec($command . ' 2>&1', $output, $returnCode);
            
            // Read the output file
            $textFile = $outputFile . '.txt';
            if (file_exists($textFile)) {
                $extractedText = file_get_contents($textFile);
                unlink($textFile); // Clean up
                return $extractedText;
            }
            
            throw new Exception('Failed to extract text from image. Tesseract output: ' . implode("\n", $output));
        } catch (Exception $e) {
            throw new Exception('OCR Error: ' . $e->getMessage());
        }
    }
    
    public function validateID($imagePath, $selectedIDType) {
        try {
            // Extract text from image
            $extractedText = $this->extractTextFromImage($imagePath);
            
            if (empty($extractedText)) {
                return [
                    'valid' => false,
                    'score' => 0,
                    'message' => 'No text could be extracted from the image. Please ensure the image is clear and readable.',
                    'extracted_text' => ''
                ];
            }
            
            // Convert to lowercase for case-insensitive matching
            $extractedText = strtolower($extractedText);
            
            // Get keywords for selected ID type
            if (!isset($this->idKeywords[$selectedIDType])) {
                return [
                    'valid' => false,
                    'score' => 0,
                    'message' => 'Invalid ID type selected.',
                    'extracted_text' => $extractedText
                ];
            }
            
            $keywords = $this->idKeywords[$selectedIDType];
            $requiredKeywords = $keywords['required'];
            $optionalKeywords = $keywords['optional'];
            
            // Score calculation
            $requiredMatches = 0;
            $optionalMatches = 0;
            $matchedKeywords = [];
            
            // Check required keywords
            foreach ($requiredKeywords as $keyword) {
                if (strpos($extractedText, strtolower($keyword)) !== false) {
                    $requiredMatches++;
                    $matchedKeywords[] = $keyword;
                }
            }
            
            // Check optional keywords
            foreach ($optionalKeywords as $keyword) {
                if (strpos($extractedText, strtolower($keyword)) !== false) {
                    $optionalMatches++;
                    $matchedKeywords[] = $keyword;
                }
            }
            
            // Calculate percentage score
            $totalRequired = count($requiredKeywords);
            $requiredScore = ($totalRequired > 0) ? ($requiredMatches / $totalRequired) * 70 : 0;
            $optionalScore = (count($optionalKeywords) > 0) ? ($optionalMatches / count($optionalKeywords)) * 30 : 0;
            $totalScore = $requiredScore + $optionalScore;
            
            // Validation threshold: at least 50% match required
            $isValid = $totalScore >= 50;
            
            $message = $isValid 
                ? "ID validation successful! Match score: " . round($totalScore, 2) . "%"
                : "The uploaded ID doesn't match the selected ID type. Please upload the correct ID. Match score: " . round($totalScore, 2) . "%";
            
            return [
                'valid' => $isValid,
                'score' => round($totalScore, 2),
                'message' => $message,
                'extracted_text' => $extractedText,
                'matched_keywords' => $matchedKeywords,
                'required_matches' => $requiredMatches,
                'total_required' => $totalRequired
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'score' => 0,
                'message' => $e->getMessage(),
                'extracted_text' => ''
            ];
        }
    }
}
?>