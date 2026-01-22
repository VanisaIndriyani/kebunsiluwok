<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch Data Barang
$sql = "SELECT * FROM barang ORDER BY nama_barang ASC";
$stmt = $koneksi->query($sql);
$barang = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_items = count($barang);
$date_print = date('d F Y');

$type = isset($_GET['type']) ? $_GET['type'] : 'pdf';
$filename = 'Laporan_Sisa_Stok_' . date('Ymd');

if ($type == 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Sisa Stok');

    // Header Info
    $sheet->setCellValue('A1', 'LAPORAN SISA STOK BARANG');
    $sheet->setCellValue('A2', 'PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK');
    $sheet->setCellValue('A3', 'Tanggal Cetak: ' . $date_print);
    $sheet->setCellValue('A4', 'Total Item: ' . $total_items);

    $sheet->mergeCells('A1:F1');
    $sheet->mergeCells('A2:F2');
    $sheet->mergeCells('A3:F3');
    $sheet->mergeCells('A4:F4');

    $sheet->getStyle('A1:A4')->getFont()->setBold(true);
    $sheet->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Table Header
    $headers = [
        'A6' => 'No',
        'B6' => 'Kode Barang',
        'C6' => 'Nama Barang',
        'D6' => 'Kategori',
        'E6' => 'Satuan',
        'F6' => 'Stok Saat Ini'
    ];

    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }

    $sheet->getStyle('A6:F6')->getFont()->setBold(true);
    $sheet->getStyle('A6:F6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A6:F6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A6:F6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

    // Data
    $rowNum = 7;
    $no = 1;
    foreach ($barang as $row) {
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, $row['kode_barang']);
        $sheet->setCellValue('C' . $rowNum, $row['nama_barang']);
        $sheet->setCellValue('D' . $rowNum, $row['kategori']);
        $sheet->setCellValue('E' . $rowNum, $row['satuan']);
        $sheet->setCellValue('F' . $rowNum, $row['stok']);
        
        // Styling conditional for stock
        if ($row['stok'] <= 10) {
            $sheet->getStyle('F' . $rowNum)->getFont()->getColor()->setARGB('FFFF0000'); // Red if low stock
        }

        $rowNum++;
    }

    // Border for data
    $lastRow = $rowNum - 1;
    if ($lastRow >= 7) {
        $sheet->getStyle('A7:F' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A7:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B7:B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E7:E' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F7:F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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
        <h3 style="margin: 0;">LAPORAN SISA STOK BARANG</h3>
        <h4 style="margin: 5px 0;">PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK</h4>
        <p style="margin: 0;">Tanggal Cetak: ' . $date_print . '</p>
    </div>
    
    <table border="1" cellspacing="0" cellpadding="5" style="width: 100%; border-collapse: collapse; font-size: 10pt;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th width="5%">No</th>
                <th width="15%">Kode Barang</th>
                <th>Nama Barang</th>
                <th width="20%">Kategori</th>
                <th width="10%">Satuan</th>
                <th width="15%">Stok</th>
            </tr>
        </thead>
        <tbody>';
    
    $no = 1;
    foreach ($barang as $row) {
        $color = ($row['stok'] <= 10) ? 'color: red; font-weight: bold;' : '';
        $html .= '
            <tr>
                <td style="text-align: center;">' . $no++ . '</td>
                <td style="text-align: center;">' . htmlspecialchars($row['kode_barang']) . '</td>
                <td>' . htmlspecialchars($row['nama_barang']) . '</td>
                <td>' . htmlspecialchars($row['kategori']) . '</td>
                <td style="text-align: center;">' . htmlspecialchars($row['satuan']) . '</td>
                <td style="text-align: right; ' . $color . '">' . number_format($row['stok'], 2, ',', '.') . '</td>
            </tr>';
    }
    
    if (count($barang) == 0) {
        $html .= '<tr><td colspan="6" style="text-align: center;">Tidak ada data barang.</td></tr>';
    }

    $html .= '
        </tbody>
    </table>
    
    <div style="margin-top: 30px; text-align: right;">
        <p>Siluwok, ' . $date_print . '</p>
        <p style="margin-top: 60px; margin-right: 30px;">(____________________)</p>
        <p style="margin-right: 40px;">Petugas Gudang</p>
    </div>
    ';

    $mpdf->WriteHTML($html);
    $mpdf->Output($filename . '.pdf', 'D');
    exit;
}
