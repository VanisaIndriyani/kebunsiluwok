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
                $stmt = $koneksi->prepare("INSERT INTO transaksi_gudang (tanggal, id_afdeling, id_barang, no_bukti, nama_mandor, jenis_transaksi, jumlah, keterangan) VALUES (:tgl, :afd, :brg, :nobukti, :mandor, :jenis, :jml, :ket)");
                $stmt->execute([
                    ':tgl' => $_POST['tanggal'],
                    ':afd' => $_POST['id_afdeling'],
                    ':brg' => $_POST['id_barang'],
                    ':nobukti' => $_POST['no_bukti'],
                    ':mandor' => $_POST['nama_mandor'],
                    ':jenis' => $_POST['jenis_transaksi'],
                    ':jml' => $_POST['jumlah'],
                    ':ket' => $_POST['keterangan']
                ]);
                $message = '<div class="alert alert-success">Data berhasil ditambahkan!</div>';
            } elseif ($_POST['action'] == 'edit') {
                $stmt = $koneksi->prepare("UPDATE transaksi_gudang SET tanggal=:tgl, no_bukti=:nobukti, nama_mandor=:mandor, jenis_transaksi=:jenis, jumlah=:jml, keterangan=:ket WHERE id=:id");
                $stmt->execute([
                    ':tgl' => $_POST['tanggal'],
                    ':nobukti' => $_POST['no_bukti'],
                    ':mandor' => $_POST['nama_mandor'],
                    ':jenis' => $_POST['jenis_transaksi'],
                    ':jml' => $_POST['jumlah'],
                    ':ket' => $_POST['keterangan'],
                    ':id' => $_POST['id']
                ]);
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
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: none;
            margin-bottom: 25px;
        }
        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
        }
        .table-custom thead th {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            border: none;
            padding: 15px;
            font-weight: 500;
        }
        .info-detail-label {
            font-size: 0.85rem;
            opacity: 0.8;
            display: block;
        }
        .info-detail-value {
            font-size: 1.1rem;
            font-weight: 600;
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
        <div class="mb-4">
            <div class="p-3 rounded text-white text-center" style="background: #2e7d32;">
                <h4 class="m-0 fw-bold">KARTU GUDANG AFDELING</h4>
            </div>
        </div>

        <!-- Filter & Info Section -->
        <div class="row">
            <div class="col-md-12">
                <div class="info-box">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Afdeling</label>
                            <select name="afdeling" class="form-select" onchange="this.form.submit()">
                                <?php foreach($afdelings as $afd): ?>
                                    <option value="<?php echo $afd['id']; ?>" <?php echo $selected_afdeling == $afd['id'] ? 'selected' : ''; ?>>
                                        <?php echo $afd['nama_afdeling']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Barang</label>
                            <select name="barang" class="form-select" onchange="this.form.submit()">
                                <?php foreach($barangs as $brg): ?>
                                    <option value="<?php echo $brg['id']; ?>" <?php echo $selected_barang == $brg['id'] ? 'selected' : ''; ?>>
                                        <?php echo $brg['nama_barang']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Bulan</label>
                            <input type="month" name="bulan" class="form-control" value="<?php echo $selected_bulan; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3 text-end">
                             <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                                <i class="fas fa-plus"></i> Input Transaksi
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <?php if ($info_barang && $info_afdeling): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2"><span class="info-label">Afdeling</span>: <?php echo $info_afdeling['nama_afdeling']; ?></div>
                            <div class="mb-2"><span class="info-label">Nama Barang</span>: <?php echo $info_barang['nama_barang']; ?></div>
                            <div class="mb-2"><span class="info-label">Satuan</span>: <?php echo $info_barang['satuan']; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2"><span class="info-label">Kode Barang</span>: <?php echo $info_barang['kode_barang']; ?></div>
                            <div class="mb-2"><span class="info-label">Periode</span>: <?php echo date('F Y', strtotime($selected_bulan)); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="table-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0 text-secondary"><i class="fas fa-list me-2"></i> Riwayat Transaksi</h5>
                <div class="d-flex gap-2">
                    <input type="text" id="searchInput" onkeyup="searchTable()" class="form-control form-control-sm" placeholder="Cari data..." style="width: 200px;">
                    <a href="export_kartu.php?afdeling=<?php echo $selected_afdeling; ?>&barang=<?php echo $selected_barang; ?>&bulan=<?php echo $selected_bulan; ?>&type=excel" target="_blank" class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel me-1"></i> Excel</a>
                    <a href="export_kartu.php?afdeling=<?php echo $selected_afdeling; ?>&barang=<?php echo $selected_barang; ?>&bulan=<?php echo $selected_bulan; ?>&type=pdf" target="_blank" class="btn btn-outline-danger btn-sm"><i class="fas fa-file-pdf me-1"></i> Print/PDF</a>
                </div>
            </div>

            <div class="table-responsive">
                <table id="kartuTable" class="table table-hover table-custom align-middle">
                    <thead>
                        <tr>
                            <th rowspan="2" width="5%">No</th>
                            <th rowspan="2">Tanggal</th>
                            <th rowspan="2">No. Bukti</th>
                            <th rowspan="2">Nama Mandor</th>
                            <th rowspan="2">Keterangan</th>
                            <th colspan="3">Banyaknya</th>
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
                            <td colspan="5" class="text-end fw-bold text-secondary">Saldo Awal</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center fw-bold"><?php echo number_format($stok_awal, 2); ?></td>
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
                            <td class="text-center fw-bold text-secondary"><?php echo $no++; ?></td>
                            <td>
                                <div class="fw-medium"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo $row['no_bukti']; ?></span></td>
                            <td><?php echo $row['nama_mandor']; ?></td>
                            <td class="text-muted small"><?php echo $row['keterangan']; ?></td>
                            <td class="text-center text-success fw-medium"><?php echo $masuk > 0 ? '+'.number_format($masuk, 2) : '-'; ?></td>
                            <td class="text-center text-danger fw-medium"><?php echo $keluar > 0 ? '-'.number_format($keluar, 2) : '-'; ?></td>
                            <td class="text-center fw-bold text-primary bg-light"><?php echo number_format($sisa, 2); ?></td>
                            <td class="text-center">
                                <button class="btn btn-light btn-sm text-warning shadow-sm" 
                                    data-id="<?php echo $row['id']; ?>"
                                    data-tanggal="<?php echo $row['tanggal']; ?>"
                                    data-nobukti="<?php echo $row['no_bukti']; ?>"
                                    data-mandor="<?php echo $row['nama_mandor']; ?>"
                                    data-jenis="<?php echo $row['jenis_transaksi']; ?>"
                                    data-jumlah="<?php echo $row['jumlah']; ?>"
                                    data-keterangan="<?php echo $row['keterangan']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#editModal"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-light btn-sm text-danger shadow-sm" 
                                    data-id="<?php echo $row['id']; ?>" 
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php 
                            }
                        } 
                        ?>
                    </tbody>
                    <tfoot class="bg-light fw-bold">
                        <tr>
                            <td colspan="5" class="text-end text-secondary text-uppercase py-3">Total Mutasi Bulan Ini</td>
                            <td class="text-center text-success py-3"><?php echo number_format($total_masuk_periode, 2); ?></td>
                            <td class="text-center text-danger py-3"><?php echo number_format($total_keluar_periode, 2); ?></td>
                            <td class="text-center text-primary py-3"><?php echo number_format($sisa, 2); ?></td>
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Input Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id_afdeling" value="<?php echo $selected_afdeling; ?>">
                        <input type="hidden" name="id_barang" value="<?php echo $selected_barang; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" name="tanggal" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Transaksi</label>
                            <select name="jenis_transaksi" class="form-select" required onchange="toggleMandor(this.value, 'add')">
                                <option value="keluar">Keluar (Pemakaian)</option>
                                <option value="masuk">Masuk (Penerimaan)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No. Bukti</label>
                            <input type="text" name="no_bukti" class="form-control" required placeholder="Contoh: BKK/001">
                        </div>
                        <div class="mb-3" id="mandorGroup_add">
                            <label class="form-label">Nama Mandor</label>
                            <input type="text" name="nama_mandor" class="form-control" placeholder="Nama Mandor">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jumlah</label>
                            <input type="number" step="0.01" name="jumlah" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Edit Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" name="tanggal" id="edit_tanggal" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Transaksi</label>
                            <select name="jenis_transaksi" id="edit_jenis" class="form-select" required onchange="toggleMandor(this.value, 'edit')">
                                <option value="keluar">Keluar (Pemakaian)</option>
                                <option value="masuk">Masuk (Penerimaan)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No. Bukti</label>
                            <input type="text" name="no_bukti" id="edit_nobukti" class="form-control" required>
                        </div>
                        <div class="mb-3" id="mandorGroup_edit">
                            <label class="form-label">Nama Mandor</label>
                            <input type="text" name="nama_mandor" id="edit_mandor" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jumlah</label>
                            <input type="number" step="0.01" name="jumlah" id="edit_jumlah" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Hapus Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <p>Apakah Anda yakin ingin menghapus data transaksi ini?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
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
