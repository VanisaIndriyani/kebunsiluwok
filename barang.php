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
                $stmt = $koneksi->prepare("INSERT INTO barang (kode_barang, nama_barang, satuan, stok) VALUES (:kode, :nama, :satuan, :stok)");
                $stmt->execute([
                    ':kode' => $_POST['kode_barang'],
                    ':nama' => $_POST['nama_barang'],
                    ':satuan' => $_POST['satuan'],
                    ':stok' => $_POST['stok']
                ]);
                $message = '<div class="alert alert-success">Data barang berhasil ditambahkan!</div>';
            } elseif ($_POST['action'] == 'edit') {
                $stmt = $koneksi->prepare("UPDATE barang SET kode_barang=:kode, nama_barang=:nama, satuan=:satuan, stok=:stok WHERE id=:id");
                $stmt->execute([
                    ':kode' => $_POST['kode_barang'],
                    ':nama' => $_POST['nama_barang'],
                    ':satuan' => $_POST['satuan'],
                    ':stok' => $_POST['stok'],
                    ':id' => $_POST['id']
                ]);
                $message = '<div class="alert alert-success">Data barang berhasil diupdate!</div>';
            } elseif ($_POST['action'] == 'delete') {
                // Cek dependensi dulu
                $stmt_check = $koneksi->prepare("SELECT COUNT(*) FROM transaksi_gudang WHERE id_barang = :id");
                $stmt_check->execute([':id' => $_POST['id']]);
                if ($stmt_check->fetchColumn() > 0) {
                    $message = '<div class="alert alert-danger">Gagal menghapus! Barang ini sudah digunakan dalam transaksi.</div>';
                } else {
                    $stmt = $koneksi->prepare("DELETE FROM barang WHERE id=:id");
                    $stmt->execute([':id' => $_POST['id']]);
                    $message = '<div class="alert alert-success">Data barang berhasil dihapus!</div>';
                }
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Fetch Data Barang
$stmt = $koneksi->query("SELECT * FROM barang ORDER BY kode_barang ASC");
$barangs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang - PTPN I Regional 3</title>
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
                <a href="#submenuDataMaster" data-bs-toggle="collapse" class="d-flex align-items-center active" aria-expanded="true">
                    <i class="fas fa-database"></i> Data Master 
                    <i class="fas fa-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                </a>
                <ul class="collapse show sidebar-submenu" id="submenuDataMaster">
                    <li><a href="barang.php" class="active"><i class="fas fa-box-open" style="font-size: 0.9em;"></i> Barang</a></li>
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

        <?php echo $message; ?>

        <!-- Content Header -->
        <div class="mb-4">
            <div class="p-3 rounded text-white" style="background: #2e7d32;">
                <h4 class="m-0 fw-bold"><i class="fas fa-box-open me-2"></i> Data Barang</h4>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus"></i> Tambah Barang
                </button>
                <div class="d-flex gap-2">
                    <input type="text" id="searchInput" class="form-control" placeholder="Cari barang...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-success">
                        <tr>
                            <th width="5%">No</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Satuan</th>
                            <th>Stok Saat Ini</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php $no = 1; foreach ($barangs as $row): ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo $row['kode_barang']; ?></td>
                            <td><?php echo $row['nama_barang']; ?></td>
                            <td><?php echo $row['satuan']; ?></td>
                            <td class="text-center"><?php echo number_format($row['stok'], 0); ?></td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm btn-edit" 
                                    data-id="<?php echo $row['id']; ?>"
                                    data-kode="<?php echo $row['kode_barang']; ?>"
                                    data-nama="<?php echo $row['nama_barang']; ?>"
                                    data-satuan="<?php echo $row['satuan']; ?>"
                                    data-stok="<?php echo $row['stok']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#editModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btn-delete" 
                                    data-id="<?php echo $row['id']; ?>" 
                                    data-nama="<?php echo $row['nama_barang']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Tambah Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Kode Barang</label>
                            <input type="text" name="kode_barang" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Barang</label>
                            <input type="text" name="nama_barang" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Satuan</label>
                            <select name="satuan" class="form-select" required>
                                <option value="Kg">Kg</option>
                                <option value="Liter">Liter</option>
                                <option value="Pcs">Pcs</option>
                                <option value="Unit">Unit</option>
                                <option value="Zak">Zak</option>
                                <option value="Pack">Pack</option>
                                <option value="Meter">Meter</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stok Awal</label>
                            <input type="number" step="0.01" name="stok" class="form-control" value="0" required>
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
                    <h5 class="modal-title">Edit Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Kode Barang</label>
                            <input type="text" name="kode_barang" id="edit_kode" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Barang</label>
                            <input type="text" name="nama_barang" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Satuan</label>
                            <select name="satuan" id="edit_satuan" class="form-select" required>
                                <option value="Kg">Kg</option>
                                <option value="Liter">Liter</option>
                                <option value="Pcs">Pcs</option>
                                <option value="Unit">Unit</option>
                                <option value="Zak">Zak</option>
                                <option value="Pack">Pack</option>
                                <option value="Meter">Meter</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stok</label>
                            <input type="number" step="0.01" name="stok" id="edit_stok" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
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
                    <h5 class="modal-title">Hapus Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <p>Apakah Anda yakin ingin menghapus barang <strong id="delete_nama"></strong>?</p>
                        <small class="text-danger">Tindakan ini tidak dapat dibatalkan.</small>
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
        // Toggle Sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('active');
        });

        // Search Function
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#tableBody tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // Edit Modal Handler
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_id').value = this.dataset.id;
                document.getElementById('edit_kode').value = this.dataset.kode;
                document.getElementById('edit_nama').value = this.dataset.nama;
                document.getElementById('edit_kategori').value = this.dataset.kategori;
                document.getElementById('edit_satuan').value = this.dataset.satuan;
                document.getElementById('edit_stok').value = this.dataset.stok;
            });
        });

        // Delete Modal Handler
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('delete_id').value = this.dataset.id;
                document.getElementById('delete_nama').innerText = this.dataset.nama;
            });
        });
    </script>
</body>
</html>