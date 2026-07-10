<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetEdu Pro | Luxury Pet Experience</title>
    
    <!-- Resources -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Plus+Jakarta+Sans:wght@800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-apple: #0b0b0c;
            --text-main: #f5f5f7;
            --text-secondary: #86868b;
            --accent-gold: #d4af37;
            --accent-blue: #0071e3;
            --bento-bg: #1c1c1e;
            --orange: #ff5400;
            --orange-dark: #cc4300;
            --border-color: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--bg-apple);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            letter-spacing: -0.02em;
        }

        /* --- Animations --- */
        .img-hd {
            border-radius: 40px;
            object-fit: cover;
            transition: 0.8s cubic-bezier(0.2, 1, 0.3, 1);
        }

        .text-gradient {
            background: linear-gradient(180deg, #fff 30%, #a1a1a6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* --- Hero Section --- */
        .hero-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            background: radial-gradient(circle at center, #2c2c2e 0%, #0b0b0c 100%);
            padding: 80px 20px;
        }

        .hero-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(3rem, 8vw, 6.5rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
        }

        /* --- Bento Box Layout --- */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: repeat(2, 450px);
            gap: 24px;
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .bento-item {
            background: var(--bento-bg);
            border-radius: 35px;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            min-height: 300px;
        }

        .bento-img-full {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
            filter: brightness(0.5);
            transition: 1.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .bento-item:hover .bento-img-full {
            transform: scale(1.08);
            filter: brightness(0.65);
        }

        .bento-content {
            position: relative;
            z-index: 2;
            padding: 40px;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 30%, transparent 100%);
        }

        /* Grid Config */
        .item-lg { grid-column: span 2; grid-row: span 2; }
        .item-wide { grid-column: span 2; }

        /* --- Buttons --- */
        .btn-apple {
            background: #fff;
            color: #000;
            padding: 18px 40px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.3s;
            display: inline-block;
            border: none;
        }

        .btn-apple:hover {
            transform: scale(1.05);
            background: var(--text-main);
            color: #000;
        }

        /* --- Services List --- */
        .service-line {
            border-bottom: 1px solid #2c2c2e;
            padding: 40px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .service-name { font-size: 2rem; font-weight: 600; }
        .service-desc { color: var(--text-secondary); max-width: 500px; text-align: right; }

        /* --- Testimonials --- */
        .testi-section {
            background: #000;
            padding: 100px 0;
            overflow: hidden;
        }

        .testi-card {
            background: var(--bento-bg);
            padding: 50px;
            border-radius: 40px;
            min-width: 350px;
            margin: 0 15px;
            border: 1px solid var(--border-color);
        }

       /* --- PROMO & MAPS SECTION --- */
        .promo-testimonial-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            max-width: 1400px;
            margin: 80px auto;
            padding: 0 24px;
        }

        .promo-card {
            background: linear-gradient(135deg, #1c1c1e 0%, #2c2c2e 100%);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px;
            display: flex;
            flex-direction: row; /* Mengatur teks dan gambar berdampingan */
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: 400px;
            overflow: hidden;
        }

        .promo-content-side {
            flex: 1.2;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .promo-image-side {
            flex: 0.8;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .promo-cat-img {
            width: 100%;
            max-width: 220px;
            height: 220px;
            object-fit: cover;
            border-radius: 20px; /* Sudut melengkung halus */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .promo-badge {
            display: inline-block;
            background: var(--orange);
            color: white;
            font-size: 11px;
            font-weight: 800;
            padding: 6px 14px;
            border-radius: 30px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: fit-content;
            margin-bottom: 20px;
        }

        .promo-content-block h3 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: #fff;
        }

        .promo-content-block p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        .btn-promo-yellow {
            background: #FFD700;
            color: #000;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 8px;
            display: inline-block;
            width: fit-content;
            margin-bottom: 15px;
        }

        .promo-terms {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Responsif untuk layar kecil */
        @media (max-width: 768px) {
            .promo-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 30px;
            }
            .promo-image-side {
                width: 100%;
                margin-top: 20px;
            }
            .promo-cat-img {
                max-width: 100%;
                height: 200px;
            }
        }
        /* Location & Map Card */
        .location-map-card {
            position: relative;
            background: #1c1c1e;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
            height: 100%;
            min-height: 400px;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .location-map-card:hover {
            transform: translateY(-6px);
            border-color: var(--orange);
            box-shadow: 0 20px 40px rgba(255, 84, 0, 0.08);
        }

        .location-map-card iframe {
            width: 100%;
            height: 100%;
            min-height: 400px;
            display: block;
            border: 0;
            filter: grayscale(100%) invert(90%) contrast(1.2);
            transition: filter 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .location-map-card:hover iframe {
            filter: grayscale(0%) invert(0%) contrast(1);
        }

        .map-overlay-card {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(11, 11, 12, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 20px;
            max-width: 260px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.08);
            z-index: 10;
            transition: all 0.4s ease;
        }

        .map-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 84, 0, 0.15);
            color: #ff7300;
            font-size: 9px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .map-overlay-card h4 {
            font-size: 15px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 6px;
        }

        .map-overlay-card p {
            font-size: 11px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .map-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--orange);
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .map-link:hover {
            background: var(--orange-dark);
            color: white;
            transform: translateY(-1px);
        }

        /* --- Footer --- */
        footer {
            background: #000;
            padding: 80px 0;
            border-top: 1px solid #1c1c1e;
            color: var(--text-secondary);
        }

        @media (max-width: 992px) {
            .bento-grid { grid-template-columns: 1fr; grid-template-rows: auto; }
            .item-lg, .item-wide { grid-column: span 1; grid-row: span 1; height: 450px; }
            .service-line { flex-direction: column; align-items: flex-start; }
            .service-desc { text-align: left; margin-top: 10px; }
            .promo-testimonial-section { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- HERO SECTION -->
    <section class="hero-container">
        <div data-aos="fade-up" data-aos-duration="1500">
            <h1 class="hero-title text-gradient">Kemewahan Sejati<br>Untuk Sahabat Anda.</h1>
            <p class="fs-4 text-secondary mb-5">PetEdu Pro: Destinasi eksklusif untuk perawatan, nutrisi, dan gaya hidup hewan peliharaan kelas dunia.</p>
            <a href="#explore" class="btn-apple">Lihat Layanan</a>
        </div>
        <div class="mt-5" data-aos="zoom-in" data-aos-delay="400">
            <!-- Gambar HD Anjing Mewah -->
            <img src="https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&q=80&w=1000" 
                 class="img-hd shadow-lg" style="width: 320px; height: 400px;" alt="Premium Dog">
        </div>
    </section>

    <!-- BENTO PRODUCT REVEAL -->
    <section id="explore" class="py-5">
        <div class="container-fluid">
            <div class="bento-grid">
                
                <!-- Layanan Utama: Grooming -->
                <div class="bento-item item-lg" data-aos="fade-up">
                    <img src="https://images.unsplash.com/photo-1516734212186-a967f81ad0d7?auto=format&fit=crop&q=80&w=1000" class="bento-img-full" alt="Signature Grooming">
                    <div class="bento-content">
                        <h2 class="display-4 fw-800 text-white">Spa & Grooming<br>Signature.</h2>
                        <p class="fs-5 text-secondary">Perawatan bulu dan kulit dengan produk organik terbaik dunia.</p>
                    </div>
                </div>

                <!-- Pet Hotel -->
                <div class="bento-item item-wide" data-aos="fade-up">
                    <img src="https://images.unsplash.com/photo-1560743641-3914f2c45636?auto=format&fit=crop&q=80&w=1000" class="bento-img-full" alt="Luxury Pet Hotel">
                    <div class="bento-content text-end">
                        <h2 class="display-5 fw-bold text-white">Luxury Pet Hotel.</h2>
                        <p class="text-secondary">Kamar private dengan kontrol suhu dan CCTV 24 jam.</p>
                    </div>
                </div>

                <!-- Gourmet Food -->
                <div class="bento-item" data-aos="fade-up">
                    <img src="https://images.unsplash.com/photo-1589924691106-088b906509c1?auto=format&fit=crop&q=80&w=800" class="bento-img-full" alt="Gourmet Food">
                    <div class="bento-content">
                        <i class="fas fa-bone fa-2x text-warning mb-3"></i>
                        <h3 class="fw-bold text-white">Gourmet Food.</h3>
                        <p class="small text-secondary">Nutrisi premium yang disesuaikan dengan kebutuhan ras spesifik.</p>
                    </div>
                </div>

                <!-- Accessories Bento Item -->
                <div class="bento-item" data-aos="fade-up">
                    <img src="https://images.unsplash.com/photo-1583337130417-3346a1be7dee?auto=format&fit=crop&q=80&w=800" class="bento-img-full" alt="Designer Collection">
                    <div class="bento-content">
                        <h3 class="fw-bold text-white">Designer Collection.</h3>
                        <p class="small text-secondary">Koleksi kalung & aksesoris eksklusif.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- EXCLUSIVE SERVICES LIST -->
    <section class="py-5 my-5">
        <div class="container">
            <h2 class="display-3 fw-800 mb-5 text-gradient" data-aos="fade-right">Layanan Eksklusif.</h2>
            
            <div class="service-line" data-aos="fade-up">
                <div class="service-name">Full Grooming</div>
                <div class="service-desc">Mandi aromaterapi, potong kuku, pembersihan telinga, dan styling bulu oleh groomer bersertifikat internasional.</div>
            </div>

            <div class="service-line" data-aos="fade-up">
                <div class="service-name">Klinik & Vaksin</div>
                <div class="service-desc">Konsultasi kesehatan rutin dan program vaksinasi lengkap untuk menjaga imunitas anabul Anda tetap prima.</div>
            </div>

            <div class="service-line" data-aos="fade-up">
                <div class="service-name">Pet Training</div>
                <div class="service-desc">Pelatihan kepatuhan dan perilaku dengan metode positive reinforcement oleh trainer profesional.</div>
            </div>

            <div class="service-line" data-aos="fade-up">
                <div class="service-name">Home Delivery</div>
                <div class="service-desc">Pengantaran kebutuhan hewan peliharaan langsung ke depan pintu rumah Anda dengan layanan instan.</div>
            </div>
        </div>
    </section>

    <!-- STATS COUNTER -->
    <section class="py-5 text-center">
        <div class="container">
            <div class="row">
                <div class="col-md-4" data-aos="fade-up">
                    <div class="display-2 fw-800 text-primary">15k+</div>
                    <p class="text-secondary fw-bold">PELANGGAN SETIA</p>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="display-2 fw-800 text-primary">24/7</div>
                    <p class="text-secondary fw-bold">PENJAGAAN MEDIS</p>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="display-2 fw-800 text-primary">100%</div>
                    <p class="text-secondary fw-bold">BAHAN ORGANIK</p>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="testi-section">
        <div class="container mb-5">
            <h2 class="display-4 fw-bold">Apa Kata Mereka?</h2>
        </div>
        <div class="d-flex overflow-auto pb-5">
            <div class="testi-card">
                <p class="fs-4">"Satu-satunya tempat di mana saya merasa aman meninggalkan kucing saya. Pelayanannya sangat premium."</p>
                <div class="mt-4"><strong>— Marcella, Jakarta</strong></div>
            </div>
            <div class="testi-card">
                <p class="fs-4">"Grooming di sini hasilnya luar biasa. Wanginya tahan lama dan bulu anjing saya jadi sangat halus."</p>
                <div class="mt-4"><strong>— Denny, Surabaya</strong></div>
            </div>
            <div class="testi-card">
                <p class="fs-4">"Pet Hotel terbaik. Saya bisa memantau anabul lewat CCTV kapan saja. Sangat menenangkan."</p>
                <div class="mt-4"><strong>— Sophia, Bali</strong></div>
            </div>
        </div>
    </section>

    <!-- PROMO & MAPS SECTION -->
    <section class="promo-testimonial-section">
        
        <!-- Promo Card -->
        <div class="promo-card" data-aos="fade-right">
            <!-- Sisi Kiri: Konten Teks -->
            <div class="promo-content-side">
                <div class="promo-badge">Promo Weekend</div>
                <div class="promo-content-block">
                    <h3>Diskon 20%</h3>
                    <p>Untuk semua layanan Grooming & Pet Hotel</p>
                    <div class="btn-promo-yellow">Setiap Sabtu & Minggu</div>
                    <div class="promo-terms">*Syarat & ketentuan berlaku</div>
                </div>
            </div>
            
            <!-- Sisi Kanan: Gambar Kucing yang Rapih -->
            <div class="promo-image-side">
                <img src="https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&q=80&w=600" class="promo-cat-img" alt="Pet Promo Asset">
            </div>
        </div>

        <!-- Location Map Card -->
        <div class="location-map-card" data-aos="fade-left">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3965.3545986499857!2d107.14830219999999!3d-6.3481107!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e699b896d7fc649%3A0xe0a940b1f200d008!2sPoliteknik%20Astra!5e0!3m2!1sid!2sid!4v1780735557436!5m2!1sid!2sid"
                allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
            </iframe>

            <div class="map-overlay-card">
                <span class="map-badge"><i class="fa-solid fa-location-dot"></i> Lokasi Utama</span>
                <h4>Politeknik Astra</h4>
                <p>Delta Silicon II, Cibatu, Cikarang Selatan, Bekasi, Jawa Barat 17530</p>
                <a href="https://maps.app.goo.gl/FpzS6FdUWPp6kGvQ9" target="_blank" class="map-link">
                    Petunjuk Arah <i class="fa-solid fa-arrow-turn-up"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- CTA SECTION -->
    <section class="container py-5 my-5 text-center" data-aos="zoom-in">
        <div class="py-5 bg-white text-black rounded-5">
            <h2 class="display-2 fw-800">Berikan Yang Terbaik.</h2>
            <p class="fs-4 mb-5">Bergabunglah dengan komunitas pemilik hewan paling elit di Indonesia.</p>
            <button onclick="handleBooking()" class="btn-apple bg-black text-white px-5">Booking Sekarang</button>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-5">
                    <h3 class="text-white fw-bold mb-4">PetEdu Pro.</h3>
                    <p>Mendefinisikan ulang cara Anda mencintai sahabat setia Anda dengan standar kemewahan baru.</p>
                </div>
                <div class="col-6 col-lg-2">
                    <h5 class="text-white mb-3">Layanan</h5>
                    <ul class="list-unstyled">
                        <li>Grooming</li>
                        <li>Pet Hotel</li>
                        <li>Nutrition</li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h5 class="text-white mb-3">Tentang</h5>
                    <ul class="list-unstyled">
                        <li>Store Locator</li>
                        <li>Karir</li>
                        <li>Kontak</li>
                    </ul>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <div class="fs-3 d-flex gap-4 justify-content-lg-end">
                        <i class="fab fa-instagram"></i>
                        <i class="fab fa-facebook"></i>
                        <i class="fab fa-whatsapp"></i>
                    </div>
                </div>
            </div>
            <hr class="my-5 border-secondary">
            <p class="small text-center text-muted">Copyright © 2024 PetEdu Pro Indonesia. Luxury Pet Care & Experience.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <script>
        // Inisialisasi Animasi Scroll
        AOS.init({ duration: 1200, once: true });

        // Fungsi Booking
        function handleBooking() {
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#ffffff', '#d4af37', '#0071e3']
            });
            setTimeout(() => {
                alert("📅 Permintaan booking Anda telah diterima. Admin kami akan segera menghubungi Anda melalui WhatsApp.");
            }, 500);
        }
    </script>
</body>
</html