
<?php
require_once "config/database.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SmartCare | Patient Registration</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-container">

        <a href="index.php" class="brand">
            <div class="brand-icon">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>

            <div class="brand-text">
                <h2>Smart<span>Care.</span></h2>
                <small>Patient Registration</small>
            </div>
        </a>

        <div class="nav-menu" id="navMenu">
            <a href="#home">Beranda</a>
            <a href="#layanan">Layanan</a>
            <a href="#tentang">Tentang Kami</a>
            <a href="#alur">Cara Daftar</a>
        </div>

        <div class="nav-buttons">
            <a href="login.php" class="btn-login">Masuk</a>
            <a href="register.php" class="btn-primary">Daftar Sekarang <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <button class="menu-toggle" id="menuToggle">
            <i class="fa-solid fa-bars"></i>
        </button>

    </div>
</nav>


<!-- HERO SECTION -->
<section class="hero" id="home">

    <div class="hero-container">

        <div class="hero-content">

            <div class="hero-label">
                <span class="pulse"></span>
                Layanan Kesehatan Digital
            </div>

            <h1>
                Kesehatan Lebih Mudah,
                <span>Daftar Tanpa Antre.</span>
            </h1>

            <p>
                Nikmati kemudahan pendaftaran pemeriksaan kesehatan
                secara online. Pilih dokter, tentukan jadwal, dan
                pantau antrean langsung dari perangkat Anda.
            </p>

            <div class="hero-buttons">
                <a href="register.php" class="btn-primary btn-large">
                    Mulai Pendaftaran
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

                <a href="#layanan" class="btn-outline">
                    Jelajahi Layanan
                </a>
            </div>

            <div class="hero-trust">
                <div class="trust-icon">
                    <i class="fa-solid fa-shield-heart"></i>
                </div>

                <div>
                    <strong>Praktis dan Terintegrasi</strong>
                    <small>Informasi pendaftaran dalam satu platform</small>
                </div>
            </div>

        </div>


        <!-- HERO VISUAL -->
        <div class="hero-visual">

            <div class="visual-background"></div>

            <div class="main-illustration">
                <div class="doctor-circle">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>

                <div class="medical-cross">
                    <i class="fa-solid fa-plus"></i>
                </div>

                <div class="medical-decoration decoration-one">
                    <i class="fa-solid fa-heart-pulse"></i>
                </div>

                <div class="medical-decoration decoration-two">
                    <i class="fa-solid fa-stethoscope"></i>
                </div>
            </div>

            <div class="floating-card appointment-card">
                <div class="card-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <div>
                    <strong>Pendaftaran Online</strong>
                    <small>Lebih praktis dan mudah</small>
                </div>

                <i class="fa-solid fa-circle-check check-icon"></i>
            </div>

            <div class="floating-card schedule-card">
                <div class="schedule-icon">
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div>
                    <strong>Jadwal Dokter</strong>
                    <small>Cek jadwal sebelum mendaftar</small>
                </div>
            </div>

        </div>

    </div>

</section>


<!-- FEATURES -->
<section class="features" id="layanan">

    <div class="section-heading">
        <span class="section-label">LAYANAN KAMI</span>

        <h2>Semua Kebutuhan Pendaftaran<br>
            <span>Dalam Satu Platform.</span>
        </h2>

        <p>
            Kelola proses pendaftaran pemeriksaan kesehatan
            dengan lebih mudah, cepat, dan terorganisir.
        </p>
    </div>

    <div class="feature-grid">

        <div class="feature-card">
            <div class="feature-icon blue">
                <i class="fa-solid fa-user-doctor"></i>
            </div>

            <h3>Informasi Dokter</h3>

            <p>
                Temukan informasi dokter, spesialisasi,
                dan jadwal praktik yang tersedia.
            </p>

            <a href="login.php">Lihat Selengkapnya <i class="fa-solid fa-arrow-right"></i></a>
        </div>


        <div class="feature-card">
            <div class="feature-icon green">
                <i class="fa-solid fa-calendar-days"></i>
            </div>

            <h3>Pendaftaran Online</h3>

            <p>
                Daftarkan pemeriksaan tanpa harus
                melakukan pendaftaran langsung di loket.
            </p>

            <a href="register.php">Mulai Daftar <i class="fa-solid fa-arrow-right"></i></a>
        </div>


        <div class="feature-card">
            <div class="feature-icon purple">
                <i class="fa-solid fa-ticket"></i>
            </div>

            <h3>Informasi Antrean</h3>

            <p>
                Pantau nomor antrean dan status
                pendaftaran secara langsung.
            </p>

            <a href="login.php">Cek Antrean <i class="fa-solid fa-arrow-right"></i></a>
        </div>


        <div class="feature-card">
            <div class="feature-icon orange">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>

            <h3>Data Terintegrasi</h3>

            <p>
                Informasi pasien, dokter, dan pendaftaran
                terhubung dalam satu sistem.
            </p>

            <a href="#tentang">Pelajari Sistem <i class="fa-solid fa-arrow-right"></i></a>
        </div>

    </div>

</section>


<!-- ABOUT -->
<section class="about" id="tentang">

    <div class="about-container">

        <div class="about-visual">
            <div class="about-box">
                <i class="fa-solid fa-hospital"></i>
                <h3>Smart Healthcare</h3>
                <p>Pelayanan kesehatan dalam satu sistem digital.</p>

                <div class="about-mini-stats">
                    <div>
                        <i class="fa-solid fa-user-doctor"></i>
                        <span>Dokter</span>
                    </div>

                    <div>
                        <i class="fa-solid fa-users"></i>
                        <span>Pasien</span>
                    </div>

                    <div>
                        <i class="fa-solid fa-clipboard-list"></i>
                        <span>Petugas</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="about-content">

            <span class="section-label">TENTANG SMARTCARE</span>

            <h2>Pelayanan Lebih Teratur dengan Teknologi.</h2>

            <p>
                SmartCare merupakan sistem informasi pendaftaran
                pasien berbasis web yang dirancang untuk membantu
                proses administrasi pelayanan kesehatan.
            </p>

            <div class="about-point">
                <i class="fa-solid fa-circle-check"></i>
                <span>Pengelolaan data pasien secara terstruktur.</span>
            </div>

            <div class="about-point">
                <i class="fa-solid fa-circle-check"></i>
                <span>Informasi jadwal dan kuota dokter.</span>
            </div>

            <div class="about-point">
                <i class="fa-solid fa-circle-check"></i>
                <span>Integrasi informasi antara pasien, dokter, dan petugas.</span>
            </div>

        </div>

    </div>

</section>


<!-- HOW IT WORKS -->
<section class="how-it-works" id="alur">

    <div class="section-heading">
        <span class="section-label">CARA PENDAFTARAN</span>

        <h2>Hanya 3 Langkah <span>Untuk Mendaftar.</span></h2>

        <p>Proses pendaftaran dirancang agar lebih sederhana.</p>
    </div>

    <div class="steps-grid">

        <div class="step-card">
            <div class="step-number">01</div>
            <i class="fa-solid fa-user-plus"></i>
            <h3>Buat Akun</h3>
            <p>Daftarkan akun dan lengkapi identitas pasien.</p>
        </div>

        <div class="step-card">
            <div class="step-number">02</div>
            <i class="fa-solid fa-user-doctor"></i>
            <h3>Pilih Dokter</h3>
            <p>Pilih poliklinik, dokter, dan jadwal pemeriksaan.</p>
        </div>

        <div class="step-card">
            <div class="step-number">03</div>
            <i class="fa-solid fa-ticket"></i>
            <h3>Dapatkan Antrean</h3>
            <p>Konfirmasi pendaftaran dan pantau nomor antrean.</p>
        </div>

    </div>

    <div class="cta-box">
        <div>
            <h2>Mulai Perjalanan Kesehatan Anda.</h2>
            <p>Daftar sekarang dan nikmati kemudahan layanan digital.</p>
        </div>

        <a href="register.php" class="btn-white">
            Daftar Sekarang <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

</section>


<!-- FOOTER -->
<footer class="footer">

    <div class="footer-container">

        <div class="footer-brand">
            <div class="brand">
                <div class="brand-icon">
                    <i class="fa-solid fa-heart-pulse"></i>
                </div>

                <div class="brand-text">
                    <h2>Smart<span>Care.</span></h2>
                    <small>Patient Registration</small>
                </div>
            </div>

            <p>
                Sistem Informasi Pendaftaran Pasien
                Rumah Sakit Politeknik LP3I Jakarta.
            </p>
        </div>

        <div class="footer-info">
            <h4>Menu</h4>
            <a href="#home">Beranda</a>
            <a href="#layanan">Layanan</a>
            <a href="#tentang">Tentang Kami</a>
            <a href="#alur">Cara Daftar</a>
        </div>

        <div class="footer-info">
            <h4>Akses Sistem</h4>
            <a href="login.php">Login</a>
            <a href="register.php">Registrasi Pasien</a>
        </div>

    </div>

    <div class="footer-bottom">
        <p>© 2026 SmartCare. Mini Project Sistem Informasi Manajemen.</p>
        <p>Politeknik LP3I Jakarta</p>
    </div>

</footer>

<script src="assets/js/script.js"></script>

</body>
</html>
