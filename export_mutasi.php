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

// Handle Filter Tanggal
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$type = isset($_GET['type']) ? $_GET['type'] : 'pdf';

// Query Data (Sama seperti di laporan_mutasi.php)
$sql = "SELECT 
            b.id, b.kode_barang, b.nama_barang, b.satuan, b.kategori,
            COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'masuk' AND t.tanggal < :start_date THEN t.jumlah ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'keluar' AND t.tanggal < :start_date THEN t.jumlah ELSE 0 END), 0) as stok_awal,
            COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'masuk' AND t.tanggal BETWEEN :start_date AND :end_date THEN t.jumlah ELSE 0 END), 0) as masuk,
            COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'keluar' AND t.tanggal BETWEEN :start_date AND :end_date THEN t.jumlah ELSE 0 END), 0) as keluar
        FROM barang b
        LEFT JOIN transaksi_gudang t ON b.id = t.id_barang
        GROUP BY b.id, b.kode_barang, b.nama_barang, b.satuan, b.kategori
        ORDER BY b.nama_barang ASC";

$stmt = $koneksi->prepare($sql);
$stmt->execute([
    ':start_date' => $start_date,
    ':end_date' => $end_date
]);
$data_mutasi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Totals
$total_masuk_periode = 0;
$total_keluar_periode = 0;
foreach ($data_mutasi as $row) {
    $total_masuk_periode += $row['masuk'];
    $total_keluar_periode += $row['keluar'];
}

$periode_text = date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date));
$filename = 'Laporan_Mutasi_Stok_' . date('Ymd', strtotime($start_date)) . '_' . date('Ymd', strtotime($end_date));

if ($type == 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Mutasi Stok');

    // Header Info
    $sheet->setCellValue('A1', 'LAPORAN MUTASI STOK');
    $sheet->setCellValue('A2', 'PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK');
    $sheet->setCellValue('A3', 'Periode: ' . $periode_text);
    $sheet->mergeCells('A1:I1');
    $sheet->mergeCells('A2:I2');
    $sheet->mergeCells('A3:I3');
    $sheet->getStyle('A1:A3')->getFont()->setBold(true);
    $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Table Header
    $headers = [
        'A5' => 'No',
        'B5' => 'Kode Barang',
        'C5' => 'Nama Barang',
        'D5' => 'Kategori',
        'E5' => 'Satuan',
        'F5' => 'Stok Awal',
        'G5' => 'Masuk',
        'H5' => 'Keluar',
        'I5' => 'Stok Akhir'
    ];

    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }
    
    $sheet->getStyle('A5:I5')->getFont()->setBold(true);
    $sheet->getStyle('A5:I5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A5:I5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A5:I5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

    // Data
    $rowNum = 6;
    $no = 1;
    foreach ($data_mutasi as $row) {
        $stok_akhir = $row['stok_awal'] + $row['masuk'] - $row['keluar'];
        
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, $row['kode_barang']);
        $sheet->setCellValue('C' . $rowNum, $row['nama_barang']);
        $sheet->setCellValue('D' . $rowNum, $row['kategori']);
        $sheet->setCellValue('E' . $rowNum, $row['satuan']);
        $sheet->setCellValue('F' . $rowNum, $row['stok_awal']);
        $sheet->setCellValue('G' . $rowNum, $row['masuk']);
        $sheet->setCellValue('H' . $rowNum, $row['keluar']);
        $sheet->setCellValue('I' . $rowNum, $stok_akhir);
        
        $rowNum++;
    }

    // Total Row
    $sheet->setCellValue('A' . $rowNum, 'TOTAL PERIODE INI');
    $sheet->mergeCells('A' . $rowNum . ':F' . $rowNum);
    $sheet->setCellValue('G' . $rowNum, $total_masuk_periode);
    $sheet->setCellValue('H' . $rowNum, $total_keluar_periode);
    
    $sheet->getStyle('A' . $rowNum . ':I' . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':F' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Styling Data Table
    $lastRow = $rowNum;
    $sheet->getStyle('A5:I' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A6:A' . ($lastRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No
    $sheet->getStyle('E6:E' . ($lastRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Satuan
    
    // Auto Size Columns
    foreach (range('A', 'I') as $col) {
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
    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L']); // Landscape for better table fit
    
    $html = '
    <div style="text-align: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">LAPORAN MUTASI STOK</h3>
        <h4 style="margin: 5px 0;">PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK</h4>
        <p style="margin: 0;">Periode: ' . $periode_text . '</p>
    </div>
    
    <table border="1" cellspacing="0" cellpadding="5" style="width: 100%; border-collapse: collapse; font-size: 10pt;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th width="5%">No</th>
                <th width="10%">Kode</th>
                <th>Nama Barang</th>
                <th width="10%">Kategori</th>
                <th width="8%">Satuan</th>
                <th width="10%">Awal</th>
                <th width="10%">Masuk</th>
                <th width="10%">Keluar</th>
                <th width="10%">Akhir</th>
            </tr>
        </thead>
        <tbody>';
    
    $no = 1;
    foreach ($data_mutasi as $row) {
        $stok_akhir = $row['stok_awal'] + $row['masuk'] - $row['keluar'];
        $html .= '
            <tr>
                <td style="text-align: center;">' . $no++ . '</td>
                <td>' . htmlspecialchars($row['kode_barang']) . '</td>
                <td>' . htmlspecialchars($row['nama_barang']) . '</td>
                <td>' . htmlspecialchars($row['kategori']) . '</td>
                <td style="text-align: center;">' . htmlspecialchars($row['satuan']) . '</td>
                <td style="text-align: right;">' . number_format($row['stok_awal'], 2, ',', '.') . '</td>
                <td style="text-align: right;">' . number_format($row['masuk'], 2, ',', '.') . '</td>
                <td style="text-align: right;">' . number_format($row['keluar'], 2, ',', '.') . '</td>
                <td style="text-align: right; font-weight: bold;">' . number_format($stok_akhir, 2, ',', '.') . '</td>
            </tr>';
    }
    
    if (count($data_mutasi) == 0) {
        $html .= '<tr><td colspan="9" style="text-align: center;">Tidak ada data transaksi.</td></tr>';
    }

    $html .= '
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="6" style="text-align: right;">Total Periode Ini</td>
                <td style="text-align: right;">' . number_format($total_masuk_periode, 2, ',', '.') . '</td>
                <td style="text-align: right;">' . number_format($total_keluar_periode, 2, ',', '.') . '</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    
    <div style="margin-top: 30px; text-align: right;">
        <p>Siluwok, ' . date('d F Y') . '</p>
        <p style="margin-top: 60px; margin-right: 30px;">(____________________)</p>
        <p style="margin-right: 40px;">Petugas Gudang</p>
    </div>
    ';

    $mpdf->WriteHTML($html);
    $mpdf->Output($filename . '.pdf', 'D'); // D for Download
    exit;
}
