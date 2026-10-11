<?php
ob_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../library/fpdf.php';
require_once __DIR__ . '/../../includes/functions.php';

unset($_GET['p']);

// === Get filters from query
$regno = $_GET['regno'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// === Access clause
$accessClause = getMaintenanceAccessClause();

$statusLabel = !empty($status) ? ucfirst($status) : 'All';

// === PDF class definition
class PDF extends FPDF
{
    public $tableWidths;
    public $visibleHeaders;
    public $statusLabel;

    function Header()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, strtoupper($this->statusLabel) . ' Maintenance Records', 0, 1, 'C');
        $this->Ln(3);

        global $regno, $date_from, $date_to;
        $filters = [];
        if (!empty($regno))
            $filters[] = "Vehicle: $regno";
        if (!empty($date_from))
            $filters[] = "From: $date_from";
        if (!empty($date_to))
            $filters[] = "To: $date_to";

        if (!empty($filters)) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, implode(' | ', $filters), 0, 1, 'C');
            $this->Ln(2);
        }

        $this->SetFont('Arial', 'B', 9);
        $totalWidth = array_sum($this->tableWidths) + 10;
        $this->SetX(($this->GetPageWidth() - $totalWidth) / 2);
        $this->Cell(10, 7, '#', 1, 0, 'C');

        foreach ($this->visibleHeaders as $i => $h) {
            $align = 'C';
            $this->Cell($this->tableWidths[$i], 7, $h, 1, 0, $align);
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

// === Build SQL query
$sql = "SELECT m.regno, m.type AS m_type, m.description, m.cost, m.odometer, m.date, s.label AS status
        FROM maintenance m
        LEFT JOIN vehicles v ON m.regno = v.regno
        LEFT JOIN vehicle_statuses s ON v.status_id = s.id
        LEFT JOIN locations l ON v.location_id = l.id
        LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
        LEFT JOIN counties co ON v.county_id = co.id
        LEFT JOIN regions r ON co.region_id = r.id
        WHERE 1=1";

$params = [];
if (!empty($regno)) {
    $sql .= " AND m.regno = :regno";
    $params['regno'] = $regno;
}
if (!empty($type)) {
    $sql .= " AND m.type = :type";
    $params['type'] = $type;
}
if (!empty($date_from)) {
    $sql .= " AND m.date >= :date_from";
    $params['date_from'] = $date_from . ' 00:00:00';
}
if (!empty($date_to)) {
    $sql .= " AND m.date <= :date_to";
    $params['date_to'] = $date_to . ' 23:59:59';
}
if (!empty($status)) {
    $sql .= " AND s.label = :status";
    $params['status'] = $status;
}

$sql .= " $accessClause ORDER BY m.date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Detect visible columns
$nonEmpty = [];
foreach ($rows as $row) {
    foreach ($row as $key => $val) {
        if (trim((string) $val) !== '')
            $nonEmpty[$key] = true;
    }
}

$allHeaders = [
    'regno' => 'Registration No',
    'm_type' => 'Type',
    'description' => 'Description',
    'cost' => 'Cost (KES)',
    'odometer' => 'Odometer',
    'date' => 'Date',
    'status' => 'Status'
];
$widthMap = [
    'regno' => 26,
    'm_type' => 25,
    'description' => 50,
    'cost' => 28,
    'odometer' => 25,
    'date' => 25,
    'status' => 20
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

// === Init PDF
$pdf = new PDF('L');
$pdf->tableWidths = array_values($columnWidths);
$pdf->visibleHeaders = $visibleHeaders;
$pdf->statusLabel = $statusLabel;
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

$counter = 1;
$totalCost = 0;
$fill = false;

if (empty($rows)) {
    $pdf->Cell(0, 10, 'No maintenance records found for the selected filters.', 1, 1, 'C');
} else {
    foreach ($rows as $row) {
        $heights = [];
        $values = [];
        foreach ($columnKeys as $key) {
            $val = iconv('UTF-8', 'windows-1252//IGNORE', (string) ($row[$key] ?? ''));
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
        $counter++;
        $fill = !$fill;

        if (isset($row['cost']) && is_numeric($row['cost'])) {
            $totalCost += (float) $row['cost'];
        }
    }

    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 10, 'Total Maintenance Cost: KES ' . number_format($totalCost, 2), 0, 1, 'C');
}

// === Build filename
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

$filename_parts[] = 'maintenance';
$filename_parts[] = date('Ymd');
$filename = implode('_', $filename_parts) . '.pdf';

ob_end_clean();
$pdf->Output('D', $filename);
exit;
