-- Tabel Afdeling
CREATE TABLE IF NOT EXISTS afdeling (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_afdeling VARCHAR(100) NOT NULL
);

-- Isi Data Afdeling
INSERT INTO afdeling (nama_afdeling) VALUES 
('Afdeling Kedondong'), 
('Afdeling Kemiri'), 
('Afdeling Gunung Sari'), 
('Afdeling Jolosekti');

-- Tabel Barang
CREATE TABLE IF NOT EXISTS barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_barang VARCHAR(20) NOT NULL,
    nama_barang VARCHAR(100) NOT NULL,
    satuan VARCHAR(20) NOT NULL,
    stok INT DEFAULT 0
);

-- Isi Data Barang Dummy
INSERT INTO barang (kode_barang, nama_barang, satuan, stok) VALUES
('BRG001', 'Pupuk Urea', 'Kg', 1500),
('BRG002', 'Herbisida', 'Liter', 200),
('BRG003', 'Solar', 'Liter', 5000),
('BRG004', 'Semen', 'Zak', 50),
('BRG005', 'Racun Tikus', 'Pack', 100);

-- Tabel Transaksi Gudang (Kartu Gudang)
CREATE TABLE IF NOT EXISTS transaksi_gudang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    id_barang INT NOT NULL,
    id_afdeling INT NOT NULL,
    no_bukti VARCHAR(50),
    nama_mandor VARCHAR(100),
    jenis_transaksi ENUM('masuk', 'keluar') NOT NULL,
    jumlah DECIMAL(10,2) NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_barang) REFERENCES barang(id),
    FOREIGN KEY (id_afdeling) REFERENCES afdeling(id)
);

-- Isi Data Dummy Transaksi
INSERT INTO transaksi_gudang (tanggal, id_barang, id_afdeling, no_bukti, nama_mandor, jenis_transaksi, jumlah, keterangan) VALUES
('2024-04-01', 1, 1, 'BKM/001', NULL, 'masuk', 100, 'Stok Awal'),
('2024-04-02', 1, 1, 'BKK/001', 'Mandor Budi', 'keluar', 20, 'Pemupukan Blok A'),
('2024-04-03', 1, 1, 'BKM/002', NULL, 'masuk', 50, 'Tambahan Stok'),
('2024-04-04', 1, 1, 'BKK/002', 'Mandor Joko', 'keluar', 30, 'Pemupukan Blok B');
