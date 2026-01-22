<?php
session_start();
require_once 'config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch Filter Data
$stmt_afdeling = $koneksi->query("SELECT * FROM afdeling");
$afdelings = $stmt_afdeling->fetchAll(PDO::FETCH_ASSOC);

// Handle Filters
$selected_afdeling = isset($_GET['afdeling']) ? $_GET['afdeling'] : '';

// Determine default month (latest transaction month or current month)
if (isset($_GET['bulan'])) {
    $selected_bulan = $_GET['bulan'];
} else {
    $stmt_max = $koneksi->query("SELECT MAX(tanggal) FROM transaksi_gudang");
    $max_date = $stmt_max->fetchColumn();
    $selected_bulan = $max_date ? date('Y-m', strtotime($max_date)) : date('Y-m');
}

// Build Query Conditions
$where_clause = "WHERE jenis_transaksi = 'keluar'";
$params = [];

if ($selected_afdeling) {
    $where_clause .= " AND id_afdeling = :afdeling";
    $params[':afdeling'] = $selected_afdeling;
}

// Stats Queries
try {
    // 1. Total Hari Ini
    $sql_hari = "SELECT SUM(jumlah) FROM transaksi_gudang $where_clause AND tanggal = CURDATE()";
    $stmt = $koneksi->prepare($sql_hari);
    $stmt->execute($params);
    $total_hari_ini = $stmt->fetchColumn() ?: 0;

    // 2. Total Bulan Ini
    $sql_bulan = "SELECT SUM(jumlah) FROM transaksi_gudang $where_clause AND DATE_FORMAT(tanggal, '%Y-%m') = :bulan";
    $params_bulan = $params;
    $params_bulan[':bulan'] = $selected_bulan;
    $stmt = $koneksi->prepare($sql_bulan);
    $stmt->execute($params_bulan);
    $total_bulan_ini = $stmt->fetchColumn() ?: 0;

    // 3. Total Tahun Ini
    $sql_tahun = "SELECT SUM(jumlah) FROM transaksi_gudang $where_clause AND YEAR(tanggal) = :tahun";
    $params_tahun = $params;
    $params_tahun[':tahun'] = date('Y', strtotime($selected_bulan));
    $stmt = $koneksi->prepare($sql_tahun);
    $stmt->execute($params_tahun);
    $total_tahun_ini = $stmt->fetchColumn() ?: 0;

    // 4. Afdeling Aktif (yang melakukan transaksi bulan ini)
    $sql_aktif = "SELECT COUNT(DISTINCT id_afdeling) FROM transaksi_gudang WHERE jenis_transaksi = 'keluar' AND DATE_FORMAT(tanggal, '%Y-%m') = :bulan";
    $stmt = $koneksi->prepare($sql_aktif);
    $stmt->execute([':bulan' => $selected_bulan]);
    $afdeling_aktif = $stmt->fetchColumn() ?: 0;

    // 5. Chart Data: Daily (Last 7 Days)
    $daily_labels = [];
    $daily_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $daily_labels[] = date('d M', strtotime($date));
        
        $sql_chart = "SELECT SUM(jumlah) FROM transaksi_gudang $where_clause AND tanggal = :date";
        $params_chart = $params;
        $params_chart[':date'] = $date;
        $stmt = $koneksi->prepare($sql_chart);
        $stmt->execute($params_chart);
        $daily_data[] = $stmt->fetchColumn() ?: 0;
    }

    // 6. Chart Data: Monthly (Current Year)
    $monthly_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
    $monthly_data = [];
    $current_year = date('Y', strtotime($selected_bulan));
    for ($m = 1; $m <= 12; $m++) {
        $month_str = sprintf("%02d", $m);
        $sql_chart = "SELECT SUM(jumlah) FROM transaksi_gudang $where_clause AND DATE_FORMAT(tanggal, '%Y-%m') = :month";
        $params_chart = $params;
        $params_chart[':month'] = "$current_year-$month_str";
        $stmt = $koneksi->prepare($sql_chart);
        $stmt->execute($params_chart);
        $monthly_data[] = $stmt->fetchColumn() ?: 0;
    }

    // 7. Chart Data: Yearly (Last 5 Years)
    $yearly_labels = [];
    $yearly_data = [];
    for ($y = $current_year - 4; $y <= $current_year; $y++) {
        $yearly_labels[] = (string)$y;
        $sql_chart = "SELECT SUM(jumlah) FROM transaksi_gudang $where_clause AND YEAR(tanggal) = :year";
        $params_chart = $params;
        $params_chart[':year'] = $y;
        $stmt = $koneksi->prepare($sql_chart);
        $stmt->execute($params_chart);
        $yearly_data[] = $stmt->fetchColumn() ?: 0;
    }

    // 8. Recent Transactions Table
    $sql_table = "SELECT t.*, a.nama_afdeling, b.nama_barang, b.satuan 
                  FROM transaksi_gudang t 
                  JOIN afdeling a ON t.id_afdeling = a.id 
                  JOIN barang b ON t.id_barang = b.id 
                  $where_clause 
                  ORDER BY t.tanggal DESC, t.id DESC LIMIT 5";
    $stmt = $koneksi->prepare($sql_table);
    $stmt->execute($params);
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PTPN I Regional 3</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            
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
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4>Selamat Datang, <?php echo $_SESSION['nama_lengkap']; ?>! 👋</h4>
                    <p>Berikut adalah ringkasan aktivitas pemakaian barang di Kebun Siluwok.</p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                    <span style="font-size: 0.9rem; background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px;">
                        <i class="fas fa-calendar-alt me-2"></i> <?php echo date('d F Y'); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Content Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="m-0 fw-bold text-secondary"><i class="fas fa-chart-pie me-2"></i> Overview Statistik</h5>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card card-gradient-orange">
                    <div class="stats-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stats-info">
                        <h6>Total Hari Ini</h6>
                        <h3><?php echo number_format($total_hari_ini); ?> <span style="font-size: 0.8rem; font-weight: 400;">Unit</span></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card card-gradient-blue">
                    <div class="stats-icon"><i class="fas fa-calendar-week"></i></div>
                    <div class="stats-info">
                        <h6>Total Bulan Ini</h6>
                        <h3><?php echo number_format($total_bulan_ini); ?> <span style="font-size: 0.8rem; font-weight: 400;">Unit</span></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card card-gradient-purple">
                    <div class="stats-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stats-info">
                        <h6>Total Tahun Ini</h6>
                        <h3><?php echo number_format($total_tahun_ini); ?> <span style="font-size: 0.8rem; font-weight: 400;">Unit</span></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card card-gradient-green">
                    <div class="stats-icon"><i class="fas fa-tree"></i></div>
                    <div class="stats-info">
                        <h6>Afdeling Aktif</h6>
                        <h3><?php echo $afdeling_aktif; ?> <span style="font-size: 0.8rem; font-weight: 400;">Afdeling</span></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Filter Afdeling:</label>
                    <select name="afdeling" class="form-select">
                        <option value="">Semua Afdeling</option>
                        <?php foreach($afdelings as $afd): ?>
                            <option value="<?php echo $afd['id']; ?>" <?php echo $selected_afdeling == $afd['id'] ? 'selected' : ''; ?>>
                                <?php echo $afd['nama_afdeling']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Pilih Periode:</label>
                    <input type="month" name="bulan" class="form-control" value="<?php echo $selected_bulan; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100"><i class="fas fa-filter"></i> Terapkan Filter</button>
                </div>
            </form>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="chart-container">
                    <h6 class="fw-bold mb-3 text-center">Pemakaian 7 Hari Terakhir</h6>
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <h6 class="fw-bold mb-3 text-center">Pemakaian Per Bulan (<?php echo $current_year; ?>)</h6>
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <h6 class="fw-bold mb-3 text-center">Pemakaian Per Tahun</h6>
                    <canvas id="yearlyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold">5 Transaksi Pemakaian Terakhir</h5>
                <div>
                    <a href="export_dashboard.php?bulan=<?php echo $selected_bulan; ?>&afdeling=<?php echo $selected_afdeling; ?>&type=excel" target="_blank" class="btn btn-success btn-sm">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </a>
                    <a href="export_dashboard.php?bulan=<?php echo $selected_bulan; ?>&afdeling=<?php echo $selected_afdeling; ?>&type=pdf" target="_blank" class="btn btn-danger btn-sm">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Afdeling</th>
                            <th>Nama Barang</th>
                            <th>Jumlah Pakai</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($recent_transactions)): ?>
                            <tr><td colspan="5" class="text-center">Tidak ada data transaksi</td></tr>
                        <?php else: ?>
                            <?php foreach($recent_transactions as $row): ?>
                            <tr>
                                <td><?php echo date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                                <td><?php echo $row['nama_afdeling']; ?></td>
                                <td><?php echo $row['nama_barang']; ?></td>
                                <td><?php echo number_format($row['jumlah'], 0); ?> <?php echo $row['satuan']; ?></td>
                                <td><?php echo $row['keterangan']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Script for Charts -->
    <script>
        // Toggle Sidebar Mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('active');
        });

        // Daily Chart
        const ctxDaily = document.getElementById('dailyChart').getContext('2d');
        new Chart(ctxDaily, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($daily_labels); ?>,
                datasets: [{
                    label: 'Pemakaian',
                    data: <?php echo json_encode($daily_data); ?>,
                    borderColor: '#ff9800',
                    tension: 0.4,
                    fill: false
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Monthly Chart
        const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctxMonthly, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Pemakaian',
                    data: <?php echo json_encode($monthly_data); ?>,
                    backgroundColor: '#2196f3'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Yearly Chart
        const ctxYearly = document.getElementById('yearlyChart').getContext('2d');
        new Chart(ctxYearly, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($yearly_labels); ?>,
                datasets: [{
                    label: 'Pemakaian',
                    data: <?php echo json_encode($yearly_data); ?>,
                    backgroundColor: '#4caf50'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
