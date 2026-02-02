<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="description" content="AIESCCS — The next-gen RFID Computer Laboratory Attendance System with AI-driven scanning.">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AIESCCS — COMPUTER LABORATORY ATTENDANCE SYSTEM</title>
  
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;800&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  
  <style>
    :root {
      --primary: #3b82f6;
      --primary-glow: rgba(59, 130, 246, 0.5);
      --accent: #60a5fa;
      --bg-dark: #0f172a;
    }

    body {
      font-family: 'Outfit', 'Inter', sans-serif;
      background-color: var(--bg-dark);
      scroll-behavior: smooth;
    }

    /* 3D Perspective Container */
    .perspective-container {
      perspective: 1200px;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 70vh;
      padding: 2rem;
    }

    /* The Scanner Card */
    .scanner-card {
      position: relative;
      width: 100%;
      max-width: 450px;
      aspect-ratio: 3/4;
      background: rgba(255, 255, 255, 0.03);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 2rem;
      transform-style: preserve-3d;
      transition: transform 0.1s ease-out, box-shadow 0.3s ease;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      overflow: hidden;
    }

    .scanner-card:hover {
      box-shadow: 0 0 40px var(--primary-glow);
    }

    /* AI Scanning Lights */
    .scan-orbit {
      position: absolute;
      width: 280px;
      height: 280px;
      border: 2px dashed rgba(59, 130, 246, 0.3);
      border-radius: 50%;
      animation: spin 10s linear infinite;
    }

    .scan-orbit-inner {
      position: absolute;
      width: 200px;
      height: 200px;
      border: 1px solid rgba(96, 165, 250, 0.4);
      border-radius: 50%;
      animation: spin-reverse 6s linear infinite;
    }

    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes spin-reverse { from { transform: rotate(360deg); } to { transform: rotate(0deg); } }

    .scanning-line {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, transparent, var(--primary), transparent);
      box-shadow: 0 0 15px var(--primary);
      opacity: 0.8;
      z-index: 10;
      animation: scan-move 3s ease-in-out infinite;
    }

    @keyframes scan-move {
      0%, 100% { transform: translateY(50px); opacity: 0; }
      10%, 90% { opacity: 1; }
      50% { transform: translateY(400px); }
    }

    .hologram-effect {
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at 50% 50%, rgba(59, 130, 246, 0.1), transparent 70%);
      pointer-events: none;
    }

    /* Typography Polish */
    .tech-text {
      font-family: 'Orbitron', sans-serif;
      letter-spacing: 0.1em;
      text-transform: uppercase;
    }

    .glass-nav {
      background: rgba(15, 23, 42, 0.7);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .btn-hologram {
      position: relative;
      background: rgba(59, 130, 246, 0.1);
      border: 1px solid var(--primary);
      color: white;
      transition: all 0.3s ease;
      overflow: hidden;
    }

    .btn-hologram:hover {
      background: var(--primary);
      box-shadow: 0 0 20px var(--primary-glow);
      transform: translateY(-2px);
    }

    /* Hidden RFID input */
    #rfid-input {
      position: absolute;
      opacity: 0;
      top: 0;
      left: 0;
      pointer-events: none;
    }

    /* Transition Portal Overlay */
    #portal-overlay {
      position: fixed;
      inset: 0;
      background: black;
      z-index: 100;
      display: none;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.8s ease;
    }

    .portal-ring {
      width: 0;
      height: 0;
      border: 4px solid var(--primary);
      border-radius: 50%;
      box-shadow: 0 0 100px var(--primary);
      transition: all 1s cubic-bezier(0.19, 1, 0.22, 1);
    }

    /* Hero Layout */
    .vignette:before {
      content: '';
      position: absolute;
      inset: 0;
      pointer-events: none;
      background: radial-gradient(circle at center, transparent 20%, rgba(15, 23, 42, 0.9) 100%);
      z-index: 1;
    }

    .bg-image {
      background-image: url('assets/img/TOpe.png');
      background-size: cover;
      background-position: center;
      transition: transform 10s ease;
    }

    header:hover .bg-image {
      transform: scale(1.1);
    }
    
    .animate-fade-in { animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>

<body class="text-white overflow-x-hidden">

  <!-- Portal Transition Layer -->
  <div id="portal-overlay">
    <div class="portal-ring" id="portal-ring"></div>
  </div>

  <nav class="fixed top-0 w-full glass-nav z-50">
    <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
      <div class="flex items-center gap-3">
        <img src="assets/img/logo.png" alt="Logo" class="w-12 h-12 object-contain bg-white rounded-full p-1">
        <div class="flex flex-col">
          <span class="text-lg font-bold tech-text text-blue-400 leading-none">RCLAMS-CCS</span>
          <span class="text-[10px] uppercase tracking-widest text-gray-400">Santa Rita College of Pampanga</span>
          <span class="text-[9px] uppercase tracking-tight text-blue-500/60 font-semibold">College of Computer Studies</span>
        </div>
      </div>
      <div class="hidden md:flex items-center gap-8">
        <a href="#about" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">About</a>
        <a href="login.php" class="text-xs px-4 py-2 bg-white/5 border border-white/10 rounded-full hover:bg-white/10 transition-all">Admin Portal</a>
        <a href="teacher/teacher_login.php" class="text-xs px-5 py-2.5 bg-blue-600 rounded-full font-bold shadow-lg shadow-blue-500/20 hover:bg-blue-500 transition-all">Faculty Portal</a>
      </div>
    </div>
  </nav>

  <header class="relative w-full h-screen flex flex-col items-center justify-center overflow-hidden">
    <!-- Hero Background -->
    <div class="absolute inset-0 bg-image"></div>
    <div class="absolute inset-0 bg-black/40 z-[1]"></div>
    <div class="absolute inset-0 vignette z-[2]"></div>

    <div class="relative z-10 w-full max-w-6xl px-6 grid lg:grid-cols-2 gap-12 items-center">
      
      <div class="text-center lg:text-left space-y-6">
        <div class="inline-block px-3 py-1 bg-blue-500/10 border border-blue-500/20 rounded-full text-blue-400 text-xs font-bold tech-text animate-pulse">
          AI-Driven System Active
        </div>
        <h1 class="text-5xl md:text-7xl font-black leading-tight">
          RFID <br> 
  <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-cyan-400 to-blue-500">COMPUTER LABORATORY ATTENDANCE MONITORING SYSTEM</span>
        </h1>
        <p class="text-lg text-gray-300 max-w-lg leading-relaxed font-light">
          Tap your RFID card in the Student Dashboard. Experience the future of campus laboratory management.
        </p>
        <div class="flex flex-wrap gap-4 justify-center lg:justify-start pt-4">
          <div class="flex items-center gap-2 text-sm text-gray-400">
            <i class="fas fa-shield-halved text-blue-500"></i> Secure Encryption
          </div>
          <div class="flex items-center gap-2 text-sm text-gray-400">
            <i class="fas fa-bolt text-cyan-400"></i> Real-time Sync
          </div>
        </div>
      </div>

      <div class="perspective-container">
        <div class="scanner-card" id="scanner-card">
          <div class="scanning-line"></div>
          <div class="scan-orbit"></div>
          <div class="scan-orbit-inner"></div>
          <div class="hologram-effect"></div>
          
          <div class="relative z-20 space-y-6 px-6">
            <div id="scanner-icon-wrap" class="w-24 h-24 mx-auto rounded-3xl bg-blue-600/20 flex items-center justify-center border border-blue-400/30 shadow-[0_0_50px_rgba(59,130,246,0.3)] transition-all duration-500">
              <i class="fas fa-rss text-3xl text-blue-400 animate-pulse"></i>
            </div>
            
            <div id="scanner-default-ui">
                <h3 class="tech-text text-xl font-bold text-white mb-2">RFID Scanner</h3>
                <p class="text-sm text-blue-300/80 font-medium tracking-widest uppercase mb-6">Place StudentRFID Near Reader</p>
            </div>

            <div id="student-preview" class="hidden animate-fade-in space-y-4">
                <img id="prev-img" src="" class="w-20 h-20 rounded-full mx-auto border-4 border-blue-500 shadow-xl">
                <div class="text-center">
                    <p class="text-blue-400 text-xs font-bold tech-text mb-1">Authenticated</p>
                    <p id="prev-name" class="font-bold text-xl"></p>
                </div>
            </div>
            
            <div id="scan-status" class="px-6 py-3 rounded-xl bg-white/5 border border-white/10 text-xs text-gray-400 tracking-wider transition-all duration-300">
              READY FOR INPUT
            </div>
          </div>

          <!-- Interactive Bottom Shadow -->
          <div class="absolute bottom-[-50px] w-[80%] h-[20px] bg-blue-500/40 blur-[40px] opacity-0 group-hover:opacity-100 transition-opacity"></div>
        </div>
      </div>

    </div>

    <!-- Hidden Input -->
    <input type="text" id="rfid-input" autofocus autocomplete="off">
    
    <!-- Swipe down indicator -->
    <a href="#about" class="absolute bottom-10 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-2 text-xs text-gray-500 hover:text-white transition-colors">
      <span>EXPLORE SYSTEM</span>
      <div class="w-[1px] h-12 bg-gradient-to-b from-blue-500 to-transparent"></div>
    </a>
  </header>

  <!-- About Section -->
  <section id="about" class="relative py-24 bg-slate-950">
    <div class="max-w-7xl mx-auto px-6">
      <div class="grid lg:grid-cols-2 gap-16 items-center">
        <div>
          <h2 class="text-4xl md:text-5xl font-black mb-8 leading-tight">
            Elevating the <br> <span class="text-blue-500">Academic Experience</span>
          </h2>
          <div class="space-y-6">
            <div class="p-6 rounded-2xl bg-white/5 border border-white/10 group hover:bg-blue-600/5 transition-all">
              <div class="flex gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform">
                  <i class="fas fa-fingerprint text-xl"></i>
                </div>
                <div>
                  <h4 class="text-lg font-bold mb-1">Instant Authentication</h4>
                  <p class="text-sm text-gray-400 font-light">Eliminate manual logs. A single tap identifies and registers your presence in the lab instantly.</p>
                </div>
              </div>
            </div>
            <div class="p-6 rounded-2xl bg-white/5 border border-white/10 group hover:bg-cyan-600/5 transition-all">
              <div class="flex gap-4">
                <div class="w-12 h-12 rounded-xl bg-cyan-500/20 flex items-center justify-center text-cyan-400 group-hover:scale-110 transition-transform">
                  <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                  <h4 class="text-lg font-bold mb-1">Performance Analytics</h4>
                  <p class="text-sm text-gray-400 font-light">View the student attendance history, subject assignments, and lab usage statistics via the dashboard.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="space-y-4 pt-12">
                <div class="p-8 rounded-3xl bg-blue-600 shadow-2xl shadow-blue-500/20">
                    <h3 class="text-3xl font-black mb-2">100%</h3>
                    <p class="text-xs uppercase tracking-widest text-blue-200">System Accuracy</p>
                </div>
                <div class="p-8 rounded-3xl bg-slate-900 border border-white/5">
                    <h3 class="text-3xl font-black mb-2">Realtime</h3>
                    <p class="text-xs uppercase tracking-widest text-gray-500">Data Sync</p>
                </div>
            </div>
            <div class="space-y-4">
                <div class="p-8 rounded-3xl bg-slate-900 border border-white/5">
                    <h3 class="text-3xl font-black mb-2">Secure</h3>
                    <p class="text-xs uppercase tracking-widest text-gray-500">RFID Encrypted</p>
                </div>
                <div class="p-8 rounded-3xl bg-cyan-500">
                    <h3 class="text-3xl font-black mb-2">Fast</h3>
                    <p class="text-xs uppercase tracking-widest text-cyan-200">0.2s Verification</p>
                </div>
            </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="py-12 border-t border-white/5 bg-slate-950">
    <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-6">
      <div class="flex items-center gap-3">
        <img src="assets/img/logo.png" alt="Logo" class="w-8 h-8 opacity-50">
        <span class="text-xs text-gray-500">© 2025 Santa Rita College. Developed by Christopher Panoy.</span>
      </div>
      <div class="flex gap-8 text-xs text-gray-500 font-medium">
        <a href="#" class="hover:text-blue-400 transition-colors">Privacy Policy</a>
        <a href="#" class="hover:text-blue-400 transition-colors">Terms of Service</a>
        <a href="#" class="hover:text-blue-400 transition-colors">Contact MIS</a>
      </div>
    </div>
  </footer>

  <script>
    // 3D Tilt Effect
    const card = document.getElementById('scanner-card');
    const container = document.querySelector('.perspective-container');

    container.addEventListener('mousemove', (e) => {
      const rect = container.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      
      const centerX = rect.width / 2;
      const centerY = rect.height / 2;
      
      const rotateX = (y - centerY) / 8;
      const rotateY = (centerX - x) / 8;
      
      card.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    });

    container.addEventListener('mouseleave', () => {
      card.style.transform = `rotateX(0deg) rotateY(0deg)`;
    });

    // RFID Input Focus
    const rfidInput = document.getElementById('rfid-input');
    const scanStatus = document.getElementById('scan-status');
    const portalOverlay = document.getElementById('portal-overlay');
    const portalRing = document.getElementById('portal-ring');

    document.addEventListener('click', () => rfidInput.focus());
    
    rfidInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const value = rfidInput.value.trim();
        if (value) handleScan(value);
        rfidInput.value = '';
      }
    });

    async function handleScan(rfid) {
      scanStatus.textContent = 'AUTHENTICATING...';
      scanStatus.classList.add('text-blue-400', 'animate-pulse');
      
      try {
        const formData = new FormData();
        formData.append('rfid', rfid);
        
        const response = await fetch('ajax/student_rfid_login.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
          scanStatus.textContent = 'ACCESS GRANTED';
          scanStatus.className = 'px-6 py-3 rounded-xl bg-green-500/20 border border-green-500/30 text-xs text-green-400 tracking-wider font-bold';
          
          // Hide default UI, show student
          document.getElementById('scanner-default-ui').classList.add('hidden');
          document.getElementById('scanner-icon-wrap').classList.add('hidden');
          
          const preview = document.getElementById('student-preview');
          preview.classList.remove('hidden');
          document.getElementById('prev-name').textContent = data.student.name;
          document.getElementById('prev-img').src = data.student.photo;
          
          // Start Portal Animation
          setTimeout(() => {
            portalOverlay.style.display = 'flex';
            setTimeout(() => {
              portalOverlay.style.opacity = '1';
              portalRing.style.width = '300vw';
              portalRing.style.height = '300vw';
            }, 50);
            
            setTimeout(() => {
              window.location.href = data.redirect;
            }, 1200);
          }, 1000);

        } else {
          scanStatus.textContent = data.message.toUpperCase();
          scanStatus.className = 'px-6 py-3 rounded-xl bg-red-500/20 border border-red-500/30 text-xs text-red-400 tracking-wider font-bold';
          setTimeout(() => {
            scanStatus.textContent = 'READY FOR INPUT';
            scanStatus.className = 'px-6 py-3 rounded-xl bg-white/5 border border-white/10 text-xs text-gray-400 tracking-wider';
          }, 3000);
        }
      } catch (err) {
        console.error(err);
        scanStatus.textContent = 'CONNECTION ERROR';
      }
    }
  </script>

</body>
</html>
