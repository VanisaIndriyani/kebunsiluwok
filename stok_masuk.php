<?php
session_start();
require_once 'config/database.php';

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

if (isset($_GET['afdeling']) && !empty($_GET['afdeling'])) {
    $where_clauses[] = "t.id_afdeling = :afdeling";
    $params[':afdeling'] = $_GET['afdeling'];
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

// Calculate Totals and Stats
$total_transaksi = count($transaksi);
$total_qty = 0;
$item_counts = [];

foreach ($transaksi as $row) {
    $total_qty += $row['jumlah'];
    if (!isset($item_counts[$row['nama_barang']])) {
        $item_counts[$row['nama_barang']] = 0;
    }
    $item_counts[$row['nama_barang']] += $row['jumlah'];
}

$top_item = '-';
$top_qty = 0;
if (!empty($item_counts)) {
    arsort($item_counts);
    $top_item = array_key_first($item_counts);
    $top_qty = $item_counts[$top_item];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Masuk - PTPN I Regional 3</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo filemtime('assets/css/dashboard.css'); ?>">
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
                    <li><a href="akun.php"><i class="fas fa-user-edit" style="font-size: 0.9em;"></i> Edit Profile</a></li>
                    <?php endif; ?>
                </ul>
            </li>

            <li><a href="kartu_gudang.php"><i class="fas fa-boxes"></i> Kartu Gudang</a></li>
            <li><a href="stok_masuk.php" class="active"><i class="fas fa-arrow-down"></i> Stok Masuk</a></li>
            <li><a href="stok_keluar.php"><i class="fas fa-arrow-up"></i> Stok Keluar</a></li>
            <li>
                <a href="#submenuLaporan" data-bs-toggle="collapse" class="d-flex align-items-center">
                    <i class="fas fa-file-alt"></i> Laporan 
                    <i class="fas fa-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                </a>
                <ul class="collapse sidebar-submenu" id="submenuLaporan">
                    <li><a href="laporan_mutasi.php"><i class="fas fa-exchange-alt" style="font-size: 0.9em;"></i> Mutasi Stok</a></li>
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
        <div class="top-navbar animate-fade-in">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-lg-none me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <div class="d-none d-md-block">
                    <h5 class="m-0 fw-bold text-success">Laporan Stok Masuk</h5>
                    <p class="m-0 text-muted" style="font-size: 0.85rem;">Monitoring penerimaan barang dan logistik kebun</p>
                </div>
            </div>
            <div class="user-profile">
                <div class="text-end me-2 d-none d-sm-block">
                    <div class="fw-bold text-dark"><?php echo $_SESSION['nama_lengkap']; ?></div>
                    <div style="font-size: 0.75rem; color: #777;"><?php echo ucfirst($_SESSION['role']); ?></div>
                </div>
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner animate-fade-in delay-1 mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="fw-bold mb-2 text-white">Stok Masuk Logistik</h4>
                    <p class="mb-0 text-white-50">Rekapitulasi penerimaan barang ke gudang pusat.</p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                     <span style="background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 30px; font-weight: 500; color: white;">
                        <i class="fas fa-calendar-alt me-2"></i> <?php echo date('F Y', strtotime($selected_bulan)); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4 animate-fade-in delay-2">
                <div class="stats-card bg-gradient-1">
                    <div class="stats-icon"><i class="fas fa-file-invoice"></i></div>
                    <div class="stats-label">Total Transaksi</div>
                    <div class="stats-value"><?php echo $total_transaksi; ?></div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">Transaksi Bulan Ini</div>
                </div>
            </div>
            <div class="col-md-4 animate-fade-in delay-2">
                <div class="stats-card bg-gradient-2">
                    <div class="stats-icon"><i class="fas fa-cubes"></i></div>
                    <div class="stats-label">Total Barang Masuk</div>
                    <div class="stats-value"><?php echo number_format($total_qty, 0); ?></div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">Unit / Kg / Liter</div>
                </div>
            </div>
            <div class="col-md-4 animate-fade-in delay-2">
                <div class="stats-card bg-gradient-3">
                    <div class="stats-icon"><i class="fas fa-star"></i></div>
                    <div class="stats-label">Item Terbanyak</div>
                    <div class="stats-value" style="font-size: 1.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $top_item; ?></div>
                    <div style="font-size: 0.85rem; opacity: 0.8;"><?php echo number_format($top_qty, 0); ?> Unit Masuk</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="glass-card animate-fade-in delay-3">
            <div class="section-title">
                <i class="fas fa-filter"></i> Filter Data
            </div>
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="form-floating">
                        <select name="afdeling" class="form-select" id="afdelingSelect" onchange="this.form.submit()">
                            <option value="">Semua Afdeling</option>
                            <?php foreach ($afdelings as $afd): ?>
                                <option value="<?php echo $afd['id']; ?>" <?php echo (isset($_GET['afdeling']) && $_GET['afdeling'] == $afd['id']) ? 'selected' : ''; ?>>
                                    <?php echo $afd['nama_afdeling']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="afdelingSelect">Pilih Afdeling</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <input type="month" name="bulan" class="form-control" id="bulanSelect" value="<?php echo $selected_bulan; ?>" onchange="this.form.submit()">
                        <label for="bulanSelect">Periode Bulan</label>
                    </div>
                </div>
                <div class="col-md-5 d-flex align-items-center justify-content-end gap-2">
                     <a href="export_masuk.php?afdeling=<?php echo isset($_GET['afdeling']) ? $_GET['afdeling'] : ''; ?>&bulan=<?php echo $selected_bulan; ?>&type=excel" target="_blank" class="btn btn-success rounded-pill px-4 py-2 fw-bold">
                        <i class="fas fa-file-excel me-2"></i> Excel
                    </a>
                    <a href="export_masuk.php?afdeling=<?php echo isset($_GET['afdeling']) ? $_GET['afdeling'] : ''; ?>&bulan=<?php echo $selected_bulan; ?>&type=pdf" target="_blank" class="btn btn-danger rounded-pill px-4 py-2 fw-bold">
                        <i class="fas fa-file-pdf me-2"></i> PDF
                    </a>
                </div>
            </form>
        </div>

        <!-- Data Table -->
        <div class="glass-card animate-fade-in delay-4">
            <div class="section-title mb-4">
                <i class="fas fa-list"></i> Rincian Stok Masuk
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th class="text-center">Satuan</th>
                            <th class="text-end">Jumlah Masuk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transaksi) > 0): ?>
                            <?php $no = 1; foreach ($transaksi as $row): ?>
                            <tr>
                                <td class="text-center text-muted"><?php echo $no++; ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></div>
                                    <div class="small text-muted"><?php echo $row['nama_afdeling']; ?></div>
                                </td>
                                <td><span class="badge bg-light text-secondary border"><?php echo $row['kode_barang']; ?></span></td>
                                <td class="fw-bold text-success"><?php echo $row['nama_barang']; ?></td>
                                <td><span class="badge bg-info bg-opacity-10 text-info border border-info"><?php echo $row['kategori']; ?></span></td>
                                <td class="text-center text-muted"><?php echo $row['satuan']; ?></td>
                                <td class="text-end fw-bold text-dark"><?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0">Tidak ada data stok masuk pada periode ini.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-light fw-bold">
                        <tr>
                            <td colspan="6" class="text-end text-uppercase py-3">Total Masuk</td>
                            <td class="text-end text-success py-3 fs-6"><?php echo number_format($total_qty, 0, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('active');
        });
    </script>
</body>
</html>