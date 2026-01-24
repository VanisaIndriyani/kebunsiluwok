<?php
session_start();
require_once 'config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle CRUD Operations
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] == 'add') {
                $stmt = $koneksi->prepare("INSERT INTO transaksi_gudang (tanggal, id_afdeling, id_barang, no_bukti, nama_mandor, jenis_transaksi, jumlah, keterangan, keterangan_lain) VALUES (:tgl, :afd, :brg, :nobukti, :mandor, :jenis, :jml, :ket, :ket_lain)");
                $stmt->execute([
                    ':tgl' => $_POST['tanggal'],
                    ':afd' => $_POST['id_afdeling'],
                    ':brg' => $_POST['id_barang'],
                    ':nobukti' => $_POST['no_bukti'],
                    ':mandor' => $_POST['nama_mandor'],
                    ':jenis' => $_POST['jenis_transaksi'],
                    ':jml' => $_POST['jumlah'],
                    ':ket' => $_POST['keterangan'],
                    ':ket_lain' => $_POST['keterangan_lain'],
                    ':ttd' => $ttd_path
                ]);
                $message = '<div class="alert alert-success">Data berhasil ditambahkan!</div>';
            } elseif ($_POST['action'] == 'edit') {
                // Handle File Upload
                $ttd_sql = "";
                $params = [
                    ':tgl' => $_POST['tanggal'],
                    ':nobukti' => $_POST['no_bukti'],
                    ':mandor' => $_POST['nama_mandor'],
                    ':jenis' => $_POST['jenis_transaksi'],
                    ':jml' => $_POST['jumlah'],
                    ':ket' => $_POST['keterangan'],
                    ':ket_lain' => $_POST['keterangan_lain'],
                    ':id' => $_POST['id']
                ];

                if (isset($_FILES['ttd_asisten']) && $_FILES['ttd_asisten']['error'] == 0) {
                    $target_dir = "assets/img/ttd/";
                    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                    $file_ext = strtolower(pathinfo($_FILES["ttd_asisten"]["name"], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    if (in_array($file_ext, $allowed)) {
                        $new_filename = uniqid() . '.' . $file_ext;
                        if (move_uploaded_file($_FILES["ttd_asisten"]["tmp_name"], $target_dir . $new_filename)) {
                            $ttd_sql = ", ttd_asisten=:ttd";
                            $params[':ttd'] = $new_filename;
                        }
                    }
                }

                $stmt = $koneksi->prepare("UPDATE transaksi_gudang SET tanggal=:tgl, no_bukti=:nobukti, nama_mandor=:mandor, jenis_transaksi=:jenis, jumlah=:jml, keterangan=:ket, keterangan_lain=:ket_lain $ttd_sql WHERE id=:id");
                $stmt->execute($params);
                $message = '<div class="alert alert-success">Data berhasil diupdate!</div>';
            } elseif ($_POST['action'] == 'delete') {
                $stmt = $koneksi->prepare("DELETE FROM transaksi_gudang WHERE id=:id");
                $stmt->execute([':id' => $_POST['id']]);
                $message = '<div class="alert alert-success">Data berhasil dihapus!</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Fetch Master Data for Filters
$stmt_afdeling = $koneksi->query("SELECT * FROM afdeling");
$afdelings = $stmt_afdeling->fetchAll(PDO::FETCH_ASSOC);

$stmt_barang = $koneksi->query("SELECT * FROM barang");
$barangs = $stmt_barang->fetchAll(PDO::FETCH_ASSOC);

// Handle Filter
$selected_afdeling = isset($_GET['afdeling']) ? $_GET['afdeling'] : (isset($afdelings[0]['id']) ? $afdelings[0]['id'] : '');
$selected_barang = isset($_GET['barang']) ? $_GET['barang'] : (isset($barangs[0]['id']) ? $barangs[0]['id'] : '');

// Determine default month (latest transaction month or current month)
if (isset($_GET['bulan'])) {
    $selected_bulan = $_GET['bulan'];
} else {
    $stmt_max = $koneksi->query("SELECT MAX(tanggal) FROM transaksi_gudang");
    $max_date = $stmt_max->fetchColumn();
    $selected_bulan = $max_date ? date('Y-m', strtotime($max_date)) : date('Y-m');
}

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Gudang - PTPN I Regional 3</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo filemtime('assets/css/dashboard.css'); ?>">
    <style>
        :root {
            --primary-color: #2e7d32;
            --secondary-color: #4caf50;
            --accent-color: #81c784;
            --bg-color: #f0f2f5;
            --card-bg: rgba(255, 255, 255, 0.9);
        }
        body {
            background-color: var(--bg-color);
            font-family: 'Poppins', sans-serif;
        }
        .main-content {
            padding: 30px;
        }
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.18);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .filter-card {
            background: linear-gradient(145deg, #ffffff, #f5f5f5);
            border-radius: 20px;
            box-shadow: 5px 5px 15px #d1d1d1, -5px -5px 15px #ffffff;
            border: none;
            margin-bottom: 25px;
            padding: 25px;
            transition: transform 0.2s;
        }
        .filter-card:hover {
            transform: translateY(-5px);
        }
        .table-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
        }
        .table-custom {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-custom thead th {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            border: none;
            padding: 18px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            vertical-align: middle;
            text-align: center;
        }
        .table-custom thead th:first-child {
            border-top-left-radius: 15px;
        }
        .table-custom thead th:last-child {
            border-top-right-radius: 15px;
        }
        .table-custom tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.95rem;
            color: #444;
        }
        .table-custom tbody tr {
            transition: all 0.2s;
        }
        .table-custom tbody tr:hover {
            background-color: #f1f8e9;
            transform: scale(1.005);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            z-index: 10;
            position: relative;
        }
        .info-label {
            color: #6c757d;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-value {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        .page-header {
            background: linear-gradient(120deg, #1b5e20, #43a047);
            padding: 35px;
            border-radius: 20px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(27, 94, 32, 0.25);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            transform: rotate(45deg);
        }
        .btn-custom-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(46, 125, 50, 0.2);
        }
        .btn-custom-primary:hover {
            background: #1b5e20;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(46, 125, 50, 0.3);
        }
        .form-control, .form-select {
            border-radius: 12px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            background-color: #fdfdfd;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.1);
            border-color: var(--secondary-color);
            background-color: #fff;
        }
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        .modal-header {
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            padding: 20px 30px;
        }
        .modal-body {
            padding: 30px;
        }
        .modal-footer {
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            padding: 20px 30px;
            border-top: none;
            background-color: #f8f9fa;
        }
        .ttd-img {
            max-height: 60px;
            border-radius: 8px;
            padding: 4px;
            border: 2px dashed #e0e0e0;
            background: #fff;
            transition: transform 0.3s;
        }
        .ttd-img:hover {
            transform: scale(1.5);
            z-index: 100;
            position: relative;
            border-color: var(--primary-color);
        }
        .badge-soft-success {
            background-color: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
        }
        .badge-soft-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        .badge-soft-primary {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        .page-link {
            color: var(--primary-color);
            border: none;
            margin: 0 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            transition: all 0.3s;
        }
        .page-link:hover {
            background-color: #e8f5e9;
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
        }
        .page-item.disabled .page-link {
            background-color: transparent;
            color: #ccc;
        }
    </style>
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

            <li><a href="kartu_gudang.php" class="active"><i class="fas fa-boxes"></i> Kartu Gudang</a></li>
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

        <?php echo $message; ?>

        <!-- Content Header -->
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="m-0 fw-bold">Kartu Gudang Afdeling</h4>
                <p class="m-0 opacity-75">Kelola dan pantau mutasi barang gudang</p>
            </div>
            <div>
                <button type="button" class="btn btn-light text-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus me-2"></i>Input Transaksi
                </button>
            </div>
        </div>

        <!-- Filter & Info Section -->
        <div class="row">
            <div class="col-md-12">
                <div class="filter-card glass-card">
                    <form method="GET" class="row g-3 align-items-end mb-4">
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-bold small text-uppercase"><i class="fas fa-building me-2"></i>Afdeling</label>
                            <select name="afdeling" class="form-select bg-light shadow-sm" onchange="this.form.submit()">
                                <?php foreach($afdelings as $afd): ?>
                                    <option value="<?php echo $afd['id']; ?>" <?php echo $selected_afdeling == $afd['id'] ? 'selected' : ''; ?>>
                                        <?php echo $afd['nama_afdeling']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-bold small text-uppercase"><i class="fas fa-box me-2"></i>Barang</label>
                            <select name="barang" class="form-select bg-light shadow-sm" onchange="this.form.submit()">
                                <?php foreach($barangs as $brg): ?>
                                    <option value="<?php echo $brg['id']; ?>" <?php echo $selected_barang == $brg['id'] ? 'selected' : ''; ?>>
                                        <?php echo $brg['nama_barang']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-bold small text-uppercase"><i class="fas fa-calendar me-2"></i>Bulan</label>
                            <input type="month" name="bulan" class="form-control bg-light shadow-sm" value="<?php echo $selected_bulan; ?>" onchange="this.form.submit()">
                        </div>
                    </form>

                    <?php if ($info_barang && $info_afdeling): ?>
                    <div class="row g-4 pt-3 border-top">
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center p-2 rounded hover-bg">
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3 text-success shadow-sm">
                                    <i class="fas fa-cube fa-lg"></i>
                                </div>
                                <div>
                                    <div class="info-label">Nama Barang</div>
                                    <div class="info-value text-dark"><?php echo $info_barang['nama_barang']; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center p-2 rounded hover-bg">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3 text-primary shadow-sm">
                                    <i class="fas fa-barcode fa-lg"></i>
                                </div>
                                <div>
                                    <div class="info-label">Kode Barang</div>
                                    <div class="info-value text-dark"><?php echo $info_barang['kode_barang']; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center p-2 rounded hover-bg">
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle me-3 text-warning shadow-sm">
                                    <i class="fas fa-balance-scale fa-lg"></i>
                                </div>
                                <div>
                                    <div class="info-label">Satuan</div>
                                    <div class="info-value text-dark"><?php echo $info_barang['satuan']; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center p-2 rounded hover-bg">
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle me-3 text-info shadow-sm">
                                    <i class="far fa-calendar-alt fa-lg"></i>
                                </div>
                                <div>
                                    <div class="info-label">Periode</div>
                                    <div class="info-value text-dark"><?php echo date('F Y', strtotime($selected_bulan)); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="table-card p-4 glass-card">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                <h5 class="fw-bold m-0 text-secondary align-self-start align-self-md-center"><i class="fas fa-list me-2"></i> Riwayat Transaksi</h5>
                <div class="d-flex flex-column flex-md-row gap-2 w-100 w-md-auto">
                    <input type="text" id="searchInput" onkeyup="searchTable()" class="form-control form-control-sm shadow-sm" placeholder="Cari data..." style="border-radius: 20px; min-width: 250px;">
                    <div class="d-flex gap-2">
                        <a href="export_kartu.php?afdeling=<?php echo $selected_afdeling; ?>&barang=<?php echo $selected_barang; ?>&bulan=<?php echo $selected_bulan; ?>&type=excel" target="_blank" class="btn btn-success btn-sm shadow-sm rounded-pill px-3 flex-fill text-nowrap"><i class="fas fa-file-excel me-1"></i> Excel</a>
                        <a href="export_kartu.php?afdeling=<?php echo $selected_afdeling; ?>&barang=<?php echo $selected_barang; ?>&bulan=<?php echo $selected_bulan; ?>&type=pdf" target="_blank" class="btn btn-danger btn-sm shadow-sm rounded-pill px-3 flex-fill text-nowrap"><i class="fas fa-file-pdf me-1"></i> PDF</a>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="kartuTable" class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th rowspan="2">No. Bukti Penerimaan</th>
                            <th rowspan="2">Tanggal</th>
                            <th rowspan="2">Nama Mandor yang mengambil</th>
                            <th rowspan="2">Dipakai untuk diterima dari</th>
                            <th colspan="3">Banyaknya</th>
                            <th rowspan="2">Keterangan</th>
                            <th rowspan="2">Tanda Tangan Asisten Afd./Wakil Asisten Afd</th>
                            <th rowspan="2" width="10%">Aksi</th>
                        </tr>
                        <tr>
                            <th>Masuk</th>
                            <th>Keluar</th>
                            <th>Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Saldo Awal Row -->
                        <tr class="bg-light">
                            <td colspan="6" class="text-end fw-bold text-secondary">Saldo Awal</td>
                            <td class="text-center fw-bold"><?php echo number_format($stok_awal, 2); ?></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>

                        <?php 
                        $no = 1;
                        $sisa = $stok_awal; 
                        if (empty($transaksi)) {
                            echo "<tr><td colspan='9' class='text-center py-5 text-muted'><i class='fas fa-inbox fa-3x mb-3 d-block opacity-25'></i>Tidak ada data transaksi pada periode ini</td></tr>";
                        } else {
                            foreach ($transaksi as $row) {
                                $masuk = ($row['jenis_transaksi'] == 'masuk') ? $row['jumlah'] : 0;
                                $keluar = ($row['jenis_transaksi'] == 'keluar') ? $row['jumlah'] : 0;
                                $sisa = $sisa + $masuk - $keluar;
                        ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border shadow-sm rounded-pill px-3 py-2"><?php echo $row['no_bukti']; ?></span></td>
                            <td>
                                <div class="fw-medium text-dark"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></div>
                            </td>
                            <td>
                                <?php if($row['nama_mandor']): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center" style="width:30px;height:30px;">
                                            <i class="fas fa-user text-secondary" style="font-size: 0.8em;"></i>
                                        </div>
                                        <span><?php echo $row['nama_mandor']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?php echo $row['keterangan']; ?></td>
                            <td class="text-center">
                                <?php if($masuk > 0): ?>
                                    <span class="badge badge-soft-success rounded-pill px-3">+ <?php echo number_format($masuk, 2); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($keluar > 0): ?>
                                    <span class="badge badge-soft-danger rounded-pill px-3">- <?php echo number_format($keluar, 2); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-soft-primary rounded-pill px-3"><?php echo number_format($sisa, 2); ?></span>
                            </td>
                            <td class="text-muted small"><?php echo $row['keterangan_lain']; ?></td>
                            <td class="text-center">
                                <?php if (!empty($row['ttd_asisten'])): ?>
                                    <img src="assets/img/ttd/<?php echo $row['ttd_asisten']; ?>" alt="TTD" class="ttd-img shadow-sm">
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Manual</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group shadow-sm rounded-pill" role="group">
                                    <button class="btn btn-light btn-sm text-warning btn-edit px-3" 
                                        data-id="<?php echo $row['id']; ?>"
                                        data-tanggal="<?php echo $row['tanggal']; ?>"
                                        data-nobukti="<?php echo $row['no_bukti']; ?>"
                                        data-mandor="<?php echo $row['nama_mandor']; ?>"
                                        data-jenis="<?php echo $row['jenis_transaksi']; ?>"
                                        data-jumlah="<?php echo $row['jumlah']; ?>"
                                        data-keterangan="<?php echo $row['keterangan']; ?>"
                                        data-keterangan_lain="<?php echo $row['keterangan_lain']; ?>"
                                        data-ttd="<?php echo $row['ttd_asisten']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-light btn-sm text-danger btn-delete px-3" 
                                        data-id="<?php echo $row['id']; ?>" 
                                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                                        title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            }
                        } 
                        ?>
                    </tbody>
                    <tfoot class="bg-light fw-bold">
                        <tr>
                            <td colspan="4" class="text-end text-secondary text-uppercase py-3">Total Mutasi Bulan Ini</td>
                            <td class="text-center text-success py-3"><?php echo number_format($total_masuk_periode, 2); ?></td>
                            <td class="text-center text-danger py-3"><?php echo number_format($total_keluar_periode, 2); ?></td>
                            <td class="text-center text-primary py-3"><?php echo number_format($sisa, 2); ?></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small>Showing 1 to <?php echo count($transaksi); ?> of <?php echo count($transaksi); ?> entries</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">Next</a></li>
                    </ul>
                </nav>
            </div>
        </div>

    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content glass-card border-0">
                <div class="modal-header bg-success text-white border-0 shadow-sm">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Input Transaksi Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id_afdeling" value="<?php echo $selected_afdeling; ?>">
                        <input type="hidden" name="id_barang" value="<?php echo $selected_barang; ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" name="tanggal" class="form-control" id="add_tanggal" required value="<?php echo date('Y-m-d'); ?>">
                                    <label for="add_tanggal">Tanggal Transaksi</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select name="jenis_transaksi" class="form-select" id="add_jenis" required onchange="toggleMandor(this.value, 'add')">
                                        <option value="keluar">Keluar (Pemakaian)</option>
                                        <option value="masuk">Masuk (Penerimaan)</option>
                                    </select>
                                    <label for="add_jenis">Jenis Transaksi</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" name="no_bukti" class="form-control" id="add_nobukti" required placeholder="Contoh: BKK/001">
                                    <label for="add_nobukti">No. Bukti Penerimaan</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="number" step="0.01" name="jumlah" class="form-control" id="add_jumlah" required placeholder="0">
                                    <label for="add_jumlah">Jumlah Barang</label>
                                </div>
                            </div>
                            <div class="col-12" id="mandorGroup_add">
                                <div class="form-floating mb-3">
                                    <input type="text" name="nama_mandor" class="form-control" id="add_mandor" placeholder="Nama Mandor">
                                    <label for="add_mandor">Nama Mandor yang mengambil</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <textarea name="keterangan" class="form-control" id="add_keterangan" style="height: 100px" placeholder="Keterangan"></textarea>
                                    <label for="add_keterangan">Dipakai untuk / Diterima dari</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <textarea name="keterangan_lain" class="form-control" id="add_ket_lain" style="height: 80px" placeholder="Catatan"></textarea>
                                    <label for="add_ket_lain">Keterangan Tambahan</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted small text-uppercase">Upload Tanda Tangan (Opsional)</label>
                                <div class="input-group">
                                    <input type="file" name="ttd_asisten" class="form-control" accept="image/*" id="add_ttd">
                                    <label class="input-group-text" for="add_ttd"><i class="fas fa-upload"></i></label>
                                </div>
                                <div class="form-text text-muted small"><i class="fas fa-info-circle me-1"></i>Biarkan kosong jika menggunakan tanda tangan manual (basah).</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-light text-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm"><i class="fas fa-save me-2"></i>Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content glass-card border-0">
                <div class="modal-header bg-warning text-dark border-0 shadow-sm">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" name="tanggal" id="edit_tanggal" class="form-control" required>
                                    <label for="edit_tanggal">Tanggal Transaksi</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select name="jenis_transaksi" id="edit_jenis" class="form-select" required onchange="toggleMandor(this.value, 'edit')">
                                        <option value="keluar">Keluar (Pemakaian)</option>
                                        <option value="masuk">Masuk (Penerimaan)</option>
                                    </select>
                                    <label for="edit_jenis">Jenis Transaksi</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" name="no_bukti" id="edit_nobukti" class="form-control" required>
                                    <label for="edit_nobukti">No. Bukti Penerimaan</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="number" step="0.01" name="jumlah" id="edit_jumlah" class="form-control" required>
                                    <label for="edit_jumlah">Jumlah Barang</label>
                                </div>
                            </div>
                            <div class="col-12" id="mandorGroup_edit">
                                <div class="form-floating mb-3">
                                    <input type="text" name="nama_mandor" id="edit_mandor" class="form-control" placeholder="Nama Mandor">
                                    <label for="edit_mandor">Nama Mandor yang mengambil</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <textarea name="keterangan" id="edit_keterangan" class="form-control" style="height: 100px" placeholder="Keterangan"></textarea>
                                    <label for="edit_keterangan">Dipakai untuk / Diterima dari</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <textarea name="keterangan_lain" id="edit_keterangan_lain" class="form-control" style="height: 80px" placeholder="Catatan"></textarea>
                                    <label for="edit_keterangan_lain">Keterangan Tambahan</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-muted small text-uppercase">Upload Tanda Tangan (Opsional)</label>
                                <div id="edit_ttd_preview_container" class="mb-3 p-3 bg-light rounded text-center border" style="display:none;">
                                    <img id="edit_ttd_preview" src="" alt="Preview TTD" class="img-fluid shadow-sm rounded" style="max-height: 100px;">
                                    <div class="mt-2 text-muted small fst-italic">Tanda tangan saat ini</div>
                                </div>
                                <div class="input-group">
                                    <input type="file" name="ttd_asisten" class="form-control" accept="image/*" id="edit_ttd">
                                    <label class="input-group-text" for="edit_ttd"><i class="fas fa-upload"></i></label>
                                </div>
                                <div class="form-text text-muted small"><i class="fas fa-info-circle me-1"></i>Upload file baru untuk mengganti. Biarkan kosong jika tidak ingin mengubah.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-light text-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning fw-bold px-4 shadow-sm"><i class="fas fa-sync-alt me-2"></i>Update Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-0">
                <div class="modal-header bg-danger text-white border-0 shadow-sm">
                    <h5 class="modal-title fw-bold"><i class="fas fa-trash-alt me-2"></i>Hapus Transaksi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body text-center py-4">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <div class="mb-3 text-danger opacity-50">
                            <i class="fas fa-exclamation-triangle fa-4x"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Apakah Anda yakin?</h5>
                        <p class="text-muted mb-0">Data transaksi yang dihapus tidak dapat dikembalikan lagi.</p>
                    </div>
                    <div class="modal-footer border-0 bg-light justify-content-center">
                        <button type="button" class="btn btn-light text-secondary fw-bold px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger fw-bold px-4 shadow-sm"><i class="fas fa-trash me-2"></i>Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Export Excel
        function exportTableToExcel(tableID, filename = ''){
            var downloadLink;
            var dataType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById(tableID);
            var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            
            filename = filename?filename+'.xls':'excel_data.xls';
            
            downloadLink = document.createElement("a");
            
            document.body.appendChild(downloadLink);
            
            if(navigator.msSaveOrOpenBlob){
                var blob = new Blob(['\ufeff', tableHTML], {
                    type: dataType
                });
                navigator.msSaveOrOpenBlob( blob, filename);
            }else{
                downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                downloadLink.download = filename;
                downloadLink.click();
            }
        }

        // Sidebar Toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('active');
        });

        // Search Function
        function searchTable() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("kartuTable");
            tr = table.getElementsByTagName("tr");
            for (i = 0; i < tr.length; i++) {
                // Search in all columns
                var found = false;
                var tds = tr[i].getElementsByTagName("td");
                for(var j=0; j<tds.length; j++){
                    if(tds[j]){
                        txtValue = tds[j].textContent || tds[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                if(i > 1 && !found){ // Skip header rows (index 0 and 1)
                     tr[i].style.display = "none";
                } else if (i > 1 && found) {
                     tr[i].style.display = "";
                }
            }
        }

        // Toggle Mandor Input
        function toggleMandor(jenis, mode) {
            const group = document.getElementById('mandorGroup_' + mode);
            const input = group.querySelector('input');
            if (jenis === 'masuk') {
                group.style.display = 'none';
                input.value = '';
            } else {
                group.style.display = 'block';
            }
        }

        // Fill Edit Modal
        document.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit_id').value = this.dataset.id;
                document.getElementById('edit_tanggal').value = this.dataset.tanggal;
                document.getElementById('edit_nobukti').value = this.dataset.nobukti;
                document.getElementById('edit_mandor').value = this.dataset.mandor;
                document.getElementById('edit_jenis').value = this.dataset.jenis;
                document.getElementById('edit_jumlah').value = this.dataset.jumlah;
                document.getElementById('edit_keterangan').value = this.dataset.keterangan;
                document.getElementById('edit_keterangan_lain').value = this.dataset.keterangan_lain;
                
                // Handle TTD Preview
                const ttdFile = this.dataset.ttd;
                const previewContainer = document.getElementById('edit_ttd_preview_container');
                const previewImg = document.getElementById('edit_ttd_preview');
                
                if (ttdFile) {
                    previewImg.src = 'assets/img/ttd/' + ttdFile;
                    previewContainer.style.display = 'block';
                } else {
                    previewImg.src = '';
                    previewContainer.style.display = 'none';
                }
                
                toggleMandor(this.dataset.jenis, 'edit');
            });
        });

        // Fill Delete Modal
        document.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('delete_id').value = this.dataset.id;
            });
        });

        // Init Toggle on Load
        document.addEventListener('DOMContentLoaded', function() {
            toggleMandor('keluar', 'add');
        });
    </script>
</body>
</html>
