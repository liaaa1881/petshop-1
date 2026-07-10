<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petshop Pro - Cybernetic Interface v4.0</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800;900&family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* --- DESIGN SYSTEM & CSS VARIABLES --- */
        :root {
            --neon-cyan: #0dcaf0;
            --neon-purple: #bd00ff;
            --neon-amber: #ffaa00;
            --bg-deep: #02040a;
            --glass-base: rgba(5, 8, 22, 0.65);
            --border-glass: rgba(13, 202, 240, 0.15);
            --font-cyber: 'Orbitron', sans-serif;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
        }

        /* --- GLOBAL SETUP --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-deep);
            color: #ffffff;
            font-family: var(--font-sans);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            perspective: 2000px; /* Kedalaman perspektif 3D ekstrim */
        }

        /* --- ADVANCED BACKGROUND AMBIENT --- */
        .ambient-engine {
            position: absolute;
            width: 100vw;
            height: 100vh;
            top: 0;
            left: 0;
            z-index: 1;
            overflow: hidden;
        }

        /* Grid Perspektif 3D yang Bergerak */
        .cyber-grid {
            position: absolute;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            background-image: 
                linear-gradient(rgba(13, 202, 240, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(13, 202, 240, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
            transform: rotateX(75deg);
            transform-origin: center center;
            animation: gridScroll 30s linear infinite;
            opacity: 0.4;
        }

        @keyframes gridScroll {
            0% { background-position: 0 0; }
            100% { background-position: 0 1000px; }
        }

        /* Pusaran Cahaya Nebula */
        .nebula-cyan {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(13, 202, 240, 0.12) 0%, transparent 70%);
            top: 10%;
            left: 15%;
            filter: blur(80px);
            animation: nebulaDrift 20s ease-in-out infinite alternate;
        }

        .nebula-purple {
            position: absolute;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(189, 0, 255, 0.1) 0%, transparent 70%);
            bottom: 10%;
            right: 15%;
            filter: blur(100px);
            animation: nebulaDrift 25s ease-in-out infinite alternate-reverse;
        }

        @keyframes nebulaDrift {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(80px, 50px) scale(1.2); }
        }

        /* Partikel Debu Kosmik */
        .cosmic-dust {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(rgba(255, 255, 255, 0.15) 1px, transparent 1px),
                radial-gradient(rgba(13, 202, 240, 0.2) 1.5px, transparent 1.5px);
            background-size: 120px 120px;
            background-position: 0 0, 40px 60px;
            opacity: 0.3;
            animation: dustFloat 40s linear infinite;
        }

        @keyframes dustFloat {
            0% { transform: translateY(0); }
            100% { transform: translateY(-120px); }
        }

        /* --- CORE 3D WRAPPER --- */
        .stage-3d {
            position: relative;
            z-index: 5;
            width: 100%;
            max-width: 780px;
            padding: 20px;
            transform-style: preserve-3d;
            /* Siklus Gerakan Rotasi 3D Kompleks */
            animation: complexAuto3D 12s cubic-bezier(0.45, 0.05, 0.55, 0.95) infinite alternate;
        }

        @keyframes complexAuto3D {
            0% {
                transform: rotateX(8deg) rotateY(-12deg) rotateZ(-1deg) translateY(-5px);
            }
            33% {
                transform: rotateX(-5deg) rotateY(10deg) rotateZ(1deg) translateY(10px);
            }
            66% {
                transform: rotateX(10deg) rotateY(8deg) rotateZ(-2deg) translateY(-8px);
            }
            100% {
                transform: rotateX(-6deg) rotateY(-10deg) rotateZ(2deg) translateY(5px);
            }
        }

        /* --- CARDS METADATA & DECORATIONS (HUD) --- */
        .hud-bracket {
            position: absolute;
            width: 30px;
            height: 30px;
            border-color: var(--neon-cyan);
            border-style: solid;
            opacity: 0.5;
            transform: translateZ(80px);
            animation: bracketBlink 4s infinite;
        }
        .top-left { top: -10px; left: -10px; border-width: 3px 0 0 3px; }
        .top-right { top: -10px; right: -10px; border-width: 3px 3px 0 0; }
        .bot-left { bottom: -10px; left: -10px; border-width: 0 0 3px 3px; }
        .bot-right { bottom: -10px; right: -10px; border-width: 0 3px 3px 0; }

        @keyframes bracketBlink {
            0%, 100% { opacity: 0.2; }
            50% { opacity: 0.8; }
        }

        /* --- MAIN QUANTUM CARD --- */
        .quantum-card {
            background: var(--glass-base);
            backdrop-filter: blur(25px) saturate(160%);
            -webkit-backdrop-filter: blur(25px) saturate(160%);
            border-radius: 40px;
            padding: 60px 50px;
            border: 1px solid var(--border-glass);
            box-shadow: 
                0 30px 80px rgba(0, 0, 0, 0.6),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 100px rgba(13, 202, 240, 0.05),
                0 0 100px rgba(189, 0, 255, 0.05);
            transform-style: preserve-3d;
            position: relative;
            overflow: hidden;
        }

        /* Garis Pemindai Laser (Scan Line Effect) */
        .laser-scanner {
            position: absolute;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--neon-cyan), transparent);
            left: 0;
            opacity: 0.7;
            box-shadow: 0 0 15px var(--neon-cyan);
            animation: laserScan 6s linear infinite;
        }

        @keyframes laserScan {
            0% { top: -5%; }
            100% { top: 105%; }
        }

        /* --- PARALLAX LAYERS (Depth Control) --- */
        
        /* Lapisan Terluar (Z-Index Terjauh Depan) */
        .layer-extreme {
            transform: translateZ(90px);
            transform-style: preserve-3d;
        }

        /* Lapisan Tengah */
        .layer-mid {
            transform: translateZ(45px);
            transform-style: preserve-3d;
        }

        /* Lapisan Dasar */
        .layer-base {
            transform: translateZ(15px);
        }

        /* --- CYBER CORE ENGINE (Paw & Orbit) --- */
        .core-engine {
            position: relative;
            width: 130px;
            height: 130px;
            margin: 0 auto 40px;
            transform-style: preserve-3d;
        }

        /* Cincin Orbit Berputar */
        .cyber-ring {
            position: absolute;
            border-radius: 50%;
            border: 2px dashed rgba(13, 202, 240, 0.3);
            top: -15px; left: -15px; right: -15px; bottom: -15px;
            animation: spinRing 12s linear infinite;
            transform: translateZ(10px);
        }

        .cyber-ring-outer {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(189, 0, 255, 0.25);
            border-left-color: var(--neon-purple);
            border-right-color: var(--neon-purple);
            top: -30px; left: -30px; right: -30px; bottom: -30px;
            animation: spinRingReverse 20s linear infinite;
            transform: translateZ(5px);
        }

        @keyframes spinRing {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes spinRingReverse {
            0% { transform: rotate(360deg); }
            100% { transform: rotate(0deg); }
        }

        /* Core Paw Glowing */
        .paw-quantum-emitter {
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(13, 202, 240, 0.1) 0%, rgba(5, 8, 22, 0.8) 100%);
            border-radius: 50%;
            border: 3px solid var(--neon-cyan);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.2rem;
            color: var(--neon-cyan);
            box-shadow: 
                0 0 30px rgba(13, 202, 240, 0.4),
                inset 0 0 20px rgba(13, 202, 240, 0.2);
            animation: corePulse 3s cubic-bezier(0.4, 0, 0.2, 1) infinite alternate;
        }

        @keyframes corePulse {
            0% {
                transform: translateZ(30px) scale(0.95);
                box-shadow: 0 0 20px rgba(13, 202, 240, 0.3), inset 0 0 15px rgba(13, 202, 240, 0.1);
                color: #ffffff;
            }
            100% {
                transform: translateZ(45px) scale(1.05);
                box-shadow: 0 0 45px rgba(13, 202, 240, 0.7), inset 0 0 30px rgba(13, 202, 240, 0.4);
                color: var(--neon-cyan);
            }
        }

        /* --- SYSTEM STATUS BAR (HUD) --- */
        .system-status {
            display: flex;
            justify-content: center;
            gap: 20px;
            font-family: var(--font-cyber);
            font-size: 0.7rem;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, 0.4);
            margin-bottom: 25px;
            transform: translateZ(40px);
        }

        .status-node {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--neon-cyan);
            box-shadow: 0 0 8px var(--neon-cyan);
            animation: blinkNode 1s ease infinite alternate;
        }

        .status-dot.purple {
            background-color: var(--neon-purple);
            box-shadow: 0 0 8px var(--neon-purple);
        }

        @keyframes blinkNode {
            0% { opacity: 0.3; }
            100% { opacity: 1; }
        }

        /* --- FUTURISTIC TYPOGRAPHY --- */
        .cyber-title {
            font-family: var(--font-cyber);
            font-weight: 900;
            font-size: 2.8rem;
            letter-spacing: 6px;
            margin-bottom: 20px;
            text-transform: uppercase;
            position: relative;
            background: linear-gradient(135deg, #ffffff 30%, #a5f3fc 70%, var(--neon-cyan) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(13, 202, 240, 0.1);
        }

        /* Hologram Glitch Text Decoration */
        .cyber-title::before {
            content: "PETSHOP PRO";
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: transparent;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: -1px 0 var(--neon-purple);
            clip-path: rect(44px, 450px, 56px, 0);
            animation: glitchEffect 4s infinite linear alternate-reverse;
            opacity: 0.7;
        }

        @keyframes glitchEffect {
            0% { clip-path: inset(40% 0 61% 0); }
            20% { clip-path: inset(92% 0 1% 0); }
            40% { clip-path: inset(15% 0 80% 0); }
            60% { clip-path: inset(80% 0 5% 0); }
            80% { clip-path: inset(3% 0 92% 0); }
            100% { clip-path: inset(60% 0 25% 0); }
        }

        .cyber-description {
            font-size: 0.95rem;
            color: #94a3b8;
            max-width: 85%;
            margin: 0 auto 40px;
            line-height: 1.8;
            letter-spacing: 0.5px;
            font-weight: 300;
        }

        /* --- THE ACTION EMITTER (Button) --- */
        .quantum-button-container {
            position: relative;
            display: inline-block;
            transform-style: preserve-3d;
            transform: translateZ(60px);
        }

        .btn-quantum {
            position: relative;
            font-family: var(--font-cyber);
            background: transparent;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 3px;
            padding: 16px 45px;
            border-radius: 12px;
            border: 1px solid var(--neon-cyan);
            text-transform: uppercase;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 15px;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            box-shadow: 
                0 0 20px rgba(13, 202, 240, 0.15),
                inset 0 0 10px rgba(13, 202, 240, 0.1);
        }

        .btn-quantum::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(13, 202, 240, 0.25), transparent);
            transition: 0.5s;
        }

        .btn-quantum:hover::before {
            left: 100%;
        }

        .btn-quantum:hover {
            color: #000000;
            background: var(--neon-cyan);
            box-shadow: 
                0 0 40px rgba(13, 202, 240, 0.6),
                0 0 80px rgba(13, 202, 240, 0.2);
            transform: scale(1.05) translateZ(15px);
            border-color: #ffffff;
        }

        /* Animasi Panah Kinetik */
        .btn-quantum i {
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }

        .btn-quantum:hover i {
            transform: translateX(6px);
        }

        /* --- SIDE FLOATING WIDGETS --- */
        .float-widget {
            position: absolute;
            background: rgba(5, 8, 22, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 12px 20px;
            border-radius: 12px;
            font-family: var(--font-cyber);
            font-size: 0.65rem;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            pointer-events: none;
        }

        .widget-left {
            left: -80px;
            top: 40%;
            transform: rotateY(25deg) translateZ(50px);
            border-left: 3px solid var(--neon-cyan);
        }

        .widget-right {
            right: -80px;
            bottom: 30%;
            transform: rotateY(-25deg) translateZ(50px);
            border-right: 3px solid var(--neon-purple);
        }

        .widget-value {
            color: var(--neon-cyan);
            font-weight: 800;
            font-size: 0.75rem;
            margin-top: 4px;
        }

        /* --- FLOATING DECORATIVE FAUNA --- */
        .cyber-animal {
            position: absolute;
            font-size: 2.2rem;
            color: rgba(13, 202, 240, 0.3);
            animation: animalFloat 5s ease-in-out infinite alternate;
        }

        .cat-sensor {
            bottom: 40px;
            right: 50px;
            animation-delay: 2.5s;
        }

        .dog-sensor {
            top: 40px;
            left: 50px;
        }

        @keyframes animalFloat {
            0% {
                transform: translateZ(50px) translateY(0) scale(1);
                opacity: 0.3;
                filter: drop-shadow(0 0 2px var(--neon-cyan));
            }
            100% {
                transform: translateZ(85px) translateY(-12px) scale(1.1);
                opacity: 0.7;
                filter: drop-shadow(0 0 15px var(--neon-cyan));
            }
        }

        /* --- FOOTER KREDENSIAL --- */
        .system-footer {
            position: absolute;
            bottom: 25px;
            font-family: var(--font-cyber);
            font-size: 0.65rem;
            letter-spacing: 3px;
            color: rgba(255, 255, 255, 0.25);
            width: 100%;
            text-align: center;
            z-index: 10;
            pointer-events: none;
        }

        .system-footer span {
            color: var(--neon-cyan);
        }

        /* Responsif Media */
        @media(max-width: 768px) {
            .quantum-card {
                padding: 40px 25px;
            }
            .cyber-title {
                font-size: 2rem;
            }
            .float-widget {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- Latar Belakang Sistem Terintegrasi -->
    <div class="ambient-engine">
        <div class="cyber-grid"></div>
        <div class="nebula-cyan"></div>
        <div class="nebula-purple"></div>
        <div class="cosmic-dust"></div>
    </div>

    <!-- Frame 3D Utama -->
    <div class="stage-3d">
        
        <!-- Braket HUD Pojok -->
        <div class="hud-bracket top-left"></div>
        <div class="hud-bracket top-right"></div>
        <div class="hud-bracket bot-left"></div>
        <div class="hud-bracket bot-right"></div>

        <!-- Widget Eksternal Melayang di Sisi Kiri -->
        

    

        <!-- Kartu Utama -->
        <div class="quantum-card">
            
            <!-- Laser Scanner Effect -->
            <div class="laser-scanner"></div>

            <!-- Elemen Parallax Depth -->
            <div class="layer-extreme">
                <div class="cyber-animal dog-sensor">
                    <i class="fa-solid fa-dog"></i>
                </div>
                <div class="cyber-animal cat-sensor">
                    <i class="fa-solid fa-cat"></i>
                </div>
            </div>

            <!-- Bagian Atas: Engine Utama & Orbit -->
            <div class="layer-mid">
                <div class="core-engine">
                    <div class="cyber-ring"></div>
                    <div class="cyber-ring-outer"></div>
                    <div class="paw-quantum-emitter">
                        <i class="fa-solid fa-paw"></i>
                    </div>
                </div>
            </div>

            <!-- Bagian Tengah: Status Sistem & Konten Teks -->
            <div class="layer-base text-center">
                
                <div class="system-status">
                    <div class="status-node">
                        <div class="status-dot"></div>
                    </div>
                    <div class="status-node">
                        <div class="status-dot purple"></div> 
                    </div>
                </div>

                <h1 class="cyber-title">Petshop Pro</h1>
                
                

                <!-- Tombol Interaksi Aksi -->
                <div class="quantum-button-container">
                    <a href="dashboard_utama.php" class="btn-quantum">
                        Masuk Ke Sistem <i class="fa-solid fa-microchip"></i>
                    </a>
                </div>

            </div>

        </div>
    </div>

    
</body>
</html>