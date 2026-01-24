<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch Master Data
$stmt_afdeling = $koneksi->query("SELECT * FROM afdeling");
$afdelings = $stmt_afdeling->fetchAll(PDO::FETCH_ASSOC);

$stmt_barang = $koneksi->query("SELECT * FROM barang");
$barangs = $stmt_barang->fetchAll(PDO::FETCH_ASSOC);

// Handle Filter
$selected_afdeling = isset($_GET['afdeling']) ? $_GET['afdeling'] : (isset($afdelings[0]['id']) ? $afdelings[0]['id'] : '');
$selected_barang = isset($_GET['barang']) ? $_GET['barang'] : (isset($barangs[0]['id']) ? $barangs[0]['id'] : '');

// Determine default month
if (isset($_GET['bulan'])) {
    $selected_bulan = $_GET['bulan'];
} else {
    $stmt_max = $koneksi->query("SELECT MAX(tanggal) FROM transaksi_gudang");
    $max_date = $stmt_max->fetchColumn();
    $selected_bulan = $max_date ? date('Y-m', strtotime($max_date)) : date('Y-m');
}

$type = isset($_GET['type']) ? $_GET['type'] : 'pdf';

$transaksi = [];
$info_barang = null;
$info_afdeling = null;
$stok_awal = 0;
$total_masuk_periode = 0;
$total_keluar_periode = 0;

if ($selected_afdeling && $selected_barang) {
    // Get info barang
    $stmt_info = $koneksi->prepare("SELECT * FROM barang WHERE id = ?");
    $stmt_info->execute([$selected_barang]);
    $info_barang = $stmt_info->fetch(PDO::FETCH_ASSOC);

    // Get info afdeling
    $stmt_info_afd = $koneksi->prepare("SELECT * FROM afdeling WHERE id = ?");
    $stmt_info_afd->execute([$selected_afdeling]);
    $info_afdeling = $stmt_info_afd->fetch(PDO::FETCH_ASSOC);

    // Query Transaksi
    $sql = "SELECT * FROM transaksi_gudang 
            WHERE id_afdeling = :afdeling 
            AND id_barang = :barang 
            AND DATE_FORMAT(tanggal, '%Y-%m') = :bulan
            ORDER BY tanggal ASC, id ASC";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([
        ':afdeling' => $selected_afdeling,
        ':barang' => $selected_barang,
        ':bulan' => $selected_bulan
    ]);
    $transaksi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hitung Stok Awal
    $start_date = $selected_bulan . '-01';
    $stmt_awal = $koneksi->prepare("SELECT SUM(CASE WHEN jenis_transaksi = 'masuk' THEN jumlah ELSE -jumlah END) FROM transaksi_gudang WHERE id_afdeling = :afd AND id_barang = :brg AND tanggal < :start_date");
    $stmt_awal->execute([
        ':afd' => $selected_afdeling,
        ':brg' => $selected_barang,
        ':start_date' => $start_date
    ]);
    $stok_awal = $stmt_awal->fetchColumn() ?: 0;

    // Hitung Total untuk Info Cards
    foreach ($transaksi as $t) {
        if ($t['jenis_transaksi'] == 'masuk') $total_masuk_periode += $t['jumlah'];
        if ($t['jenis_transaksi'] == 'keluar') $total_keluar_periode += $t['jumlah'];
    }
}

$periode_text = date('F Y', strtotime($selected_bulan));
$filename = 'Kartu_Gudang_' . $selected_bulan . '_' . ($info_barang ? $info_barang['kode_barang'] : 'Unknown');

if ($type == 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Kartu Gudang');

    // Header Info
    $sheet->setCellValue('A1', 'KARTU GUDANG BARANG');
    $sheet->setCellValue('A2', 'PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK');
    
    $sheet->setCellValue('A4', 'Afdeling: ' . ($info_afdeling ? $info_afdeling['nama_afdeling'] : '-'));
    $sheet->setCellValue('E4', 'Kode Barang: ' . ($info_barang ? $info_barang['kode_barang'] : '-'));
    
    $sheet->setCellValue('A5', 'Nama Barang: ' . ($info_barang ? $info_barang['nama_barang'] : '-'));
    $sheet->setCellValue('E5', 'Periode: ' . $periode_text);
    
    $sheet->setCellValue('A6', 'Satuan: ' . ($info_barang ? $info_barang['satuan'] : '-'));

    $sheet->mergeCells('A1:I1');
    $sheet->mergeCells('A2:I2');
    
    $sheet->getStyle('A1:A2')->getFont()->setBold(true);
    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Table Header
    $sheet->setCellValue('A8', 'No. Bukti Penerimaan');
    $sheet->setCellValue('B8', 'Tanggal');
    $sheet->setCellValue('C8', 'Nama Mandor yang mengambil');
    $sheet->setCellValue('D8', 'Dipakai untuk diterima dari');
    $sheet->setCellValue('E8', 'Banyaknya');
    $sheet->setCellValue('E9', 'Masuk');
    $sheet->setCellValue('F9', 'Keluar');
    $sheet->setCellValue('G9', 'Sisa');
    $sheet->setCellValue('H8', 'Keterangan');
    $sheet->setCellValue('I8', 'Tanda Tangan Asisten Afd./Wakil Asisten Afd');

    $sheet->mergeCells('A8:A9');
    $sheet->mergeCells('B8:B9');
    $sheet->mergeCells('C8:C9');
    $sheet->mergeCells('D8:D9');
    $sheet->mergeCells('E8:G8');
    $sheet->mergeCells('H8:H9');
    $sheet->mergeCells('I8:I9');
    
    $sheet->getStyle('A8:I9')->getFont()->setBold(true);
    $sheet->getStyle('A8:I9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A8:I9')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A8:I9')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A8:I9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

    // Saldo Awal Row
    $rowNum = 10;
    $sheet->setCellValue('A' . $rowNum, '');
    $sheet->setCellValue('B' . $rowNum, '');
    $sheet->setCellValue('C' . $rowNum, '');
    $sheet->setCellValue('D' . $rowNum, 'Saldo Awal');
    $sheet->setCellValue('E' . $rowNum, '-');
    $sheet->setCellValue('F' . $rowNum, '-');
    $sheet->setCellValue('G' . $rowNum, $stok_awal);
    $sheet->setCellValue('H' . $rowNum, '');
    $sheet->setCellValue('I' . $rowNum, '');
    
    $sheet->getStyle('D' . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('D' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('E' . $rowNum . ':G' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('G' . $rowNum)->getFont()->setBold(true);

    $rowNum++;

    // Data Transaksi
    $sisa = $stok_awal;
    foreach ($transaksi as $row) {
        $masuk = ($row['jenis_transaksi'] == 'masuk') ? $row['jumlah'] : 0;
        $keluar = ($row['jenis_transaksi'] == 'keluar') ? $row['jumlah'] : 0;
        $sisa = $sisa + $masuk - $keluar;

        $sheet->setCellValue('A' . $rowNum, $row['no_bukti']);
        $sheet->setCellValue('B' . $rowNum, date('d/m/Y', strtotime($row['tanggal'])));
        $sheet->setCellValue('C' . $rowNum, $row['nama_mandor']);
        $sheet->setCellValue('D' . $rowNum, $row['keterangan']);
        $sheet->setCellValue('E' . $rowNum, ($masuk > 0 ? $masuk : '-'));
        $sheet->setCellValue('F' . $rowNum, ($keluar > 0 ? $keluar : '-'));
        $sheet->setCellValue('G' . $rowNum, $sisa);
        $sheet->setCellValue('H' . $rowNum, $row['keterangan_lain']);
        $sheet->setCellValue('I' . $rowNum, '');

        // Add Signature Image if exists
        if (!empty($row['ttd_asisten']) && file_exists('assets/img/ttd/' . $row['ttd_asisten'])) {
            $drawing = new Drawing();
            $drawing->setName('TTD');
            $drawing->setDescription('Tanda Tangan');
            $drawing->setPath('assets/img/ttd/' . $row['ttd_asisten']);
            $drawing->setHeight(40);
            $drawing->setCoordinates('I' . $rowNum);
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($sheet);
            // Adjust row height to fit image
            $sheet->getRowDimension($rowNum)->setRowHeight(45);
        }

        $rowNum++;
    }

    // Total Row
    $sheet->setCellValue('A' . $rowNum, 'TOTAL MUTASI BULAN INI');
    $sheet->mergeCells('A' . $rowNum . ':D' . $rowNum);
    $sheet->setCellValue('E' . $rowNum, $total_masuk_periode);
    $sheet->setCellValue('F' . $rowNum, $total_keluar_periode);
    $sheet->setCellValue('G' . $rowNum, $sisa);
    $sheet->setCellValue('H' . $rowNum, '');
    $sheet->setCellValue('I' . $rowNum, '');
    
    $sheet->getStyle('A' . $rowNum . ':I' . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Styling Data Table
    $lastRow = $rowNum;
    $sheet->getStyle('A10:I' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A10:B' . ($lastRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
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
    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L']); // Landscape for more columns
    
    $html = '
    <div style="text-align: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">KARTU GUDANG BARANG</h3>
        <h4 style="margin: 5px 0;">PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK</h4>
    </div>
    
    <table style="width: 100%; margin-bottom: 20px; font-size: 10pt;">
        <tr>
            <td width="15%"><strong>Afdeling</strong></td>
            <td width="35%">: ' . ($info_afdeling ? $info_afdeling['nama_afdeling'] : '-') . '</td>
            <td width="15%"><strong>Kode Barang</strong></td>
            <td width="35%">: ' . ($info_barang ? $info_barang['kode_barang'] : '-') . '</td>
        </tr>
        <tr>
            <td><strong>Nama Barang</strong></td>
            <td>: ' . ($info_barang ? $info_barang['nama_barang'] : '-') . '</td>
            <td><strong>Periode</strong></td>
            <td>: ' . $periode_text . '</td>
        </tr>
        <tr>
            <td><strong>Satuan</strong></td>
            <td>: ' . ($info_barang ? $info_barang['satuan'] : '-') . '</td>
            <td></td>
            <td></td>
        </tr>
    </table>
    
    <table border="1" cellspacing="0" cellpadding="5" style="width: 100%; border-collapse: collapse; font-size: 10pt;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th rowspan="2">No. Bukti Penerimaan</th>
                <th rowspan="2">Tanggal</th>
                <th rowspan="2">Nama Mandor yang mengambil</th>
                <th rowspan="2">Dipakai untuk diterima dari</th>
                <th colspan="3">Banyaknya</th>
                <th rowspan="2">Keterangan</th>
                <th rowspan="2">Tanda Tangan Asisten Afd./Wakil Asisten Afd</th>
            </tr>
            <tr style="background-color: #f2f2f2;">
                <th>Masuk</th>
                <th>Keluar</th>
                <th>Sisa</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background-color: #fafafa;">
                <td colspan="4" style="text-align: right; font-weight: bold;">Saldo Awal</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center; font-weight: bold;">' . number_format($stok_awal, 2) . '</td>
                <td></td>
                <td></td>
            </tr>';
    
    $sisa = $stok_awal;
    foreach ($transaksi as $row) {
        $masuk = ($row['jenis_transaksi'] == 'masuk') ? $row['jumlah'] : 0;
        $keluar = ($row['jenis_transaksi'] == 'keluar') ? $row['jumlah'] : 0;
        $sisa = $sisa + $masuk - $keluar;
        
        $ttd_html = '';
        if (!empty($row['ttd_asisten']) && file_exists('assets/img/ttd/' . $row['ttd_asisten'])) {
            $ttd_html = '<img src="assets/img/ttd/' . $row['ttd_asisten'] . '" style="height: 40px;">';
        }

        $html .= '
            <tr>
                <td style="text-align: center;">' . htmlspecialchars($row['no_bukti']) . '</td>
                <td style="text-align: center;">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>
                <td>' . htmlspecialchars($row['nama_mandor']) . '</td>
                <td>' . htmlspecialchars($row['keterangan']) . '</td>
                <td style="text-align: center;">' . ($masuk > 0 ? number_format($masuk, 2) : '-') . '</td>
                <td style="text-align: center;">' . ($keluar > 0 ? number_format($keluar, 2) : '-') . '</td>
                <td style="text-align: center; font-weight: bold;">' . number_format($sisa, 2) . '</td>
                <td>' . htmlspecialchars($row['keterangan_lain']) . '</td>
                <td style="text-align: center;">' . $ttd_html . '</td>
            </tr>';
    }
    
    if (count($transaksi) == 0) {
        $html .= '<tr><td colspan="9" style="text-align: center;">Tidak ada data transaksi.</td></tr>';
    }

    $html .= '
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="4" style="text-align: right;">Total Mutasi Bulan Ini</td>
                <td style="text-align: center;">' . number_format($total_masuk_periode, 2) . '</td>
                <td style="text-align: center;">' . number_format($total_keluar_periode, 2) . '</td>
                <td style="text-align: center;">' . number_format($sisa, 2) . '</td>
                <td></td>
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
    $mpdf->Output($filename . '.pdf', 'D');
    exit;
}
