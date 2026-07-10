<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetEdu Pro - Pusat Edukasi Hewan Peliharaan Premium</title>
    <!-- CSS Resources -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --pet-blue: #0dcaf0;
            --pet-blue-hover: #0baccc;
            --glass: rgba(255, 255, 255, 0.02);
            --glass-border: rgba(255, 255, 255, 0.08);
            --info-grad: linear-gradient(135deg, #0dcaf0, #0081a7);
            --dark-bg: #07090b;
            --dark-card: #111418;
            --pet-border: rgba(255, 255, 255, 0.08);
            --accent-green: #2ecc71;
            --accent-orange: #e67e22;
            --accent-red: #e74c3c;
        }

        body {
            background-color: var(--dark-bg);
            color: #ffffff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--dark-bg); }
        ::-webkit-scrollbar-thumb { background: #22252a; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--pet-blue); }

        .fw-800 { font-weight: 800; }
        
        /* Navbar */
        .navbar {
            background: rgba(7, 9, 11, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--pet-border);
            padding: 15px 0;
            z-index: 1050;
        }

        .nav-link { color: #ccc !important; font-size: 0.95rem; margin: 0 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { color: var(--pet-blue) !important; }

        /* Hero Section */
        .hero-section {
            padding: 200px 0 120px;
            background: radial-gradient(circle at 80% 20%, rgba(13, 202, 240, 0.08), transparent 50%),
                        radial-gradient(circle at 10% 80%, rgba(0, 129, 167, 0.05), transparent 50%);
        }

        /* Interactive Filter Hub */
        .filter-pill {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            color: #ccc;
            padding: 8px 20px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .filter-pill.active, .filter-pill:hover {
            background: var(--pet-blue);
            color: #000;
            border-color: var(--pet-blue);
            box-shadow: 0 5px 15px rgba(13, 202, 240, 0.3);
        }

        .search-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 12px 25px;
            color: white;
            transition: 0.3s;
        }

        .search-box:focus {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--pet-blue);
            box-shadow: 0 0 15px rgba(13, 202, 240, 0.1);
            outline: none;
            color: white;
        }

        /* Edu Cards */
        .edu-card {
            background: var(--dark-card);
            border: 1px solid var(--pet-border);
            border-radius: 24px;
            overflow: hidden;
            height: 100%;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
        }

        .edu-card-img-wrapper {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .edu-card-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: 0.6s ease;
        }

        .edu-card:hover .edu-card-img-wrapper img {
            transform: scale(1.08);
        }

        .edu-tag {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(11, 13, 15, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid var(--glass-border);
            color: var(--pet-blue);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .edu-card-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .edu-card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 12px;
            line-height: 1.4;
            color: #fff;
            transition: 0.3s;
        }

        .edu-card:hover {
            transform: translateY(-8px);
            border-color: var(--pet-blue);
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
        }

        .edu-card:hover .edu-card-title {
            color: var(--pet-blue);
        }

        /* Body Language Decoder */
        .decode-card {
            background: var(--dark-card);
            border: 1px solid var(--pet-border);
            border-radius: 24px;
            padding: 35px;
            height: 100%;
        }

        .interactive-hotspot {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .interactive-hotspot:hover, .interactive-hotspot.active {
            background: rgba(13, 202, 240, 0.08);
            border-color: var(--pet-blue);
            transform: translateX(5px);
        }

        .hotspot-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: rgba(13, 202, 240, 0.15);
            display: flex; align-items: center; justify-content: center;
            color: var(--pet-blue);
            font-weight: bold;
        }

        .hotspot-detail-view {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 40px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            min-height: 300px;
        }

        /* Laboratory / Calculator Glassmorphism */
        .calc-container {
            background: linear-gradient(135deg, rgba(22, 25, 28, 0.8) 0%, rgba(11, 13, 15, 0.95) 100%);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid var(--glass-border);
            border-radius: 35px;
            padding: 45px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }

        .nav-tabs-custom {
            border-bottom: 1px solid var(--pet-border);
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .tab-btn-custom {
            background: transparent;
            border: none;
            color: #888;
            padding: 10px 0;
            font-weight: 600;
            font-size: 1.05rem;
            position: relative;
            transition: 0.3s;
        }

        .tab-btn-custom.active {
            color: var(--pet-blue);
        }

        .tab-btn-custom.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--pet-blue);
            box-shadow: 0 0 10px var(--pet-blue);
        }

        .form-label {
            font-weight: 600;
            color: #ccc;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.04) !important;
            border: 1px solid var(--glass-border) !important;
            color: white !important;
            border-radius: 12px;
            padding: 14px 18px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--pet-blue) !important;
            box-shadow: 0 0 15px rgba(13, 202, 240, 0.15) !important;
        }

        .btn-calc-submit {
            background: var(--pet-blue);
            color: #000;
            font-weight: 700;
            padding: 15px 30px;
            border-radius: 12px;
            border: none;
            transition: 0.3s;
            width: 100%;
        }

        .btn-calc-submit:hover {
            background: var(--pet-blue-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 202, 240, 0.25);
        }

        .result-box-custom {
            background: rgba(13, 202, 240, 0.05);
            border: 1px solid rgba(13, 202, 240, 0.15);
            border-radius: 16px;
            padding: 20px;
            margin-top: 25px;
            text-align: center;
        }

        /* Symptom Checker */
        .checker-box {
            background: var(--dark-card);
            border: 1px solid var(--pet-border);
            border-radius: 24px;
            padding: 35px;
            height: 100%;
        }

        .symptom-item {
            padding: 12px 18px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--pet-border);
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: 0.3s;
        }

        .symptom-item:hover, .symptom-item.active {
            border-color: var(--accent-orange);
            background: rgba(230, 126, 34, 0.05);
        }

        /* Trivia Quiz Box */
        .quiz-container {
            background: linear-gradient(135deg, #16191c 0%, #0d1115 100%);
            border: 1px solid var(--pet-border);
            border-radius: 30px;
            padding: 40px;
        }

        .quiz-option {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 16px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .quiz-option:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--pet-blue);
        }

        .quiz-option.correct {
            background: rgba(46, 204, 113, 0.15) !important;
            border-color: var(--accent-green) !important;
            color: var(--accent-green);
        }

        .quiz-option.wrong {
            background: rgba(231, 76, 60, 0.15) !important;
            border-color: var(--accent-red) !important;
            color: var(--accent-red);
        }

        /* Accordion FAQ */
        .accordion-item {
            background: var(--dark-card) !important;
            border: 1px solid var(--pet-border) !important;
            margin-bottom: 15px;
            border-radius: 16px !important;
            overflow: hidden;
            transition: 0.3s;
        }

        .accordion-item:hover {
            border-color: rgba(13, 202, 240, 0.3);
        }

        .accordion-button {
            background: var(--dark-card) !important;
            color: white !important;
            font-weight: 600;
            padding: 20px;
            border: none !important;
            box-shadow: none !important;
        }

        .accordion-button:not(.collapsed) {
            color: var(--pet-blue) !important;
            background: rgba(13, 202, 240, 0.03) !important;
        }

        .accordion-button::after {
            filter: invert(1);
        }

        .accordion-body {
            background: rgba(0, 0, 0, 0.2);
            color: #b0b3b8;
            line-height: 1.6;
            border-top: 1px solid var(--pet-border);
            padding: 20px;
        }

        /* CTA Banner */
        .cta-banner {
            background: var(--info-grad);
            border-radius: 35px;
            padding: 60px;
            color: black;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(13, 202, 240, 0.25);
        }

        footer {
            border-top: 1px solid var(--pet-border);
            padding: 80px 0 40px;
            background: #040507;
        }
    </style>
</head>
<body>

    <!-- HERO SECTION -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right" data-aos-duration="1200">
                    <span class="badge bg-info text-dark mb-3 px-3 py-2 rounded-pill fw-bold">PORTAL EDUKASI VETERINER</span>
                    <h1 class="display-3 fw-800 mb-4">Wujudkan Anabul <span class="text-info">Sehat & Cerdas.</span></h1>
                    <p class="fs-5 opacity-75 mb-4">Pelajari panduan medis terverifikasi, interpretasikan psikologi hewan, hingga hitung kalkulasi nutrisi ilmiah buatan ahli veteriner profesional.</p>
                    <div class="d-flex gap-3">
                        <a href="#edu-hub" class="btn btn-info btn-lg rounded-pill px-4 fw-bold">Mulai Belajar</a>
                        <a href="#lab-digital" class="btn btn-outline-light btn-lg rounded-pill px-4">Coba Tools</a>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left" data-aos-duration="1200">
                    <div class="position-relative ms-lg-5 text-center">
                        <img src="https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&w=800&q=80" class="img-fluid rounded-5 shadow-2xl" alt="Pet Education Ecosystem" style="max-height: 480px; object-fit: cover; width: 100%;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- INTERACTIVE BODY LANGUAGE DECODER -->
    <section class="py-5 bg-black-50">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-800" data-aos="fade-up">Baca Pikiran <span class="text-info">Anabul</span></h2>
                <p class="text-secondary" data-aos="fade-up" data-aos-delay="100">Klik salah satu tanda bahasa tubuh untuk membaca suasana hati kucing atau anjing Anda secara langsung.</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="decode-card">
                        <h4 class="fw-bold mb-4"><i class="fa-solid fa-cat text-info me-2"></i> Kamus Perilaku Kucing</h4>
                        
                        <div class="interactive-hotspot active" onclick="decodeBehavior('cat-tail', this)">
                            <div class="hotspot-icon">1</div>
                            <div>
                                <h6 class="mb-1 fw-bold">Ekor Tegak Lurus ke Atas</h6>
                                <p class="small text-muted mb-0">Klik untuk melihat makna psikologis perilaku ini.</p>
                            </div>
                        </div>

                        <div class="interactive-hotspot" onclick="decodeBehavior('cat-slowblink', this)">
                            <div class="hotspot-icon">2</div>
                            <div>
                                <h6 class="mb-1 fw-bold">Berkedip Sangat Lambat (Slow Blink)</h6>
                                <p class="small text-muted mb-0">Mata berkedip lambat di hadapan Anda.</p>
                            </div>
                        </div>

                        <div class="interactive-hotspot" onclick="decodeBehavior('cat-ears', this)">
                            <div class="hotspot-icon">3</div>
                            <div>
                                <h6 class="mb-1 fw-bold">Telinga Mengarah ke Samping (Pesawat)</h6>
                                <p class="small text-muted mb-0">Telinga mendatar ke arah samping kepala.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6" data-aos="fade-left">
                    <div class="hotspot-detail-view" id="behavior-display">
                        <i class="fa-solid fa-paw fa-3x text-info mb-4"></i>
                        <h4 class="fw-bold" id="behavior-title">Ekor Tegak Lurus ke Atas</h4>
                        <p class="text-secondary px-lg-5" id="behavior-desc">Ini menandakan kucing Anda merasa sangat percaya diri, bahagia, ramah, dan siap berinteraksi atau bermain dengan Anda secara bersahabat.</p>
                        <span class="badge bg-success px-3 py-2 mt-2" id="behavior-status">SUASANA HATI: POSITIF / SENANG</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SEARCH & ARTICLES HUB -->
    <section id="edu-hub" class="py-5">
        <div class="container">
            <div class="d-md-flex align-items-center justify-content-between mb-5">
                <div data-aos="fade-right">
                    <h2 class="display-5 fw-800 mb-2">Perpustakaan <span class="text-info">Edukasi</span></h2>
                    <p class="text-secondary mb-0">Ratusan artikel kesehatan dan nutrisi anabul tervalidasi.</p>
                </div>
                <div class="mt-4 mt-md-0" data-aos="fade-left" style="min-width: 300px;">
                    <input type="text" id="articleSearch" class="form-control search-box w-100" placeholder="Cari panduan (misal: vaksin, diare)..." onkeyup="filterArticles()">
                </div>
            </div>

            <!-- Filter Pills -->
            <div class="d-flex flex-wrap gap-2 mb-4" data-aos="fade-up">
                <button class="filter-pill active" onclick="filterCategory('all', this)">Semua Edukasi</button>
                <button class="filter-pill" onclick="filterCategory('kucing', this)">Edukasi Kucing</button>
                <button class="filter-pill" onclick="filterCategory('anjing', this)">Edukasi Anjing</button>
                <button class="filter-pill" onclick="filterCategory('kesehatan', this)">Kesehatan Klinis</button>
                <button class="filter-pill" onclick="filterCategory('nutrisi', this)">Nutrisi & Diet</button>
            </div>

           <!-- Articles Grid -->
            <div class="row g-4" id="articlesGrid">
                <!-- Card 1 -->
                <div class="col-md-4 article-item" data-tags="kucing kesehatan" data-aos="fade-up" data-aos-delay="100">
                    <a href="baca_utama.php?id=1" class="text-decoration-none text-white">
                        <div class="edu-card">
                            <div class="edu-card-img-wrapper">
                                <span class="edu-tag">Kucing • Kesehatan</span>
                                <img src="https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=500&q=80" alt="Virus FIP">
                            </div>
                            <div class="edu-card-body">
                                <h5 class="edu-card-title">Mengenal Gejala Awal Feline Infectious Peritonitis (FIP)</h5>
                                <p class="text-secondary small flex-grow-1">FIP adalah mutasi virus corona yang mematikan pada kucing. Pelajari tanda mutasi wet FIP dan dry FIP sedini mungkin.</p>
                                <hr class="border-secondary opacity-25 col-12">
                                <span class="small text-info fw-bold"><i class="fa-regular fa-clock me-1"></i> Baca 4 Menit</span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Card 2 -->
                <div class="col-md-4 article-item" data-tags="anjing nutrisi" data-aos="fade-up" data-aos-delay="200">
                    <a href="baca_utama.php?id=2" class="text-decoration-none text-white">
                        <div class="edu-card">
                            <div class="edu-card-img-wrapper">
                                <span class="edu-tag">Anjing • Nutrisi</span>
                                <img src="https://images.unsplash.com/photo-1543466835-00a7907e9de1?auto=format&fit=crop&w=500&q=80" alt="Bahan Makanan Berbahaya">
                            </div>
                            <div class="edu-card-body">
                                <h5 class="edu-card-title">Daftar Bahan Makanan Manusia yang Beracun untuk Anjing</h5>
                                <p class="text-secondary small flex-grow-1">Bahan seperti cokelat, bawang, kismis, hingga pemanis xylitol dapat berakibat fatal bagi ginjal dan pencernaan anjing.</p>
                                <hr class="border-secondary opacity-25 col-12">
                                <span class="small text-info fw-bold"><i class="fa-regular fa-clock me-1"></i> Baca 6 Menit</span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Card 3 -->
                <div class="col-md-4 article-item" data-tags="kesehatan kucing" data-aos="fade-up" data-aos-delay="300">
                    <a href="baca_utama.php?id=3" class="text-decoration-none text-white">
                        <div class="edu-card">
                            <div class="edu-card-img-wrapper">
                                <span class="edu-tag">Kesehatan • Kucing</span>
                                <img src="https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&w=500&q=80" alt="Vaksinasi Kucing">
                            </div>
                            <div class="edu-card-body">
                                <h5 class="edu-card-title">Panduan Lengkap Jadwal Vaksinasi Kucing F3, F4 & Rabies</h5>
                                <p class="text-secondary small flex-grow-1">Cegah penyakit panleukopenia, calicivirus, dan rhinotracheitis dengan melengkapi jadwal vaksin primer kucing kesayangan Anda.</p>
                                <hr class="border-secondary opacity-25 col-12">
                                <span class="small text-info fw-bold"><i class="fa-regular fa-clock me-1"></i> Baca 5 Menit</span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Card 4 -->
                <div class="col-md-4 article-item" data-tags="anjing kesehatan" data-aos="fade-up">
                    <a href="baca_utama.php?id=4" class="text-decoration-none text-white">
                        <div class="edu-card">
                            <div class="edu-card-img-wrapper">
                                <span class="edu-tag">Anjing • Kesehatan</span>
                                <img src="https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&w=500&q=80" alt="Parasit Kulit">
                            </div>
                            <div class="edu-card-body">
                                <h5 class="edu-card-title">Mengatasi Kutu Dan Scabies Parah Pada Anjing Ras</h5>
                                <p class="text-secondary small flex-grow-1">Panduan praktis pengobatan topikal, pembersihan kandang, serta dosis ivermectin yang aman sesuai anjuran medis.</p>
                                <hr class="border-secondary opacity-25 col-12">
                                <span class="small text-info fw-bold"><i class="fa-regular fa-clock me-1"></i> Baca 7 Menit</span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Card 5 -->
                <div class="col-md-4 article-item" data-tags="nutrisi kucing" data-aos="fade-up" data-aos-delay="100">
                    <a href="baca_utama.php?id=5" class="text-decoration-none text-white">
                        <div class="edu-card">
                            <div class="edu-card-img-wrapper">
                                <span class="edu-tag">Nutrisi • Kucing</span>
                                <img src="https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=500&q=80" alt="Diet Basah">
                            </div>
                            <div class="edu-card-body">
                                <h5 class="edu-card-title">Pentingnya Wet Food untuk Kesehatan Ginjal Kucing Senior</h5>
                                <p class="text-secondary small flex-grow-1">Kucing secara biologis memiliki rasa haus yang rendah. Wet food membantu asupan hidrasi untuk mencegah penyakit kristal urin (FUS).</p>
                                <hr class="border-secondary opacity-25 col-12">
                                <span class="small text-info fw-bold"><i class="fa-regular fa-clock me-1"></i> Baca 5 Menit</span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Card 6 -->
                <div class="col-md-4 article-item" data-tags="nutrisi anjing" data-aos="fade-up" data-aos-delay="200">
                    <a href="baca_utama.php?id=6" class="text-decoration-none text-white">
                        <div class="edu-card">
                            <div class="edu-card-img-wrapper">
                                <span class="edu-tag">Nutrisi • Anjing</span>
                                <img src="https://images.unsplash.com/photo-1535930891776-0c2dfb7fda1a?auto=format&fit=crop&w=500&q=80" alt="Raw Food">
                            </div>
                            <div class="edu-card-body">
                                <h5 class="edu-card-title">Kelebihan dan Risiko Diet Raw Food (Makanan Mentah)</h5>
                                <p class="text-secondary small flex-grow-1">Kaji kecocokan nutrisi mentah terhadap sistem pencernaan, serta bahaya patogen seperti Salmonella dan E. coli.</p>
                                <hr class="border-secondary opacity-25 col-12">
                                <span class="small text-info fw-bold"><i class="fa-regular fa-clock me-1"></i> Baca 8 Menit</span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

    <!-- ADVANCED LABORATORIUM DIGITAL -->
    <section id="lab-digital" class="py-5">
        <div class="container">
            <div class="calc-container" data-aos="fade-up">
                <div class="row g-5 align-items-center">
                    <div class="col-lg-5">
                        <span class="badge bg-info text-dark mb-3 px-3 py-2 rounded-pill fw-bold">CALCULATOR ENGINE</span>
                        <h2 class="fw-800 mb-3 display-5">Laboratorium <span class="text-info">Digital V2</span></h2>
                        <p class="opacity-75 mb-4">Gunakan mesin kalkulator terpadu kami untuk memetakan tumbuh kembang nyata serta kebutuhan metabolisme biologis anabul Anda secara akurat.</p>
                        
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="hotspot-icon mt-1"><i class="fa-solid fa-calculator"></i></div>
                            <div>
                                <h6 class="mb-1 fw-bold">Presisi Berdasarkan Klasifikasi Medis</h6>
                                <p class="small text-secondary mb-0">Algoritma menghitung ras spesifik dan tingkat keaktifan biologis hewan peliharaan.</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3">
                            <div class="hotspot-icon mt-1"><i class="fa-solid fa-droplet"></i></div>
                            <div>
                                <h6 class="mb-1 fw-bold">Kombinasi Pengukuran Air Harian</h6>
                                <p class="small text-secondary mb-0">Memandu pencegahan dehidrasi kronis pada ginjal kucing dan anjing.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card bg-dark border-0 p-4 rounded-4" style="background: rgba(0,0,0,0.3) !important;">
                            <!-- Tabs Navigation -->
                            <div class="nav-tabs-custom">
                                <button class="tab-btn-custom active" onclick="switchLabTab('tab-age-v2', this)">Umur Manusia</button>
                                <button class="tab-btn-custom" onclick="switchLabTab('tab-calorie-v2', this)">Kalori Harian</button>
                                <button class="tab-btn-custom" onclick="switchLabTab('tab-hydration-v2', this)">Asupan Air</button>
                            </div>

                            <!-- Tabs Content -->
                            <div class="tab-content">
                                <!-- Tab 1: Umur -->
                                <div class="tab-pane-custom" id="tab-age-v2">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Jenis Anabul</label>
                                            <select id="petTypeV2" class="form-select">
                                                <option value="cat">Kucing (Lokal / Ras)</option>
                                                <option value="dog-small">Anjing (Kecil < 10kg)</option>
                                                <option value="dog-medium">Anjing (Medium 10-25kg)</option>
                                                <option value="dog-giant">Anjing (Besar > 25kg)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Umur Riil (Tahun)</label>
                                            <input type="number" id="petAgeV2" class="form-control" placeholder="Contoh: 3" min="0" max="30">
                                        </div>
                                        <div class="col-12 mt-4">
                                            <button onclick="calculateAdvancedAge()" class="btn-calc-submit">HITUNG UMUR BIOLOGIS</button>
                                        </div>
                                        <div id="ageResultV2" class="result-box-custom d-none">
                                            <h6 class="text-secondary small mb-1">SETARA UMUR MANUSIA</h6>
                                            <div class="h3 text-info fw-800 mb-2" id="ageOutputVal">-</div>
                                            <p class="small text-muted mb-0" id="ageOutputStage">-</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tab 2: Kalori -->
                                <div class="tab-pane-custom d-none" id="tab-calorie-v2">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Berat Badan (Kg)</label>
                                            <input type="number" id="petWeightCal" class="form-control" placeholder="Contoh: 4.5" step="0.1" min="0.1">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Status Aktivitas / Tahap</label>
                                            <select id="petActivityCal" class="form-select">
                                                <option value="1.2">Kucing / Anjing Dewasa Steril</option>
                                                <option value="1.4">Kucing Aktif / Belum Steril</option>
                                                <option value="1.6">Anjing Aktif / Belum Steril</option>
                                                <option value="1.0">Mengurangi Berat Badan (Obesitas)</option>
                                                <option value="2.5">Kitten / Puppy Sedang Tumbuh</option>
                                            </select>
                                        </div>
                                        <div class="col-12 mt-4">
                                            <button onclick="calculateAdvancedCalories()" class="btn-calc-submit">CEK ENERGI BIOLOGIS</button>
                                        </div>
                                        <div id="calorieResultV2" class="result-box-custom d-none">
                                            <h6 class="text-secondary small mb-1">RATING KEBUTUHAN ENERGI HARIAN (RER)</h6>
                                            <div class="h3 text-info fw-800 mb-2" id="calorieOutputVal">-</div>
                                            <p class="small text-muted mb-0">Merupakan batas panduan kalori harian untuk menjaga stabilitas berat badan fungsional.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tab 3: Hidrasi -->
                                <div class="tab-pane-custom d-none" id="tab-hydration-v2">
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Berat Badan Anabul (Kg)</label>
                                            <input type="number" id="petWeightWater" class="form-control" placeholder="Contoh: 5" step="0.1">
                                        </div>
                                        <div class="col-12 mt-4">
                                            <button onclick="calculateHydration()" class="btn-calc-submit">HITUNG KEBUTUHAN HIDRASI</button>
                                        </div>
                                        <div id="hydrationResultV2" class="result-box-custom d-none">
                                            <h6 class="text-secondary small mb-1">REKOMENDASI VOLUME AIR MINUM</h6>
                                            <div class="h3 text-info fw-800 mb-2" id="hydrationOutputVal">-</div>
                                            <p class="small text-muted mb-0">Kebutuhan dasar cairan mencegah batu ginjal / hematuria.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SYMPTOM CHECKER & TRIVIA QUIZ -->
    <section class="py-5">
        <div class="container">
            <div class="row g-5">
                <!-- Column 1: Symptom Checker -->
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="checker-box">
                        <span class="badge bg-warning text-dark mb-3 px-3 py-2 rounded-pill fw-bold">FIRST-AID ALGORITHM</span>
                        <h3 class="fw-800 mb-2">Symptom Checker</h3>
                        <p class="text-secondary mb-4">Deteksi dini kondisi klinis anabul Anda berdasarkan gejala utama yang sering muncul.</p>
                        
                        <div class="symptom-item" onclick="checkSymptom('vomit', this)">
                            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Muntah Lebih Dari 3 Kali Sehari</h6>
                            <p class="small text-muted mb-0">Muntah berulang berisi cairan kuning atau empedu.</p>
                        </div>

                        <div class="symptom-item" onclick="checkSymptom('lethargy', this)">
                            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Lemas & Tidak Mau Makan (Anoreksia)</h6>
                            <p class="small text-muted mb-0">Anabul mengisolasi diri dan menolak makanan favoritnya.</p>
                        </div>

                        <div class="symptom-item" onclick="checkSymptom('itchy', this)">
                            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Menggaruk Telinga Secara Ekstrem</h6>
                            <p class="small text-muted mb-0">Disertai kotoran hitam atau kemerahan di dalam liang telinga.</p>
                        </div>

                        <!-- Diagnosis Result Box -->
                        <div id="checker-result" class="p-3 rounded-4 mt-3 d-none" style="background: rgba(255,255,255,0.02); border: 1px dashed var(--pet-border);">
                            <div class="fw-bold text-info mb-1" id="diag-title">Diagnosis Sementara</div>
                            <p class="small text-secondary mb-2" id="diag-desc">Pilih gejala di atas untuk melihat detail diagnosis.</p>
                            <div class="badge bg-danger text-white py-2 px-3" id="diag-severity">TINGKAT DARURAT: TINGGI</div>
                        </div>
                    </div>
                </div>

                <!-- Column 2: Quiz Engine -->
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="quiz-container">
                        <span class="badge bg-success text-dark mb-3 px-3 py-2 rounded-pill fw-bold">DAILY PET TRIVIA</span>
                        <h3 class="fw-800 mb-2">Uji IQ Kepemilikan Anda</h3>
                        <p class="text-secondary mb-4">Ikuti kuis mini edukatif ini untuk mengukur seberapa baik Anda memahami anabul Anda.</p>
                        
                        <div id="quiz-box">
                            <h5 class="fw-bold mb-3" id="quiz-question">Pertanyaan 1: Apakah makanan berikut ini aman dikonsumsi oleh kucing Anda?</h5>
                            
                            <div class="quiz-option" onclick="checkAnswer(this, false)">
                                <span>Cokelat Hitam Premium</span>
                                <i class="fa-regular fa-circle"></i>
                            </div>

                            <div class="quiz-option" onclick="checkAnswer(this, true)">
                                <span>Ikan Salmon Rebus Tanpa Garam</span>
                                <i class="fa-regular fa-circle"></i>
                            </div>

                            <div class="quiz-option" onclick="checkAnswer(this, false)">
                                <span>Susu Sapi Segar (Laktosa Tinggi)</span>
                                <i class="fa-regular fa-circle"></i>
                            </div>
                        </div>

                        <div id="quiz-result" class="text-center d-none mt-3">
                            <div class="h4 text-info fw-bold">Selamat! Jawaban Anda Benar. 🎉</div>
                            <p class="small text-secondary">Kucing adalah karnivora obligat. Salmon rebus tanpa bumbu memberikan asupan protein murni dan omega-3 yang sangat sehat tanpa merusak ginjal mereka.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ SECTION -->
    <section class="py-5 bg-black-50">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-6" data-aos="fade-up">
                    <h2 class="fw-800 mb-2">Pertanyaan <span class="text-info">Populer (FAQ)</span></h2>
                    <p class="text-secondary mb-4">Jawaban cepat terhadap pertanyaan yang paling sering ditanyakan seputar kesehatan umum hewan peliharaan.</p>
                    
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#f1">
                                    Berapa kali sebaiknya anabul dimandikan?
                                </button>
                            </h2>
                            <div id="f1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Idealnya 1-2 bulan sekali kecuali jika kotor. Memandikan terlalu sering dapat merusak kelembapan minyak alami pada epidermis kulit mereka, memicu ketombe, kekeringan, dan iritasi parah.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#f2">
                                    Apakah susu sapi aman untuk kucing?
                                </button>
                            </h2>
                            <div id="f2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Sangat tidak disarankan. Kebanyakan kucing dewasa menderita Intoleransi Laktosa karena tubuh mereka berhenti memproduksi enzim laktase secara memadai pasca-sapih. Memberikan susu sapi dapat memicu diare parah dan dehidrasi.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#f3">
                                    Kapan anabul saya harus mulai mendapatkan vaksin?
                                </button>
                            </h2>
                            <div id="f3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Vaksinasi primer pertama harus diberikan sejak usia 6-8 minggu untuk kucing dan anjing, diikuti oleh penguat (booster) bulanan hingga usia mereka mencapai 16 minggu guna menjaga daya tahan imunitas tubuhnya.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="cta-banner">
                        <h2 class="fw-800 mb-3 text-dark">Tanya Komunitas</h2>
                        <p class="mb-4 text-dark opacity-75">Bingung dengan perilaku anabul Anda? Konsultasikan atau diskusikan langsung masalah Anda dengan ribuan pemilik hewan peliharaan berpengalaman lainnya di platform kami.</p>
                        <a href="index.php" class="btn btn-dark btn-lg rounded-pill px-5 fw-bold text-white">Join Community</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

   

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true });

        // INTERACTIVE BEHAVIOR DECODER
        const behaviors = {
            'cat-tail': {
                title: "Ekor Tegak Lurus ke Atas",
                desc: "Ini menandakan kucing Anda merasa sangat percaya diri, bahagia, ramah, dan siap berinteraksi atau bermain dengan Anda secara bersahabat.",
                status: "SUASANA HATI: POSITIF / SENANG",
                class: "bg-success"
            },
            'cat-slowblink': {
                title: "Berkedip Sangat Lambat (Slow Blink)",
                desc: "Merupakan tanda kasih sayang yang kuat. Ini setara dengan pelukan atau kecupan hangat kucing yang menunjukkan rasa percaya penuh terhadap Anda.",
                status: "SUASANA HATI: PENUH KASIH SAYANG",
                class: "bg-info"
            },
            'cat-ears': {
                title: "Telinga Mengarah ke Samping (Pesawat)",
                desc: "Tanda bahwa kucing sedang merasa cemas, takut, stres, atau waspada dengan lingkungan sekitarnya. Jangan langsung disentuh agar terhindar dari cakar.",
                status: "SUASANA HATI: WASPADA / TERTEKAN",
                class: "bg-danger"
            }
        };

        function decodeBehavior(key, element) {
            // Toggle active class
            document.querySelectorAll('.interactive-hotspot').forEach(item => item.classList.remove('active'));
            element.classList.add('active');

            // Set content
            const data = behaviors[key];
            document.getElementById('behavior-title').innerText = data.title;
            document.getElementById('behavior-desc').innerText = data.desc;
            
            const badge = document.getElementById('behavior-status');
            badge.innerText = data.status;
            badge.className = `badge ${data.class} px-3 py-2 mt-2`;
        }

        // ARTICLES FILTERING SYSTEM
        function filterCategory(category, button) {
            document.querySelectorAll('.filter-pill').forEach(pill => pill.classList.remove('active'));
            button.classList.add('active');

            const items = document.querySelectorAll('.article-item');
            items.forEach(item => {
                const tags = item.getAttribute('data-tags');
                if (category === 'all' || tags.includes(category)) {
                    item.classList.remove('d-none');
                } else {
                    item.classList.add('d-none');
                }
            });
        }

        function filterArticles() {
            const query = document.getElementById('articleSearch').value.toLowerCase();
            const items = document.querySelectorAll('.article-item');

            items.forEach(item => {
                const title = item.querySelector('.edu-card-title').innerText.toLowerCase();
                const desc = item.querySelector('.text-secondary').innerText.toLowerCase();
                if (title.includes(query) || desc.includes(query)) {
                    item.classList.remove('d-none');
                } else {
                    item.classList.add('d-none');
                }
            });
        }

        // ADVANCED LABORATORIUM ENGINE (TAB CONTROLLER)
        function switchLabTab(tabId, btn) {
            // Deactivate all
            document.querySelectorAll('.tab-btn-custom').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-pane-custom').forEach(pane => pane.classList.add('d-none'));

            // Activate current
            btn.classList.add('active');
            document.getElementById(tabId).classList.remove('d-none');
        }

        function calculateAdvancedAge() {
            const type = document.getElementById('petTypeV2').value;
            const age = parseFloat(document.getElementById('petAgeV2').value);
            const resultBox = document.getElementById('ageResultV2');
            
            if (isNaN(age) || age < 0) {
                alert("Masukkan umur anabul yang valid!");
                return;
            }

            let humanAge = 0;
            let stage = "";

            if (type === 'cat') {
                if (age === 1) { humanAge = 15; stage = "Tahap Remaja (Adolescent)"; }
                else if (age === 2) { humanAge = 24; stage = "Tahap Dewasa (Adult)"; }
                else { humanAge = 24 + ((age - 2) * 4); stage = age >= 11 ? "Tahap Senior (Geriatric)" : "Tahap Dewasa Matang"; }
            } else {
                let factor = 4;
                if (type === 'dog-giant') factor = 7;
                else if (type === 'dog-medium') factor = 5;

                if (age === 1) { humanAge = 15; stage = "Anak-Anak (Puppy)"; }
                else if (age === 2) { humanAge = 24; stage = "Tahap Dewasa Utama"; }
                else { humanAge = 24 + ((age - 2) * factor); stage = age >= 9 ? "Tahap Senior" : "Tahap Dewasa"; }
            }

            document.getElementById('ageOutputVal').innerHTML = `± ${humanAge} Tahun`;
            document.getElementById('ageOutputStage').innerHTML = stage;
            resultBox.classList.remove('d-none');
        }

        function calculateAdvancedCalories() {
            const weight = parseFloat(document.getElementById('petWeightCal').value);
            const factor = parseFloat(document.getElementById('petActivityCal').value);
            const resultBox = document.getElementById('calorieResultV2');

            if (isNaN(weight) || weight <= 0) {
                alert("Masukkan berat badan yang valid!");
                return;
            }

            // Standard RER Formula: 70 * (Weight)^0.75
            const rer = Math.round(70 * Math.pow(weight, 0.75));
            const totalCalories = Math.round(rer * factor);

            document.getElementById('calorieOutputVal').innerHTML = `${totalCalories} kCal / Hari`;
            resultBox.classList.remove('d-none');
        }

        function calculateHydration() {
            const weight = parseFloat(document.getElementById('petWeightWater').value);
            const resultBox = document.getElementById('hydrationResultV2');

            if (isNaN(weight) || weight <= 0) {
                alert("Masukkan berat badan yang valid!");
                return;
            }

            // Standard Formula: 50ml per Kg berat badan
            const minWater = Math.round(weight * 50);
            const maxWater = Math.round(weight * 60);

            document.getElementById('hydrationOutputVal').innerHTML = `${minWater} - ${maxWater} mL / Hari`;
            resultBox.classList.remove('d-none');
        }

        // SYMPTOM CHECKER ENGINE
        const diagnostics = {
            'vomit': {
                title: "Dugaan: Gastroenteritis atau Obstruksi Benda Asing",
                desc: "Kondisi di mana lambung anabul mengalami peradangan akut. Apabila muntahan berwarna empedu kuning/hijau, segera puasakan anabul selama 6 jam dan hubungi dokter hewan jika berlanjut.",
                severity: "EMERGENCY: MEDIUM-HIGH",
                badgeClass: "bg-warning"
            },
            'lethargy': {
                title: "Dugaan: Infeksi Patogen Sistemik (Virus/Parasit Darah)",
                desc: "Merupakan gejala yang sangat umum namun berbahaya. Lemas ekstrem yang diiringi penurunan napsu makan drastis menunjukkan tanda pertahanan sel tubuh menurun terhadap serangan infeksi kronis.",
                severity: "EMERGENCY: HIGH",
                badgeClass: "bg-danger"
            },
            'itchy': {
                title: "Dugaan: Ear Mites (Otodectes Cynotis)",
                desc: "Merupakan serangan mikro-parasit kutu telinga yang berkembang biak dengan memakan sebum liang telinga. Diperlukan obat tetes telinga khusus mengandung selamectin / doramectin.",
                severity: "EMERGENCY: LOW-MEDIUM",
                badgeClass: "bg-info"
            }
        };

        function checkSymptom(key, element) {
            document.querySelectorAll('.symptom-item').forEach(item => item.classList.remove('active'));
            element.classList.add('active');

            const data = diagnostics[key];
            const resultBox = document.getElementById('checker-result');
            
            document.getElementById('diag-title').innerText = data.title;
            document.getElementById('diag-desc').innerText = data.desc;
            
            const sevBadge = document.getElementById('diag-severity');
            sevBadge.innerText = data.severity;
            sevBadge.className = `badge ${data.badgeClass} text-white py-2 px-3`;
            
            resultBox.classList.remove('d-none');
        }

        // TRIVIA QUIZ ENGINE
        function checkAnswer(element, isCorrect) {
            // Clear previous states
            document.querySelectorAll('.quiz-option').forEach(opt => {
                opt.classList.remove('correct', 'wrong');
                opt.querySelector('i').className = "fa-regular fa-circle";
            });

            const icon = element.querySelector('i');
            if (isCorrect) {
                element.classList.add('correct');
                icon.className = "fa-regular fa-circle-check";
                document.getElementById('quiz-result').classList.remove('d-none');
            } else {
                element.classList.add('wrong');
                icon.className = "fa-regular fa-circle-xmark";
                document.getElementById('quiz-result').classList.add('d-none');
            }
        }
    </script>
</body>
</html>