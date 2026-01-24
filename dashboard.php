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

    // 4. Afdeling Aktif (Total Afdeling)
    $sql_aktif = "SELECT COUNT(*) FROM afdeling";
    $stmt = $koneksi->prepare($sql_aktif);
    $stmt->execute();
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
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo filemtime('assets/css/dashboard.css'); ?>">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="assets/img/log.png" alt="Logo" style="width: 40px;">
            <a href="#" class="sidebar-brand">KEBUN SILUWOK</a>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            
            <!-- Data Master Menu -->
            <li>
                <a href="#submenuDataMaster" data-bs-toggle="collapse" class="d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-database"></i> Data Master</span>
                    <i class="fas fa-chevron-down" style="font-size: 0.8rem;"></i>
                </a>
                <ul class="collapse sidebar-submenu" id="submenuDataMaster">
                    <li><a href="barang.php"><i class="fas fa-box-open"></i> Barang</a></li>
                    <li><a href="afdeling.php"><i class="fas fa-building"></i> Afdeling</a></li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <li><a href="akun.php"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
                    <?php endif; ?>
                </ul>
            </li>

            <li><a href="kartu_gudang.php"><i class="fas fa-boxes"></i> Kartu Gudang</a></li>
            <li><a href="stok_masuk.php"><i class="fas fa-arrow-down"></i> Stok Masuk</a></li>
            <li><a href="stok_keluar.php"><i class="fas fa-arrow-up"></i> Stok Keluar</a></li>
            <li>
                <a href="#submenuLaporan" data-bs-toggle="collapse" class="d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-file-alt"></i> Laporan</span>
                    <i class="fas fa-chevron-down" style="font-size: 0.8rem;"></i>
                </a>
                <ul class="collapse sidebar-submenu" id="submenuLaporan">
                    <li><a href="laporan_mutasi.php"><i class="fas fa-exchange-alt"></i> Mutasi Stok</a></li>
                    <li><a href="laporan_stok.php"><i class="fas fa-clipboard-list"></i> Sisa Stok</a></li>
                </ul>
            </li>
            <li style="margin-top: 50px;">
                <a href="logout.php" style="color: #dc3545;"><i class="fas fa-sign-out-alt"></i> Keluar</a>
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
                    <h5 class="m-0 fw-bold text-success">Dashboard Overview</h5>
                    <p class="m-0 text-muted" style="font-size: 0.85rem;">PT. Perkebunan Nusantara I Regional 3</p>
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
        <div class="welcome-banner animate-fade-in delay-1">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="fw-bold mb-2">Selamat Datang, <?php echo $_SESSION['nama_lengkap']; ?>! 👋</h4>
                    <p class="mb-0 opacity-75">Berikut adalah ringkasan aktivitas pemakaian barang di Kebun Siluwok hari ini.</p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                    <span style="background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 30px; font-weight: 500;">
                        <i class="fas fa-calendar-alt me-2"></i> <?php echo date('d F Y'); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4 animate-fade-in delay-2">
                <div class="stats-card bg-gradient-1">
                    <div class="stats-icon"><i class="fas fa-box-open"></i></div>
                    <div class="stats-label">Total Hari Ini</div>
                    <div class="stats-value"><?php echo number_format($total_hari_ini); ?></div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">Unit Barang Keluar</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4 animate-fade-in delay-2">
                <div class="stats-card bg-gradient-2">
                    <div class="stats-icon"><i class="fas fa-calendar-week"></i></div>
                    <div class="stats-label">Total Bulan Ini</div>
                    <div class="stats-value"><?php echo number_format($total_bulan_ini); ?></div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">Unit Barang Keluar</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4 animate-fade-in delay-2">
                <div class="stats-card bg-gradient-3">
                    <div class="stats-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stats-label">Total Tahun Ini</div>
                    <div class="stats-value"><?php echo number_format($total_tahun_ini); ?></div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">Unit Barang Keluar</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4 animate-fade-in delay-2">
                <div class="stats-card bg-gradient-4">
                    <div class="stats-icon"><i class="fas fa-tree"></i></div>
                    <div class="stats-label">Total Afdeling</div>
                    <div class="stats-value"><?php echo $afdeling_aktif; ?></div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">Afdeling Aktif</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="glass-card animate-fade-in delay-3">
            <div class="section-title">
                <i class="fas fa-filter"></i> Filter Data
            </div>
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <div class="form-floating">
                        <select name="afdeling" class="form-select" id="afdelingSelect">
                            <option value="">Semua Afdeling</option>
                            <?php foreach($afdelings as $afd): ?>
                                <option value="<?php echo $afd['id']; ?>" <?php echo $selected_afdeling == $afd['id'] ? 'selected' : ''; ?>>
                                    <?php echo $afd['nama_afdeling']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="afdelingSelect">Pilih Afdeling</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="month" name="bulan" class="form-control" id="bulanSelect" value="<?php echo $selected_bulan; ?>">
                        <label for="bulanSelect">Pilih Periode</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn-filter w-100">
                        <i class="fas fa-search"></i> Tampilkan
                    </button>
                </div>
            </form>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-lg-8 animate-fade-in delay-3">
                <div class="glass-card">
                    <div class="section-title">
                        <i class="fas fa-chart-line"></i> Grafik Pemakaian 7 Hari Terakhir
                    </div>
                    <div class="chart-container">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 animate-fade-in delay-3">
                <div class="glass-card">
                    <div class="section-title">
                        <i class="fas fa-chart-bar"></i> Pemakaian Per Tahun
                    </div>
                    <div class="chart-container">
                        <canvas id="yearlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
             <div class="col-12 animate-fade-in delay-3">
                <div class="glass-card">
                    <div class="section-title">
                        <i class="fas fa-chart-area"></i> Statistik Bulanan (<?php echo $current_year; ?>)
                    </div>
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="glass-card animate-fade-in delay-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="section-title mb-0">
                    <i class="fas fa-history"></i> 5 Transaksi Terakhir
                </div>
                <div>
                    <a href="export_dashboard.php?bulan=<?php echo $selected_bulan; ?>&afdeling=<?php echo $selected_afdeling; ?>&type=excel" target="_blank" class="btn btn-success btn-sm rounded-pill px-3">
                        <i class="fas fa-file-excel me-1"></i> Excel
                    </a>
                    <a href="export_dashboard.php?bulan=<?php echo $selected_bulan; ?>&afdeling=<?php echo $selected_afdeling; ?>&type=pdf" target="_blank" class="btn btn-danger btn-sm rounded-pill px-3">
                        <i class="fas fa-file-pdf me-1"></i> PDF
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Afdeling</th>
                            <th>Nama Barang</th>
                            <th>Jumlah</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($recent_transactions)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada data transaksi</td></tr>
                        <?php else: ?>
                            <?php foreach($recent_transactions as $row): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo date('d', strtotime($row['tanggal'])); ?></div>
                                    <div class="small text-muted"><?php echo date('M Y', strtotime($row['tanggal'])); ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo $row['nama_afdeling']; ?></span></td>
                                <td class="fw-bold text-success"><?php echo $row['nama_barang']; ?></td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <?php echo number_format($row['jumlah'], 0); ?> <?php echo $row['satuan']; ?>
                                    </span>
                                </td>
                                <td><?php echo $row['keterangan']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Script for Charts & UI -->
    <script>
        // Toggle Sidebar Mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Chart Configurations
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                x: { grid: { display: false } }
            }
        };

        // Daily Chart
        const ctxDaily = document.getElementById('dailyChart').getContext('2d');
        new Chart(ctxDaily, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($daily_labels); ?>,
                datasets: [{
                    label: 'Pemakaian',
                    data: <?php echo json_encode($daily_data); ?>,
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#4caf50',
                    pointRadius: 5,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: commonOptions
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
                    backgroundColor: '#2196f3',
                    borderRadius: 5
                }]
            },
            options: commonOptions
        });

        // Yearly Chart
        const ctxYearly = document.getElementById('yearlyChart').getContext('2d');
        new Chart(ctxYearly, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($yearly_labels); ?>,
                datasets: [{
                    label: 'Pemakaian',
                    data: <?php echo json_encode($yearly_data); ?>,
                    backgroundColor: [
                        '#4caf50', '#81c784', '#a5d6a7', '#c8e6c9', '#e8f5e9'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
