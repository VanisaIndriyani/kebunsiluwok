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

// Fetch Afdeling untuk Filter
$stmt_afdeling = $koneksi->query("SELECT * FROM afdeling ORDER BY nama_afdeling ASC");
$afdelings = $stmt_afdeling->fetchAll(PDO::FETCH_ASSOC);

// Handle Filters
$where_clauses = ["t.jenis_transaksi = 'masuk'"];
$params = [];

$nama_afdeling_filter = 'Semua Afdeling';
if (isset($_GET['afdeling']) && !empty($_GET['afdeling'])) {
    $where_clauses[] = "t.id_afdeling = :afdeling";
    $params[':afdeling'] = $_GET['afdeling'];
    
    // Get nama afdeling for title
    $stmt_afd_name = $koneksi->prepare("SELECT nama_afdeling FROM afdeling WHERE id = ?");
    $stmt_afd_name->execute([$_GET['afdeling']]);
    $nama_afdeling_filter = $stmt_afd_name->fetchColumn();
}

// Filter Bulan (Default: Bulan Terbaru dari Transaksi)
if (isset($_GET['bulan']) && !empty($_GET['bulan'])) {
    $selected_bulan = $_GET['bulan'];
} else {
    // Cari bulan transaksi terakhir
    $stmt_max = $koneksi->query("SELECT MAX(tanggal) FROM transaksi_gudang WHERE jenis_transaksi = 'masuk'");
    $max_date = $stmt_max->fetchColumn();
    $selected_bulan = $max_date ? date('Y-m', strtotime($max_date)) : date('Y-m');
}

$where_clauses[] = "DATE_FORMAT(t.tanggal, '%Y-%m') = :bulan";
$params[':bulan'] = $selected_bulan;

// Build Query
$sql = "SELECT t.*, b.kode_barang, b.nama_barang, b.satuan, b.kategori, a.nama_afdeling 
        FROM transaksi_gudang t
        JOIN barang b ON t.id_barang = b.id
        JOIN afdeling a ON t.id_afdeling = a.id
        WHERE " . implode(" AND ", $where_clauses) . "
        ORDER BY t.tanggal DESC, t.id DESC";

$stmt = $koneksi->prepare($sql);
$stmt->execute($params);
$transaksi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Totals
$total_qty = 0;
foreach ($transaksi as $row) {
    $total_qty += $row['jumlah'];
}

$type = isset($_GET['type']) ? $_GET['type'] : 'pdf';
$filename = 'Laporan_Stok_Masuk_' . $selected_bulan;

if ($type == 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Stok Masuk');

    // Header Info
    $sheet->setCellValue('A1', 'LAPORAN STOK MASUK');
    $sheet->setCellValue('A2', 'PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK');
    $sheet->setCellValue('A3', 'Periode: ' . date('F Y', strtotime($selected_bulan)));
    $sheet->setCellValue('A4', 'Afdeling: ' . $nama_afdeling_filter);

    $sheet->mergeCells('A1:G1');
    $sheet->mergeCells('A2:G2');
    $sheet->mergeCells('A3:G3');
    $sheet->mergeCells('A4:G4');

    $sheet->getStyle('A1:A4')->getFont()->setBold(true);
    $sheet->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Table Header
    $headers = [
        'A6' => 'No',
        'B6' => 'Tanggal',
        'C6' => 'Afdeling',
        'D6' => 'Kode Barang',
        'E6' => 'Nama Barang',
        'F6' => 'Kategori',
        'G6' => 'Satuan',
        'H6' => 'Jumlah Masuk'
    ];

    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }

    $sheet->getStyle('A6:H6')->getFont()->setBold(true);
    $sheet->getStyle('A6:H6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A6:H6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A6:H6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

    // Data
    $rowNum = 7;
    $no = 1;
    foreach ($transaksi as $row) {
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, date('d/m/Y', strtotime($row['tanggal'])));
        $sheet->setCellValue('C' . $rowNum, $row['nama_afdeling']);
        $sheet->setCellValue('D' . $rowNum, $row['kode_barang']);
        $sheet->setCellValue('E' . $rowNum, $row['nama_barang']);
        $sheet->setCellValue('F' . $rowNum, $row['kategori']);
        $sheet->setCellValue('G' . $rowNum, $row['satuan']);
        $sheet->setCellValue('H' . $rowNum, $row['jumlah']);
        
        $rowNum++;
    }

    // Total Row
    $sheet->setCellValue('A' . $rowNum, 'TOTAL MASUK');
    $sheet->mergeCells('A' . $rowNum . ':G' . $rowNum);
    $sheet->setCellValue('H' . $rowNum, $total_qty);
    
    $sheet->getStyle('A' . $rowNum . ':H' . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Borders
    $lastRow = $rowNum;
    $sheet->getStyle('A7:H' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A7:B' . ($lastRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D7:D' . ($lastRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F7:G' . ($lastRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('H7:H' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Align numbers right

    // Auto Size Columns
    foreach (range('A', 'H') as $col) {
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
        <h3 style="margin: 0;">LAPORAN STOK MASUK</h3>
        <h4 style="margin: 5px 0;">PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK</h4>
        <p style="margin: 0;">Periode: ' . date('F Y', strtotime($selected_bulan)) . '</p>
        <p style="margin: 0;">Afdeling: ' . htmlspecialchars($nama_afdeling_filter) . '</p>
    </div>
    
    <table border="1" cellspacing="0" cellpadding="5" style="width: 100%; border-collapse: collapse; font-size: 10pt;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th width="5%">No</th>
                <th width="12%">Tanggal</th>
                <th width="15%">Afdeling</th>
                <th width="15%">Kode Barang</th>
                <th>Nama Barang</th>
                <th width="10%">Satuan</th>
                <th width="15%">Jumlah</th>
            </tr>
        </thead>
        <tbody>';
    
    $no = 1;
    foreach ($transaksi as $row) {
        $html .= '
            <tr>
                <td style="text-align: center;">' . $no++ . '</td>
                <td style="text-align: center;">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>
                <td>' . htmlspecialchars($row['nama_afdeling']) . '</td>
                <td style="text-align: center;">' . htmlspecialchars($row['kode_barang']) . '</td>
                <td>' . htmlspecialchars($row['nama_barang']) . '</td>
                <td style="text-align: center;">' . htmlspecialchars($row['satuan']) . '</td>
                <td style="text-align: right; font-weight: bold; color: green;">' . number_format($row['jumlah'], 2, ',', '.') . '</td>
            </tr>';
    }
    
    if (count($transaksi) == 0) {
        $html .= '<tr><td colspan="7" style="text-align: center;">Tidak ada data stok masuk.</td></tr>';
    }

    $html .= '
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="6" style="text-align: right;">Total Masuk</td>
                <td style="text-align: right;">' . number_format($total_qty, 2, ',', '.') . '</td>
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
    $mpdf->Output($filename . '.pdf', 'D');
    exit;
}
