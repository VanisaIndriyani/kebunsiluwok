<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Filters
$selected_afdeling = isset($_GET['afdeling']) ? $_GET['afdeling'] : '';
$selected_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$type = isset($_GET['type']) ? $_GET['type'] : 'pdf';

// Build Query
$where_clause = "WHERE t.jenis_transaksi = 'keluar' AND DATE_FORMAT(t.tanggal, '%Y-%m') = :bulan";
$params = [':bulan' => $selected_bulan];

$afdeling_name = "Semua Afdeling";

if ($selected_afdeling) {
    $where_clause .= " AND t.id_afdeling = :afdeling";
    $params[':afdeling'] = $selected_afdeling;
    
    // Get Afdeling Name
    $stmt_afd = $koneksi->prepare("SELECT nama_afdeling FROM afdeling WHERE id = ?");
    $stmt_afd->execute([$selected_afdeling]);
    $afdeling_name = $stmt_afd->fetchColumn();
}

$sql = "SELECT t.*, a.nama_afdeling, b.nama_barang, b.satuan 
        FROM transaksi_gudang t 
        JOIN afdeling a ON t.id_afdeling = a.id 
        JOIN barang b ON t.id_barang = b.id 
        $where_clause 
        ORDER BY t.tanggal ASC, t.id ASC";

$stmt = $koneksi->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$periode_text = date('F Y', strtotime($selected_bulan . '-01'));
$filename = 'Laporan_Pemakaian_' . $selected_bulan . '_' . ($selected_afdeling ? 'Afd_' . $selected_afdeling : 'All');

if ($type == 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Pemakaian Barang');

    // Header Info
    $sheet->setCellValue('A1', 'LAPORAN PEMAKAIAN BARANG');
    $sheet->setCellValue('A2', 'PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK');
    $sheet->setCellValue('A3', 'Periode: ' . $periode_text);
    $sheet->setCellValue('A4', 'Afdeling: ' . $afdeling_name);
    
    $sheet->mergeCells('A1:F1');
    $sheet->mergeCells('A2:F2');
    $sheet->mergeCells('A3:F3');
    $sheet->mergeCells('A4:F4');
    
    $sheet->getStyle('A1:A4')->getFont()->setBold(true);
    $sheet->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Table Header
    $headers = [
        'A6' => 'No',
        'B6' => 'Tanggal',
        'C6' => 'Afdeling',
        'D6' => 'Nama Barang',
        'E6' => 'Jumlah',
        'F6' => 'Keterangan'
    ];

    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }
    
    $sheet->getStyle('A6:F6')->getFont()->setBold(true);
    $sheet->getStyle('A6:F6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A6:F6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A6:F6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

    // Data
    $rowNum = 7;
    $no = 1;
    foreach ($data as $row) {
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, date('d/m/Y', strtotime($row['tanggal'])));
        $sheet->setCellValue('C' . $rowNum, $row['nama_afdeling']);
        $sheet->setCellValue('D' . $rowNum, $row['nama_barang']);
        $sheet->setCellValue('E' . $rowNum, $row['jumlah'] . ' ' . $row['satuan']);
        $sheet->setCellValue('F' . $rowNum, $row['keterangan']);
        $rowNum++;
    }

    // Border for data
    $lastRow = $rowNum - 1;
    if ($lastRow >= 7) {
        $sheet->getStyle('A7:F' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A7:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B7:B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Auto Size Columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} else {
    // PDF Export
    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
    
    $html = '
    <div style="text-align: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">LAPORAN PEMAKAIAN BARANG</h3>
        <h4 style="margin: 5px 0;">PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK</h4>
        <p style="margin: 0;">Periode: ' . $periode_text . '</p>
        <p style="margin: 0;">Afdeling: ' . $afdeling_name . '</p>
    </div>
    
    <table border="1" cellspacing="0" cellpadding="5" style="width: 100%; border-collapse: collapse; font-size: 10pt;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th width="5%">No</th>
                <th width="15%">Tanggal</th>
                <th width="20%">Afdeling</th>
                <th>Nama Barang</th>
                <th width="15%">Jumlah</th>
                <th width="20%">Keterangan</th>
            </tr>
        </thead>
        <tbody>';
    
    $no = 1;
    foreach ($data as $row) {
        $html .= '
            <tr>
                <td style="text-align: center;">' . $no++ . '</td>
                <td style="text-align: center;">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>
                <td>' . htmlspecialchars($row['nama_afdeling']) . '</td>
                <td>' . htmlspecialchars($row['nama_barang']) . '</td>
                <td style="text-align: center;">' . number_format($row['jumlah'], 0) . ' ' . htmlspecialchars($row['satuan']) . '</td>
                <td>' . htmlspecialchars($row['keterangan']) . '</td>
            </tr>';
    }
    
    if (count($data) == 0) {
        $html .= '<tr><td colspan="6" style="text-align: center;">Tidak ada data transaksi.</td></tr>';
    }

    $html .= '
        </tbody>
    </table>
    
    <div style="margin-top: 30px; text-align: right;">
        <p>Siluwok, ' . date('d F Y') . '</p>
        <p style="margin-top: 60px; margin-right: 30px;">(____________________)</p>
        <p style="margin-right: 40px;">Petugas Gudang</p>
    </div>
    ';

    $mpdf->WriteHTML($html);
    $mpdf->Output($filename . '.pdf', 'D');
    exit;
}
