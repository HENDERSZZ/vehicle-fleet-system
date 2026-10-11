<?php
ob_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../library/fpdf.php';

unset($_GET['p']);

$regno = $_GET['regno'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$statusLabel = !empty($status) ? ucfirst($status) : 'All';

function safeText($text)
{
    return iconv('UTF-8', 'windows-1252//IGNORE', (string) $text);
}

class PDF extends FPDF
{
    public $tableWidths;
    public $visibleHeaders;
    public $statusLabel;
    public $columnAligns;

    function Header()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, strtoupper($this->statusLabel) . ' Refueling Records', 0, 1, 'C');
        $this->Ln(3);

        global $regno, $date_from, $date_to;
        $filters = [];
        if (!empty($regno))
            $filters[] = "Vehicle: $regno";
        if (!empty($date_from))
            $filters[] = "From: $date_from";
        if (!empty($date_to))
            $filters[] = "To: $date_to";
        if ($filters) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, implode(' | ', $filters), 0, 1, 'C');
            $this->Ln(2);
        }

        $this->SetFont('Arial', 'B', 9);
        $totalWidth = array_sum($this->tableWidths) + 10;
        $this->SetX(($this->GetPageWidth() - $totalWidth) / 2);

        $this->Cell(10, 7, '#', 1, 0, 'C');
        foreach ($this->visibleHeaders as $i => $h) {
            $this->Cell($this->tableWidths[$i], 7, $h, 1, 0, 'C');
        }
        $this->Ln();
    }

    function Footer()
    {
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

    function GetMultiCellHeight($w, $h, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ')
                $sep = $i;
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                $i = ($sep == -1) ? ($i == $j ? $i + 1 : $sep + 1) : $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

// Access control
$accessClause = getRefuelAccessClause(); // ✅ Uses user’s session for scoping

// SQL query with filters
$sql = "SELECT r.regno, r.fuel, r.cost, r.odometer, r.station, r.remarks, r.date, s.label AS status
        FROM refuel r
        LEFT JOIN vehicles v ON r.regno = v.regno
        LEFT JOIN locations l ON v.location_id = l.id
        LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
        LEFT JOIN counties co ON v.county_id = co.id
        LEFT JOIN regions rg ON co.region_id = rg.id
        LEFT JOIN vehicle_statuses s ON v.status_id = s.id
        WHERE 1=1";

$params = [];
if (!empty($regno)) {
    $sql .= " AND TRIM(LOWER(r.regno)) = TRIM(LOWER(:regno))";
    $params['regno'] = strtolower(trim($regno));
}
if (!empty($date_from)) {
    $sql .= " AND r.date >= :date_from";
    $params['date_from'] = $date_from . ' 00:00:00';
}
if (!empty($date_to)) {
    $sql .= " AND r.date <= :date_to";
    $params['date_to'] = $date_to . ' 23:59:59';
}
if (!empty($status)) {
    $sql .= " AND LOWER(s.label) = LOWER(:status)";
    $params['status'] = $status;
}

// Apply access control
$sql .= " $accessClause ORDER BY r.date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Columns to show
$allHeaders = [
    'regno' => 'Registration No',
    'fuel' => 'Fuel (L)',
    'cost' => 'Cost (KES)',
    'odometer' => 'Odometer',
    'station' => 'Station',
    'remarks' => 'Remarks',
    'date' => 'Date',
    'status' => 'Status'
];

$widthMap = [
    'regno' => 26,
    'fuel' => 22,
    'cost' => 26,
    'odometer' => 25,
    'station' => 40,
    'remarks' => 50,
    'date' => 25,
    'status' => 20
];

$alignMap = [
    'fuel' => 'R',
    'cost' => 'R',
    'odometer' => 'R',
    'regno' => 'L',
    'station' => 'L',
    'remarks' => 'L',
    'date' => 'L',
    'status' => 'L'
];

// Detect non-empty columns
$nonEmpty = [];
foreach ($rows as $row) {
    foreach ($row as $key => $val) {
        if (trim((string) $val) !== '')
            $nonEmpty[$key] = true;
    }
}

$visibleHeaders = [];
$columnKeys = [];
$aligns = [];

foreach ($allHeaders as $key => $label) {
    if (!empty($nonEmpty[$key])) {
        $visibleHeaders[] = $label;
        $columnKeys[] = $key;
        $aligns[] = $alignMap[$key] ?? 'L';
    }
}

$columnWidths = array_map(fn($k) => $widthMap[$k], $columnKeys);

// PDF init
$pdf = new PDF('L');
$pdf->tableWidths = $columnWidths;
$pdf->visibleHeaders = $visibleHeaders;
$pdf->columnAligns = $aligns;
$pdf->statusLabel = $statusLabel;
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

// Loop rows
$counter = 1;
$totalCost = 0;
$fill = false;

if (empty($rows)) {
    $pdf->Cell(0, 10, 'No refueling records found for the selected filters.', 1, 1, 'C');
} else {
    foreach ($rows as $row) {
        $heights = [];
        $values = [];
        foreach ($columnKeys as $key) {
            $val = safeText($row[$key] ?? '');
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
            $align = $aligns[$i];
            $pdf->Rect($x, $yStart, $pdf->tableWidths[$i], $rowHeight);
            $pdf->MultiCell($pdf->tableWidths[$i], 6, $val, 0, $align);
            $pdf->SetXY($x + $pdf->tableWidths[$i], $yStart);
        }

        $pdf->SetXY($xStart, $yStart + $rowHeight);
        $counter++;
        $fill = !$fill;

        if (isset($row['cost']) && is_numeric($row['cost'])) {
            $totalCost += (float) $row['cost'];
        }
    }

    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 10, 'Total Refuel Cost: KES ' . number_format($totalCost, 2), 0, 1, 'C');
}

ob_end_clean();

// Dynamic filename
$filename_parts = [];

if (!empty($regno))
    $filename_parts[] = strtolower(str_replace(' ', '_', $regno));
if (!empty($status))
    $filename_parts[] = strtolower(str_replace(' ', '_', $status));
if (!empty($date_from) || !empty($date_to)) {
    $from = $date_from ?: 'start';
    $to = $date_to ?: 'end';
    $filename_parts[] = "{$from}_to_{$to}";
}
if (empty($filename_parts))
    $filename_parts[] = 'all';

$filename_parts[] = 'refuel';
$filename_parts[] = date('Ymd');
$filename = implode('_', $filename_parts) . '.pdf';

$pdf->Output('D', $filename);
exit;
