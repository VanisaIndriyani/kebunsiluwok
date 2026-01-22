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
                    <h4 class="mb-1 text-white">Laporan Stok Masuk</h4>
                    <p class="mb-0 text-white-50">Monitoring penerimaan barang dan logistik kebun.</p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                    <i class="fas fa-arrow-down fa-3x text-white opacity-25"></i>
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
                                <span class="info-detail-label">Total Transaksi</span>
                                <span class="info-detail-value"><?php echo $total_transaksi; ?></span>
                                <div class="mt-1 small opacity-75">Transaksi Bulan Ini</div>
                            </div>
                            <i class="fas fa-file-invoice fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-gradient-green text-white h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="info-detail-label">Total Barang Masuk</span>
                                <span class="info-detail-value"><?php echo number_format($total_qty, 2); ?></span>
                                <div class="mt-1 small opacity-75">Unit / Kg / Liter</div>
                            </div>
                            <i class="fas fa-cubes fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-gradient-orange text-white h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="info-detail-label">Item Terbanyak</span>
                                <span class="info-detail-value" style="font-size: 1.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; display: block;"><?php echo $top_item; ?></span>
                                <div class="mt-1 small opacity-75"><?php echo number_format($top_qty, 2); ?> Masuk</div>
                            </div>
                            <i class="fas fa-star fa-2x opacity-25"></i>
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
                        <label class="form-label small text-muted fw-bold text-uppercase">Afdeling</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-building text-muted"></i></span>
                            <select name="afdeling" class="form-select border-start-0 bg-light" onchange="this.form.submit()">
                                <option value="">Semua Afdeling</option>
                                <?php foreach ($afdelings as $afd): ?>
                                    <option value="<?php echo $afd['id']; ?>" <?php echo (isset($_GET['afdeling']) && $_GET['afdeling'] == $afd['id']) ? 'selected' : ''; ?>>
                                        <?php echo $afd['nama_afdeling']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Periode Bulan</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-calendar-alt text-muted"></i></span>
                            <input type="month" name="bulan" class="form-control border-start-0 bg-light" value="<?php echo $selected_bulan; ?>" onchange="this.form.submit()">
                        </div>
                    </div>
                    <div class="col-md-5 text-md-end">
                        <div class="btn-group shadow-sm">
                            <a href="export_masuk.php?afdeling=<?php echo isset($_GET['afdeling']) ? $_GET['afdeling'] : ''; ?>&bulan=<?php echo $selected_bulan; ?>&type=pdf" target="_blank" class="btn btn-light text-success fw-bold">
                                <i class="fas fa-print me-1"></i> Print
                            </a>
                            <a href="export_masuk.php?afdeling=<?php echo isset($_GET['afdeling']) ? $_GET['afdeling'] : ''; ?>&bulan=<?php echo $selected_bulan; ?>&type=excel" target="_blank" class="btn btn-light text-success fw-bold">
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
                <h6 class="m-0 fw-bold text-success"><i class="fas fa-list me-2"></i>Rincian Stok Masuk - <?php echo date('F Y', strtotime($selected_bulan)); ?></h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="stokMasukTable" class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-center py-3 text-secondary text-uppercase small fw-bold border-0" width="5%">No</th>
                                <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Tanggal</th>
                                <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Kode Barang</th>
                                <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Nama Barang</th>
                                <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Kategori</th>
                                <th class="text-center py-3 text-secondary text-uppercase small fw-bold border-0">Satuan</th>
                                <th class="text-end py-3 text-secondary text-uppercase small fw-bold border-0">Jumlah Masuk</th>
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
                                    <td class="fw-bold text-dark"><?php echo $row['nama_barang']; ?></td>
                                    <td><span class="badge bg-info bg-opacity-10 text-info"><?php echo $row['kategori']; ?></span></td>
                                    <td class="text-center text-muted"><?php echo $row['satuan']; ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo number_format($row['jumlah'], 2, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                                            <p class="mb-0">Tidak ada data stok masuk pada periode ini.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-light fw-bold">
                            <tr>
                                <td colspan="6" class="text-end text-secondary text-uppercase py-3">Total Masuk</td>
                                <td class="text-end text-success py-3 fs-6"><?php echo number_format($total_qty, 2, ',', '.'); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
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