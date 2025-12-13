<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    header("Location: ../login.php");
    exit();
}

require '../vendor/autoload.php';
include '../conn.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

$authId = $_SESSION['auth_id'];

// Get personnel's department
$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$authId]);
$personnel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personnel) {
    die("Personnel information not found.");
}

$departmentId = $personnel['department_id'];

// Get department name
$deptStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
$deptStmt->execute([$departmentId]);
$deptName = $deptStmt->fetchColumn();

// Get filters from GET parameters
$startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : null;

// Build the WHERE clause with filters - FILTERED BY DEPARTMENT
$whereClause = "WHERE a.department_id = ?";
$params = [$departmentId];

if ($startDate && $endDate) {
    $whereClause .= " AND DATE(af.submitted_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
} elseif ($startDate) {
    $whereClause .= " AND DATE(af.submitted_at) >= ?";
    $params[] = $startDate;
} elseif ($endDate) {
    $whereClause .= " AND DATE(af.submitted_at) <= ?";
    $params[] = $endDate;
}

// Fetch all feedbacks based on filters for this department only
$feedbackStmt = $pdo->prepare("
    SELECT 
        af.sqd0_answer,
        af.sqd1_answer,
        af.sqd2_answer,
        af.sqd3_answer,
        af.sqd4_answer,
        af.sqd5_answer,
        af.sqd6_answer,
        af.sqd7_answer,
        af.sqd8_answer,
        d.name as department_name
    FROM appointment_feedback af
    JOIN appointments a ON af.appointment_id = a.id
    JOIN departments d ON a.department_id = d.id
    $whereClause
    ORDER BY af.submitted_at DESC
");
$feedbackStmt->execute($params);
$feedbacks = $feedbackStmt->fetchAll(PDO::FETCH_ASSOC);

// Question definitions
$sqdQuestions = [
    'sqd0' => 'I am satisfied with the service that I availed.',
    'sqd1' => 'I spent a reasonable amount of time for my transaction.',
    'sqd2' => 'The office followed the transaction\'s requirements and steps.',
    'sqd3' => 'The steps I needed to do for my transaction were easy and simple.',
    'sqd4' => 'I easily found information about my transaction.',
    'sqd5' => 'I paid a reasonable amount of fees for my transaction.',
    'sqd6' => 'I am confident my online transaction was secure.',
    'sqd7' => 'The office\'s online support was available and quick.',
    'sqd8' => 'I got what I needed from the government office.'
];

$scoreMapping = [
    'Strongly Agree' => 5,
    'Agree' => 4,
    'Neither Agree nor Disagree' => 3,
    'Disagree' => 2,
    'Strongly Disagree' => 1
];

// Initialize statistics
$questionStats = [];
foreach ($sqdQuestions as $key => $question) {
    $questionStats[$key] = [
        'question' => $question,
        'responses' => [
            'Strongly Agree' => 0,
            'Agree' => 0,
            'Neither Agree nor Disagree' => 0,
            'Disagree' => 0,
            'Strongly Disagree' => 0
        ],
        'total' => 0,
        'scoreTotal' => 0,
        'scoreCount' => 0
    ];
}

// Calculate statistics
foreach ($feedbacks as $feedback) {
    foreach ($sqdQuestions as $key => $question) {
        $answer = $feedback[$key . '_answer'];
        
        if ($answer && $answer !== 'N/A' && $answer !== '') {
            $questionStats[$key]['total']++;
            
            if (isset($questionStats[$key]['responses'][$answer])) {
                $questionStats[$key]['responses'][$answer]++;
                
                if (isset($scoreMapping[$answer])) {
                    $questionStats[$key]['scoreTotal'] += $scoreMapping[$answer];
                    $questionStats[$key]['scoreCount']++;
                }
            }
        }
    }
}

// Calculate overall statistics
$totalScores = 0;
$totalCount = 0;
foreach ($questionStats as $stats) {
    $totalScores += $stats['scoreTotal'];
    $totalCount += $stats['scoreCount'];
}
$overallAverage = $totalCount > 0 ? round($totalScores / $totalCount, 2) : 0;
$overallSatisfaction = $totalCount > 0 ? round(($overallAverage / 5) * 100, 1) : 0;

// Create new Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator("Appointment System")
    ->setTitle("Feedback Analysis Report - " . $deptName)
    ->setSubject("Question-by-Question Analysis")
    ->setDescription("Detailed feedback analysis with response distribution for " . $deptName);

// Define styles
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 14
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0D92F4']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

$subHeaderStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '27548A']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

$summaryStyle = [
    'font' => [
        'bold' => true,
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E8F4FD']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

$questionHeaderStyle = [
    'font' => [
        'bold' => true,
        'size' => 11,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0D92F4']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ]
];

$dataStyle = [
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

$percentStyle = [
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'font' => [
        'bold' => true
    ]
];

// Set column widths
$sheet->getColumnDimension('A')->setWidth(50);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(15);

$row = 1;

// Title
$sheet->mergeCells("A{$row}:D{$row}");
$sheet->setCellValue("A{$row}", "FEEDBACK ANALYSIS REPORT");
$sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
$sheet->getRowDimension($row)->setRowHeight(30);
$row++;

// Department name
$sheet->mergeCells("A{$row}:D{$row}");
$sheet->setCellValue("A{$row}", "Department: " . $deptName);
$sheet->getStyle("A{$row}")->applyFromArray([
    'font' => ['bold' => true, 'size' => 12],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row++;

// Filter information
if ($startDate || $endDate) {
    $filterInfo = "Date Range: ";
    $filters = [];
    
    if ($startDate) $filters[] = "From: " . date('M d, Y', strtotime($startDate));
    if ($endDate) $filters[] = "To: " . date('M d, Y', strtotime($endDate));
    
    $sheet->mergeCells("A{$row}:D{$row}");
    $sheet->setCellValue("A{$row}", implode(" | ", $filters));
    $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;
}

// Generated date
$sheet->mergeCells("A{$row}:D{$row}");
$sheet->setCellValue("A{$row}", "Generated on: " . date('F d, Y g:i A'));
$sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9);
$sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;
$row++; // Empty row

// Overall Summary Section
$sheet->mergeCells("A{$row}:D{$row}");
$sheet->setCellValue("A{$row}", "OVERALL SUMMARY");
$sheet->getStyle("A{$row}")->applyFromArray($subHeaderStyle);
$sheet->getRowDimension($row)->setRowHeight(25);
$row++;

$summaryData = [
    ['Total Responses:', count($feedbacks)],
    ['Average Score:', $overallAverage . ' / 5.0'],
    ['Overall Satisfaction:', $overallSatisfaction . '%'],
    ['Questions Analyzed:', count($sqdQuestions)]
];

foreach ($summaryData as $data) {
    $sheet->setCellValue("A{$row}", $data[0]);
    $sheet->setCellValue("B{$row}", $data[1]);
    $sheet->getStyle("A{$row}:B{$row}")->applyFromArray($summaryStyle);
    $sheet->getStyle("A{$row}:B{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
}
$row++; // Empty row

// Question-by-Question Analysis
$sheet->mergeCells("A{$row}:D{$row}");
$sheet->setCellValue("A{$row}", "QUESTION-BY-QUESTION ANALYSIS");
$sheet->getStyle("A{$row}")->applyFromArray($subHeaderStyle);
$sheet->getRowDimension($row)->setRowHeight(25);
$row++;
$row++; // Empty row

foreach ($sqdQuestions as $key => $question) {
    $stats = $questionStats[$key];
    $avgScore = $stats['scoreCount'] > 0 ? round($stats['scoreTotal'] / $stats['scoreCount'], 2) : 0;
    $satisfaction = $stats['scoreCount'] > 0 ? round(($avgScore / 5) * 100, 1) : 0;
    
    // Question header with statistics
    $sheet->mergeCells("A{$row}:D{$row}");
    $questionText = strtoupper($key) . ": " . $question;
    $sheet->setCellValue("A{$row}", $questionText);
    $sheet->getStyle("A{$row}")->applyFromArray($questionHeaderStyle);
    $sheet->getRowDimension($row)->setRowHeight(35);
    $row++;
    
    // Statistics row
    $sheet->setCellValue("A{$row}", "Total Responses: " . $stats['total']);
    $sheet->setCellValue("B{$row}", "Avg Score: " . $avgScore . " / 5.0");
    $sheet->setCellValue("C{$row}", "Satisfaction: " . $satisfaction . "%");
    $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($summaryStyle);
    $sheet->getStyle("A{$row}:D{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    // Response distribution header
    $sheet->setCellValue("A{$row}", "Response");
    $sheet->setCellValue("B{$row}", "Count");
    $sheet->setCellValue("C{$row}", "Percentage");
    $sheet->setCellValue("D{$row}", "Visual");
    $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E8F4FD']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ]);
    $row++;
    
    // Response rows
    $responseOrder = [
        'Strongly Agree',
        'Agree',
        'Neither Agree nor Disagree',
        'Disagree',
        'Strongly Disagree'
    ];
    
    foreach ($responseOrder as $response) {
        $count = $stats['responses'][$response];
        $percentage = $stats['total'] > 0 ? round(($count / $stats['total']) * 100, 1) : 0;
        
        $sheet->setCellValue("A{$row}", $response);
        $sheet->setCellValue("B{$row}", $count);
        $sheet->setCellValue("C{$row}", $percentage . "%");
        
        // Visual representation
        $barLength = (int)($percentage / 5);
        $visualBar = str_repeat('█', $barLength);
        $sheet->setCellValue("D{$row}", $visualBar);
        
        // Color code based on response
        $fillColor = 'FFFFFF';
        switch ($response) {
            case 'Strongly Agree':
                $fillColor = 'D1FAE5';
                break;
            case 'Agree':
                $fillColor = 'BFDBFE';
                break;
            case 'Neither Agree nor Disagree':
                $fillColor = 'FEF3C7';
                break;
            case 'Disagree':
                $fillColor = 'FECACA';
                break;
            case 'Strongly Disagree':
                $fillColor = 'FEE2E2';
                break;
        }
        
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $fillColor]
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ]);
        
        $sheet->getStyle("C{$row}")->applyFromArray($percentStyle);
        
        $row++;
    }
    
    $row++; // Empty row between questions
}

// Add borders to all data
$lastRow = $row - 1;
$sheet->getStyle("A1:D{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Set print area and page setup
$sheet->getPageSetup()->setPrintArea("A1:D{$lastRow}");
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// Generate filename
$filename = "Feedback_Analysis_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $deptName) . "_" . date('Y-m-d_His');
if ($startDate || $endDate) {
    $filename .= "_Filtered";
}
$filename .= ".xlsx";

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Write file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>