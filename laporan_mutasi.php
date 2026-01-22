<?php
session_start();
require_once 'config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Filter Tanggal
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Query Mutasi Stok
// Stok Awal: Total Masuk - Total Keluar sebelum start_date
// Masuk: Total Masuk antara start_date dan end_date
// Keluar: Total Keluar antara start_date dan end_date
// Stok Akhir: Stok Awal + Masuk - Keluar

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

// Calculate Totals for Info Cards
$total_items = count($data_mutasi);
$total_masuk_periode = 0;
$total_keluar_periode = 0;

foreach ($data_mutasi as $row) {
    $total_masuk_periode += $row['masuk'];
    $total_keluar_periode += $row['keluar'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Mutasi Stok - PTPN I Regional 3</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="assets/img/log.png" alt="Logo" style="width: 30px;">
            <a href="#" class="sidebar-brand">KEBUN SILUWOK</a>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            
            <!-- Data Master Menu -->
            <li>
                <a href="#submenuDataMaster" data-bs-toggle="collapse" class="d-flex align-items-center">
                    <i class="fas fa-database"></i> Data Master 
                    <i class="fas fa-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                </a>
                <ul class="collapse sidebar-submenu" id="submenuDataMaster">
                    <li><a href="barang.php"><i class="fas fa-box-open" style="font-size: 0.9em;"></i> Barang</a></li>
                    <li><a href="afdeling.php"><i class="fas fa-building" style="font-size: 0.9em;"></i> Afdeling</a></li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <li><a href="akun.php"><i class="fas fa-users-cog" style="font-size: 0.9em;"></i> Akun</a></li>
                    <?php endif; ?>
                </ul>
            </li>

            <li><a href="kartu_gudang.php"><i class="fas fa-boxes"></i> Kartu Gudang</a></li>
            <li><a href="stok_masuk.php"><i class="fas fa-arrow-down"></i> Stok Masuk</a></li>
            <li><a href="stok_keluar.php"><i class="fas fa-arrow-up"></i> Stok Keluar</a></li>
            
            <!-- Laporan Menu -->
            <li>
                <a href="#submenuLaporan" data-bs-toggle="collapse" class="d-flex align-items-center active" aria-expanded="true">
                    <i class="fas fa-file-alt"></i> Laporan 
                    <i class="fas fa-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                </a>
                <ul class="collapse show sidebar-submenu" id="submenuLaporan">
                    <li><a href="laporan_mutasi.php" class="active"><i class="fas fa-exchange-alt" style="font-size: 0.9em;"></i> Mutasi Stok</a></li>
                    <li><a href="laporan_stok.php"><i class="fas fa-clipboard-list" style="font-size: 0.9em;"></i> Sisa Stok</a></li>
                </ul>
            </li>

            <li style="margin-top: 50px;">
                <a href="logout.php" style="color: #ff8a80;"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-md-none me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <div class="company-title">
                    <img src="assets/img/log.png" alt="Logo" style="height: 30px; margin-right: 10px;">
                    PT. PERKEBUNAN NUSANTARA I REGIONAL 3 KEBUN SILUWOK
                </div>
            </div>
            <div class="user-profile">
                <div class="text-end me-2 d-none d-sm-block">
                    <div style="font-weight: 600; font-size: 0.9rem;"><?php echo $_SESSION['nama_lengkap']; ?></div>
                    <div style="font-size: 0.75rem; color: #777;"><?php echo ucfirst($_SESSION['role']); ?></div>
                </div>
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1 text-white">Laporan Mutasi Stok</h4>
                    <p class="mb-0 text-white-50">Rekapitulasi pergerakan stok barang (Awal, Masuk, Keluar, Akhir).</p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                    <i class="fas fa-exchange-alt fa-3x text-white opacity-25"></i>
                </div>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card card-gradient-blue text-white h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="info-detail-label">Total Barang</span>
                                <span class="info-detail-value"><?php echo $total_items; ?> Item</span>
                                <div class="mt-1 small opacity-75">Data dalam laporan</div>
                            </div>
                            <i class="fas fa-boxes fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-gradient-green text-white h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="info-detail-label">Total Masuk</span>
                                <span class="info-detail-value"><?php echo number_format($total_masuk_periode, 2); ?></span>
                                <div class="mt-1 small opacity-75">Periode Ini</div>
                            </div>
                            <i class="fas fa-arrow-down fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-gradient-orange text-white h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="info-detail-label">Total Keluar</span>
                                <span class="info-detail-value"><?php echo number_format($total_keluar_periode, 2); ?></span>
                                <div class="mt-1 small opacity-75">Periode Ini</div>
                            </div>
                            <i class="fas fa-arrow-up fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card filter-card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold text-uppercase">Periode Tanggal</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-calendar-alt text-muted"></i></span>
                            <input type="date" name="start_date" class="form-control border-start-0 bg-light" value="<?php echo $start_date; ?>" required>
                            <span class="input-group-text bg-light border-start-0 border-end-0">s/d</span>
                            <input type="date" name="end_date" class="form-control border-start-0 bg-light" value="<?php echo $end_date; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm"><i class="fas fa-filter me-1"></i> Filter</button>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="btn-group shadow-sm">
                            <a href="export_mutasi.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&type=pdf" target="_blank" class="btn btn-light text-danger fw-bold">
                                <i class="fas fa-file-pdf me-1"></i> PDF
                            </a>
                            <a href="export_mutasi.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&type=excel" target="_blank" class="btn btn-light text-success fw-bold">
                                <i class="fas fa-file-excel me-1"></i> Excel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card table-card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-list me-2"></i>Rincian Mutasi Stok (<?php echo date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="mutasiTable" class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th rowspan="2" class="text-center py-3 text-secondary text-uppercase small fw-bold border-0" width="5%">No</th>
                                <th rowspan="2" class="py-3 text-secondary text-uppercase small fw-bold border-0">Kode Barang</th>
                                <th rowspan="2" class="py-3 text-secondary text-uppercase small fw-bold border-0">Nama Barang</th>
                                <th rowspan="2" class="py-3 text-secondary text-uppercase small fw-bold border-0">Kategori</th>
                                <th rowspan="2" class="text-center py-3 text-secondary text-uppercase small fw-bold border-0">Satuan</th>
                                <th colspan="4" class="text-center py-2 text-secondary text-uppercase small fw-bold border-0 bg-light border-bottom">Pergerakan Stok</th>
                            </tr>
                            <tr>
                                <th class="text-end py-2 text-secondary small fw-bold border-0">Awal</th>
                                <th class="text-end py-2 text-success small fw-bold border-0">Masuk</th>
                                <th class="text-end py-2 text-danger small fw-bold border-0">Keluar</th>
                                <th class="text-end py-2 text-primary small fw-bold border-0">Akhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($data_mutasi) > 0): ?>
                                <?php $no = 1; foreach ($data_mutasi as $row): 
                                    $stok_akhir = $row['stok_awal'] + $row['masuk'] - $row['keluar'];
                                ?>
                                <tr>
                                    <td class="text-center text-muted"><?php echo $no++; ?></td>
                                    <td><span class="badge bg-light text-secondary border"><?php echo $row['kode_barang']; ?></span></td>
                                    <td class="fw-bold text-dark"><?php echo $row['nama_barang']; ?></td>
                                    <td><span class="badge bg-info bg-opacity-10 text-info"><?php echo $row['kategori']; ?></span></td>
                                    <td class="text-center text-muted"><?php echo $row['satuan']; ?></td>
                                    <td class="text-end text-secondary fw-medium"><?php echo number_format($row['stok_awal'], 2, ',', '.'); ?></td>
                                    <td class="text-end text-success fw-medium"><?php echo number_format($row['masuk'], 2, ',', '.'); ?></td>
                                    <td class="text-end text-danger fw-medium"><?php echo number_format($row['keluar'], 2, ',', '.'); ?></td>
                                    <td class="text-end text-primary fw-bold bg-light"><?php echo number_format($stok_akhir, 2, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                                            <p class="mb-0">Tidak ada data transaksi pada periode ini.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-light fw-bold">
                            <tr>
                                <td colspan="6" class="text-end text-secondary text-uppercase py-3">Total Periode Ini</td>
                                <td class="text-end text-success py-3"><?php echo number_format($total_masuk_periode, 2, ',', '.'); ?></td>
                                <td class="text-end text-danger py-3"><?php echo number_format($total_keluar_periode, 2, ',', '.'); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SheetJS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    
    <script>
        // Toggle Sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('active');
        });

        // Export to Excel
        function exportToExcel() {
            const table = document.getElementById('mutasiTable');
            const wb = XLSX.utils.table_to_book(table, {sheet: "Mutasi Stok"});
            XLSX.writeFile(wb, 'Laporan_Mutasi_Stok_<?php echo $start_date; ?>_sd_<?php echo $end_date; ?>.xlsx');
        }
    </script>
</body>
</html>