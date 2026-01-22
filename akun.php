<?php
session_start();
require_once 'config/database.php';

// Cek Login dan Role Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Handle CRUD Operations
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] == 'add') {
                // Cek username exists
                $stmt_check = $koneksi->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
                $stmt_check->execute([':username' => $_POST['username']]);
                if ($stmt_check->fetchColumn() > 0) {
                    $message = '<div class="alert alert-danger">Username sudah digunakan!</div>';
                } else {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $koneksi->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (:username, :password, :nama, :role)");
                    $stmt->execute([
                        ':username' => $_POST['username'],
                        ':password' => $password,
                        ':nama' => $_POST['nama_lengkap'],
                        ':role' => $_POST['role']
                    ]);
                    $message = '<div class="alert alert-success">Data akun berhasil ditambahkan!</div>';
                }
            } elseif ($_POST['action'] == 'edit') {
                if (!empty($_POST['password'])) {
                    // Update dengan password baru
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $koneksi->prepare("UPDATE users SET username=:username, password=:password, nama_lengkap=:nama, role=:role WHERE id=:id");
                    $stmt->execute([
                        ':username' => $_POST['username'],
                        ':password' => $password,
                        ':nama' => $_POST['nama_lengkap'],
                        ':role' => $_POST['role'],
                        ':id' => $_POST['id']
                    ]);
                } else {
                    // Update tanpa password
                    $stmt = $koneksi->prepare("UPDATE users SET username=:username, nama_lengkap=:nama, role=:role WHERE id=:id");
                    $stmt->execute([
                        ':username' => $_POST['username'],
                        ':nama' => $_POST['nama_lengkap'],
                        ':role' => $_POST['role'],
                        ':id' => $_POST['id']
                    ]);
                }
                $message = '<div class="alert alert-success">Data akun berhasil diupdate!</div>';
            } elseif ($_POST['action'] == 'delete') {
                // Prevent self-delete
                if ($_POST['id'] == $_SESSION['user_id']) {
                    $message = '<div class="alert alert-danger">Anda tidak dapat menghapus akun Anda sendiri!</div>';
                } else {
                    $stmt = $koneksi->prepare("DELETE FROM users WHERE id=:id");
                    $stmt->execute([':id' => $_POST['id']]);
                    $message = '<div class="alert alert-success">Data akun berhasil dihapus!</div>';
                }
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Fetch Data Users
$stmt = $koneksi->query("SELECT * FROM users ORDER BY role ASC, username ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Akun - PTPN I Regional 3</title>
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
                    <li><a href="barang.php"><i class="fas fa-box-open" style="font-size: 0.9em;"></i> Barang</a></li>
                    <li><a href="afdeling.php"><i class="fas fa-building" style="font-size: 0.9em;"></i> Afdeling</a></li>
                    <li><a href="akun.php" class="active"><i class="fas fa-users-cog" style="font-size: 0.9em;"></i> Akun</a></li>
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
                <h4 class="m-0 fw-bold"><i class="fas fa-users-cog me-2"></i> Data Akun Pengguna</h4>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus"></i> Tambah Akun
                </button>
                <div class="d-flex gap-2">
                    <input type="text" id="searchInput" class="form-control" placeholder="Cari user...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-success">
                        <tr>
                            <th width="5%">No</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Role</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php $no = 1; foreach ($users as $row): ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo $row['username']; ?></td>
                            <td><?php echo $row['nama_lengkap']; ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $row['role'] == 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                    <?php echo ucfirst($row['role']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm btn-edit" 
                                    data-id="<?php echo $row['id']; ?>"
                                    data-username="<?php echo $row['username']; ?>"
                                    data-nama="<?php echo $row['nama_lengkap']; ?>"
                                    data-role="<?php echo $row['role']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#editModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-danger btn-sm btn-delete" 
                                    data-id="<?php echo $row['id']; ?>" 
                                    data-nama="<?php echo $row['username']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
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
                    <h5 class="modal-title">Tambah Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
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
                    <h5 class="modal-title">Edit Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password (Kosongkan jika tidak diubah)</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
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
                    <h5 class="modal-title">Hapus Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <p>Apakah Anda yakin ingin menghapus akun <strong id="delete_nama"></strong>?</p>
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
                document.getElementById('edit_username').value = this.dataset.username;
                document.getElementById('edit_nama').value = this.dataset.nama;
                document.getElementById('edit_role').value = this.dataset.role;
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