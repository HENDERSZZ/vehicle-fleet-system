<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../library/fpdf.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) die('Unauthorized access');

// 🔒 Lockdown context
$user = $_SESSION['user'];
$region_id = $user['region_id'] ?? null;
$county_id = $user['county_id'] ?? null;
$subcounty_id = $user['subcounty_id'] ?? null;
$location_id = $user['location_id'] ?? null;

unset($_GET['p']);

class PDF extends FPDF {
    public $tableWidths;
    public $visibleHeaders;
    public $statusLabel;

    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, strtoupper($this->statusLabel) . ' Vehicles', 0, 1, 'C');
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 9);
        $totalWidth = array_sum($this->tableWidths) + 10;
        $this->SetX(($this->GetPageWidth() - $totalWidth) / 2);
        $this->Cell(10, 7, '#', 1, 0, 'C');
        foreach ($this->visibleHeaders as $i => $h) {
            $this->Cell($this->tableWidths[$i], 7, $h, 1, 0, 'C');
        }
        $this->Ln();
    }

    function Footer() {
        $this->SetY(-40);
        $blockWidth = 70;
        $centerX = ($this->GetPageWidth() - $blockWidth) / 2;
        $this->SetFont('Arial', '', 10);
        $this->SetX($centerX);
        $this->Cell($blockWidth, 8, '_________________________', 0, 1, 'C');
        $this->SetX($centerX);
        $this->Cell($blockWidth, 6, 'Authorized Signature', 0, 1, 'C');
        $this->SetX($centerX);
        $this->Cell($blockWidth, 6, 'Name: ____________________', 0, 1, 'C');
        $this->SetX($centerX);
        $this->Cell($blockWidth, 6, 'Date: ' . date('Y-m-d'), 0, 1, 'C');
        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function GetMultiCellHeight($w, $h, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

// 🔍 Filters
$search = $_GET['search'] ?? '';
$status_id = $_GET['status_id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$filter_location_id = $_GET['location_id'] ?? '';
$filter_subcounty_id = $_GET['subcounty_id'] ?? '';
$filter_county_id = $_GET['county_id'] ?? '';

// Status label
$statusLabel = 'All';
if ($status_id) {
    $stmt = $pdo->prepare("SELECT label FROM vehicle_statuses WHERE id = ?");
    $stmt->execute([$status_id]);
    $statusLabel = $stmt->fetchColumn() ?: 'All';
}

// SQL base
$sql = "SELECT v.regno, v.make AS model, v.designation,
               r.name AS region, l.name AS location,
               c.name AS category, s.label AS status, v.date
        FROM vehicles v
        LEFT JOIN categories c ON v.category_id = c.id
        LEFT JOIN locations l ON v.location_id = l.id
        LEFT JOIN subcounties sc ON l.subcounty_id = sc.id
        LEFT JOIN counties co ON sc.county_id = co.id
        LEFT JOIN regions r ON co.region_id = r.id
        LEFT JOIN vehicle_statuses s ON v.status_id = s.id
        WHERE 1=1";

$params = [];
$isHQ = (int) $region_id === 9;

// 🔒 Lockdown filters (only if not HQ)
if (!$isHQ) {
    if ($location_id) {
        $sql .= " AND v.location_id = :lock_location_id";
        $params['lock_location_id'] = $location_id;
    } elseif ($subcounty_id) {
        $sql .= " AND l.subcounty_id = :lock_subcounty_id";
        $params['lock_subcounty_id'] = $subcounty_id;
    } elseif ($county_id) {
        $sql .= " AND sc.county_id = :lock_county_id";
        $params['lock_county_id'] = $county_id;
    } else {
        $sql .= " AND co.region_id = :lock_region_id";
        $params['lock_region_id'] = $region_id;
    }
}

// 📌 User-selected filters (always applied if present)
if ($filter_location_id) {
    $sql .= " AND v.location_id = :filter_location_id";
    $params['filter_location_id'] = $filter_location_id;
} elseif ($filter_subcounty_id) {
    $sql .= " AND l.subcounty_id = :filter_subcounty_id";
    $params['filter_subcounty_id'] = $filter_subcounty_id;
} elseif ($filter_county_id) {
    $sql .= " AND sc.county_id = :filter_county_id";
    $params['filter_county_id'] = $filter_county_id;
}

// 🔍 Other filters
if ($search) {
    $sql .= " AND (
        v.regno LIKE :search OR v.make LIKE :search OR v.designation LIKE :search OR
        r.name LIKE :search OR l.name LIKE :search
    )";
    $params['search'] = "%$search%";
}
if ($status_id) {
    $sql .= " AND s.id = :status_id";
    $params['status_id'] = $status_id;
}
if ($startDate && $endDate) {
    $sql .= " AND v.date BETWEEN :start AND :end";
    $params['start'] = $startDate;
    $params['end'] = $endDate;
}
$sql .= " ORDER BY v.date DESC";

// Query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Detect visible columns
$nonEmpty = [];
foreach ($rows as $row) {
    foreach ($row as $key => $val) {
        if (trim($val) !== '') $nonEmpty[$key] = true;
    }
}

$allHeaders = [
    'regno' => 'Registration No',
    'model' => 'Model',
    'designation' => 'Designation',
    'region' => 'Region',
    'location' => 'Location',
    'category' => 'Category',
    'status' => 'Status',
    'date' => 'Date'
];

$widthMap = [
    'regno' => 25,
    'model' => 28,
    'designation' => 30,
    'region' => 25,
    'location' => 30,
    'category' => 28,
    'status' => 20,
    'date' => 22
];

$visibleHeaders = [];
$columnKeys = [];
foreach ($allHeaders as $key => $label) {
    if (!empty($nonEmpty[$key])) {
        $visibleHeaders[] = $label;
        $columnKeys[] = $key;
    }
}
$columnWidths = array_map(fn($k) => $widthMap[$k], $columnKeys);

// PDF Init
$pdf = new PDF('L');
$pdf->tableWidths = array_values($columnWidths);
$pdf->visibleHeaders = $visibleHeaders;
$pdf->statusLabel = $statusLabel;
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);
$fill = false;
$counter = 1;

if (empty($rows)) {
    $pdf->Cell(0, 10, 'No vehicles found for the selected filters.', 1, 1, 'C');
} else {
    foreach ($rows as $row) {
        $heights = [];
        $values = [];
        foreach ($columnKeys as $key) {
            $val = iconv('UTF-8', 'windows-1252//IGNORE', $row[$key] ?: ' ');
            $values[] = $val;
            $heights[] = $pdf->GetMultiCellHeight($pdf->tableWidths[array_search($key, $columnKeys)], 6, $val);
        }

        $rowHeight = max($heights) * 6;
        $yStart = $pdf->GetY();
        $xStart = ($pdf->GetPageWidth() - array_sum($pdf->tableWidths) - 10) / 2;

        if ($yStart + $rowHeight > $pdf->GetPageHeight() - 45) {
            $pdf->AddPage();
            $yStart = $pdf->GetY();
        }

        $pdf->SetX($xStart);
        if ($fill) {
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Rect($xStart, $yStart, array_sum($pdf->tableWidths) + 10, $rowHeight, 'F');
        }

        $pdf->Rect($xStart, $yStart, 10, $rowHeight);
        $pdf->MultiCell(10, 6, $counter, 0, 'C');
        $pdf->SetXY($xStart + 10, $yStart);

        foreach ($values as $i => $val) {
            $x = $pdf->GetX();
            $pdf->Rect($x, $yStart, $pdf->tableWidths[$i], $rowHeight);
            $pdf->MultiCell($pdf->tableWidths[$i], 6, $val, 0, 'L');
            $pdf->SetXY($x + $pdf->tableWidths[$i], $yStart);
        }

        $pdf->SetXY($xStart, $yStart + $rowHeight);
        $fill = !$fill;
        $counter++;
    }
}

$filename = strtolower(str_replace(' ', '_', $statusLabel)) . '_vehicles_' . date('Ymd') . '.pdf';
$pdf->Output('D', $filename);
exit;
