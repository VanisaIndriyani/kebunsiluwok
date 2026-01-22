<?php
session_start();
require_once 'config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$params = [];
$where_clause = "";

if (!empty($search)) {
    $where_clause = "WHERE nama_barang LIKE :search OR kode_barang LIKE :search OR kategori LIKE :search";
    $params[':search'] = "%$search%";
}

// Fetch Data Barang
$sql = "SELECT * FROM barang $where_clause ORDER BY nama_barang ASC";
$stmt = $koneksi->prepare($sql);
$stmt->execute($params);
$barang = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats (Always calculate from full dataset for summary cards)
// Note: Ideally summary cards show global stats, but if filtered, maybe show filtered stats?
// Let's show Global Stats for the cards to be useful overview.
$stmt_stats = $koneksi->query("SELECT 
    COUNT(*) as total_items, 
    SUM(stok) as total_qty,
    SUM(CASE WHEN stok <= 10 THEN 1 ELSE 0 END) as low_stock_count
    FROM barang");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$total_items = $stats['total_items'];
$total_qty = $stats['total_qty'];
$low_stock_count = $stats['low_stock_count'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Sisa Stok - PTPN I Regional 3</title>
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
                    <li><a href="laporan_mutasi.php"><i class="fas fa-exchange-alt" style="font-size: 0.9em;"></i> Mutasi Stok</a></li>
                    <li><a href="laporan_stok.php" class="active"><i class="fas fa-clipboard-list" style="font-size: 0.9em;"></i> Sisa Stok</a></li>
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
                    <h4 class="mb-1 text-white">Laporan Sisa Stok</h4>
                    <p class="mb-0 text-white-50">Monitoring ketersediaan barang di gudang saat ini.</p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                    <i class="fas fa-clipboard-list fa-3x text-white opacity-25"></i>
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
                                <span class="info-detail-label">Total Item Barang</span>
                                <span class="info-detail-value"><?php echo $total_items; ?></span>
                                <div class="mt-1 small opacity-75">Jenis Barang Terdaftar</div>
                            </div>
                            <i class="fas fa-box-open fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-gradient-green text-white h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="info-detail-label">Total Stok Fisik</span>
                                <span class="info-detail-value"><?php echo number_format($total_qty, 0, ',', '.'); ?></span>
                                <div class="mt-1 small opacity-75">Akumulasi Seluruh Barang</div>
                            </div>
                            <i class="fas fa-cubes fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card <?php echo $low_stock_count > 0 ? 'card-gradient-red' : 'card-gradient-purple'; ?> text-white h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="info-detail-label">Stok Menipis</span>
                                <span class="info-detail-value"><?php echo $low_stock_count; ?></span>
                                <div class="mt-1 small opacity-75">Item Perlu Re-stock (≤ 10)</div>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Action Section -->
        <div class="card filter-card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 bg-light" placeholder="Cari nama barang, kode, atau kategori..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">Cari</button>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="btn-group shadow-sm">
                            <a href="export_stok.php?type=pdf&search=<?php echo urlencode($search); ?>" target="_blank" class="btn btn-light text-danger fw-bold">
                                <i class="fas fa-print me-1"></i> Print PDF
                            </a>
                            <a href="export_stok.php?type=excel&search=<?php echo urlencode($search); ?>" target="_blank" class="btn btn-light text-success fw-bold">
                                <i class="fas fa-file-excel me-1"></i> Export Excel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card table-card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="m-0 fw-bold text-success"><i class="fas fa-list me-2"></i>Daftar Sisa Stok Barang</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-center py-3 text-secondary text-uppercase small fw-bold border-0" width="5%">No</th>
                                <th class="py-3 text-secondary text-uppercase small fw-bold border-0" width="15%">Kode Barang</th>
                                <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Nama Barang</th>
                                <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Kategori</th>
                                <th class="text-center py-3 text-secondary text-uppercase small fw-bold border-0" width="10%">Satuan</th>
                                <th class="text-end py-3 text-secondary text-uppercase small fw-bold border-0" width="15%">Stok Saat Ini</th>
                                <th class="text-center py-3 text-secondary text-uppercase small fw-bold border-0" width="15%">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($barang) > 0): ?>
                                <?php $no = 1; foreach ($barang as $row): ?>
                                <tr>
                                    <td class="text-center text-muted"><?php echo $no++; ?></td>
                                    <td><span class="badge bg-light text-secondary border"><?php echo $row['kode_barang']; ?></span></td>
                                    <td class="fw-bold text-dark"><?php echo $row['nama_barang']; ?></td>
                                    <td><span class="badge bg-info bg-opacity-10 text-info"><?php echo $row['kategori']; ?></span></td>
                                    <td class="text-center text-muted"><?php echo $row['satuan']; ?></td>
                                    <td class="text-end fw-bold <?php echo ($row['stok'] <= 10) ? 'text-danger' : 'text-success'; ?>" style="font-size: 1.1em;">
                                        <?php echo number_format($row['stok'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['stok'] <= 10): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-3 py-2 rounded-pill">
                                                <i class="fas fa-exclamation-circle me-1"></i> Menipis
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 rounded-pill">
                                                <i class="fas fa-check-circle me-1"></i> Aman
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-box-open fa-3x mb-3 opacity-25"></i>
                                            <p class="mb-0">Data barang tidak ditemukan.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
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