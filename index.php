<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="description" content="Automated Ingress and Egress System for BSIS students — attendance, records, and secure access.">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AIESCCS — Automated Ingress & Egress System</title>
  <link rel="preload" href="assets/img/TOpe.png" as="image">
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <meta name="theme-color" content="#0a0a23">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Simple fade + slide-up animation */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-up { animation: fadeUp 700ms cubic-bezier(.2,.8,.2,1) both; }
    .animate-fade-up-delay { animation: fadeUp 900ms cubic-bezier(.2,.8,.2,1) 120ms both; }

    @media (prefers-reduced-motion: reduce) {
      .animate-fade-up, .animate-fade-up-delay { animation: none !important; }
    }

    .vignette:before {
      content: '';
      position: absolute;
      inset: 0;
      pointer-events: none;
      background: radial-gradient(ellipse at center, rgba(0,0,0,0) 40%, rgba(0,0,0,0.28) 100%);
      mix-blend-mode: multiply;
    }
    .hero-card-shadow { box-shadow: 0 10px 30px rgba(2,6,23,0.45); }
    .hero-heading {
      text-shadow: 0 2px 12px rgba(0,0,0,0.45), 0 1px 0 rgba(0,0,0,0.18);
      letter-spacing: 0.02em;
      word-break: break-word;
      font-size: clamp(1.4rem, 6.0vw, 3.8rem);
      line-height: 1.06;
      white-space: normal;
    }
    @media (min-width: 768px) {
      .hero-heading { letter-spacing: 0.04em; }
    }
    *, *:before, *:after { box-sizing: border-box; }

    .hero-card { width: 100%; max-width: 720px; margin-left: auto; margin-right: auto; }
    .logo-text { display: inline; }
    @media (max-width: 420px) {
      .logo-text { display: none; }
      .hero-card { padding-left: 1rem; padding-right: 1rem; }
    }
    .cta-btn { padding-top: 0.85rem; padding-bottom: 0.85rem; }
    @media (min-width: 640px) {
      .cta-btn { padding-top: 0.9rem; padding-bottom: 0.9rem; }
    }
    @media (max-width: 640px) {
      #about { padding-top: 2.5rem; padding-bottom: 2.5rem; }
      .table-section, .p-6 { padding-left: 1rem; padding-right: 1rem; }
    }

    /* New added professional tweaks */
    nav { background-color: #ffffff; }
    nav .nav-link:hover { color: #1e40af; } /* blue on hover */
    .btn-primary { background-color: #1e40af; color: #ffffff; }
    .btn-primary:hover { background-color: #1e3a8a; } /* darker blue */
    footer { background-color: #f3f4f6; color: #4b5563; }
    .footer-credit { font-size: 0.875rem; color: #6b7280; }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="min-h-screen bg-gray-50 text-gray-800 overflow-x-hidden flex flex-col">

  <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:bg-white/90 focus:text-gray-900 focus:px-3 focus:py-2 rounded">Skip to content</a>

  <!-- Top navbar (logo left, minimal links) -->
  <nav class="w-full shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-20 md:h-24 w-full">
        <div class="flex items-center gap-3">
          <div class="flex flex-col sm:flex-row sm:items-center items-start gap-2">
            <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-20 md:h-20 rounded-full bg-white/90 p-1 shadow-sm ring-2 ring-gray-100 overflow-hidden flex items-center justify-center">
              <img src="assets/img/logo.png" alt="Santa Rita College logo" class="w-full h-full object-cover block" onerror="this.style.display='none'">
            </div>
            <span class="text-sm sm:text-base md:text-lg font-semibold text-gray-800 select-none logo-text">College Of <br class="sm:hidden">Computer Studies</span>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <a href="#about" id="nav-about" class="nav-link text-base md:text-lg text-gray-700 hover:text-gray-900 flex items-center gap-2"><i class="fas fa-info-circle"></i><span>About</span></a>
          <div class="hidden sm:flex items-center gap-3">
            <a href="login.php" aria-label="MIS Log In" class="inline-flex items-center gap-2 px-3 py-2 btn-primary rounded-lg shadow text-sm font-semibold transition-colors">MIS Administrator Log In</a>
            <a href="teacher/teacher_login.php" aria-label="Faculty Log In" class="inline-flex items-center gap-2 px-3 py-2 bg-blue-500 text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors">Faculty Log In</a>
          </div>
          <div class="sm:hidden flex items-center gap-2">
            <a href="login.php" class="px-3 py-1 btn-primary rounded text-xs font-semibold">Admin</a>
            <a href="teacher/teacher_login.php" class="px-3 py-1 bg-blue-500 text-white rounded text-xs font-semibold hover:bg-blue-600 transition-colors">Faculty</a>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <!-- College history modal -->
  <div id="college-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60">
    <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-4 overflow-hidden">
      <div class="flex justify-between items-center p-4 border-b">
        <h3 class="text-lg font-bold">Santa Rita College — A Short History</h3>
        <button id="college-modal-close" class="text-gray-600 hover:text-gray-800 text-2xl">&times;</button>
      </div>
      <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2">
          <p class="text-sm text-gray-800 mb-4">Santa Rita College traces its beginnings to 1945–1946, when Miss Narcisa Gosioco, a teacher from Guagua, proposed the establishment of a secondary school in Santa Rita. Together with Doña Irene Pineda and other civic leaders, they founded what became Santa Rita Institute. Despite challenges, the school opened in 1947 and produced its first nine graduates.</p>
          <p class="text-sm text-gray-800 mb-4">By 1955, enrollment had grown to 450, prompting the construction of a new building. In 1965, the College Department opened with programs in Liberal Arts, Commerce, Education, and Secretarial courses, followed by the Elementary Department in 1966. That same year, SRI gained full college status and became Santa Rita College.</p>
          <p class="text-sm text-gray-800 mb-4">Over the decades, the institution has produced more than 11,000 graduates, including professionals in medicine, engineering, business, the military, and religious life. It has remained resilient through challenges such as the Mt. Pinatubo eruption and economic downturns.</p>
          <p class="text-sm text-gray-800">Today, Santa Rita College continues to provide quality education, maintaining a mission rooted in service to the community. It now offers programs such as BSED, BEED, BSAcct, BTTE in consortium with DHVTSU, and the recently added BSIS. AIESCCS.com is the official system portal for the Automated Ingress & Egress System.</p>
        </div>
        <div class="md:col-span-1 space-y-3">
          <img src="assets/img/logo.png" alt="SRC logo" class="w-full rounded" onerror="this.style.display='none'">
          <img src="assets/img/TOpe.png" alt="Campus" class="w-full rounded" onerror="this.style.display='none'">
          <div class="text-xs text-gray-500">If images are missing, add event photos to assets/img/ for a richer gallery.</div>
        </div>
      </div>
      <div class="p-4 border-t text-right">
        <button id="college-modal-close-2" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Close</button>
      </div>
    </div>
  </div>

  <!-- Hero Section -->
  <header class="relative w-full">
    <div class="absolute inset-0 vignette">
      <div class="w-full h-full bg-cover bg-center" style="background-image: url('assets/img/TOpe.png');"></div>
      <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/50 to-transparent"></div>
      <div class="absolute left-0 right-0 bottom-0 h-24 bg-gradient-to-t from-black/90 to-transparent pointer-events-none"></div>
    </div>

    <div class="relative max-w-8xl mx-auto px-2 sm:px-4 lg:px-6">
      <div id="main" class="pt-10 pb-0 lg:pt-16 lg:pb-6 min-h-[78vh] sm:min-h-[80vh] md:minh-[85vh] flex flex-col justify-center">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
          <div class="text-center z-10 lg:col-span-2">
            <div class="mx-auto max-w-4xl hero-card px-4 sm:px-6" >
              <div class="bg-white/10 backdrop-blur-sm rounded-2xl px-4 py-6 sm:p-6 lg:p-8 border border-white/20 animate-fade-up hero-card-shadow" style="background-color: rgba(255,255,255,0.12);">
                <h1 class="hero-heading font-black text-white leading-tight text-center">RFID COMPUTER LABORATORY ATTENDANCE MONITORING SYSTEM</h1>
                <p class="mt-4 text-base sm:text-lg text-white/90 max-w-full text-center">Securing access, streamlining attendance, and preserving integrity in every entry and exit.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- About / Features section -->
  <section id="about" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 bg-white">
    <div class="max-w-4xl mx-auto text-center">
      <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">About the Automated Ingress & Egress System (AIESCCS)</h2>
      <p class="mt-4 text-gray-700 text-base sm:text-lg">This is the official system portal at <a href="https://aiesccs.com" class="text-blue-600 hover:underline">AIESCCS.com</a>. The system was developed to streamline student access control, attendance, and laboratory management for BSIS students at Santa Rita College computer laboratories. It provides secure, accurate, and auditable records of ingress and egress, reduces manual attendance work, and gives faculty and administrators tools for monitoring, reporting, and analysis.</p>
    </div>

    <div class="mt-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="p-6 bg-gray-50 rounded-lg border shadow-sm hover:shadow-md transition-shadow duration-300">
        <div class="icon text-3xl mb-2 text-blue-500"><i class="fas fa-lock"></i></div>
        <h3 class="text-lg font-semibold">Security & Integrity</h3>
        <p class="mt-2 text-sm text-gray-700">Scan-based identification prevents fraudulent entries and maintains an auditable trail for every student ingress and egress event.</p>
      </div>

      <div class="p-6 bg-gray-50 rounded-lg border shadow-sm hover:shadow-md transition-shadow duration-300">
        <div class="icon text-3xl mb-2 text-blue-500"><i class="fas fa-clock"></i></div>
        <h3 class="text-lg font-semibold">Real-Time Attendance</h3>
        <p class="mt-2 text-sm text-gray-700">Attendance records update immediately upon scanning so teachers and admins can view up-to-the-minute lab usage and late/absent reports.</p>
      </div>

      <div class="p-6 bg-gray-50 rounded-lg border shadow-sm hover:shadow-md transition-shadow duration-300">
        <div class="icon text-3xl mb-2 text-blue-500"><i class="fas fa-file-export"></i></div>
        <h3 class="text-lg font-semibold">Reports & Export</h3>
        <p class="mt-2 text-sm text-gray-700">Generate filtered reports and export to Excel for documentation, audits, or further analysis.</p>
      </div>
    </div>

    <div class="mt-10 grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="p-6 bg-white rounded-lg border shadow-sm">
        <h3 class="text-xl font-bold text-gray-900">Core Features</h3>
        <ul class="mt-3 list-inside text-sm text-gray-700 space-y-2">
          <li class="flex items-start"><i class="fas fa-check text-green-500 mr-3 mt-1"></i>Scan-based ingress and egress recording</li>
          <li class="flex items-start"><i class="fas fa-check text-green-500 mr-3 mt-1"></i>Real-time attendance dashboard for faculty and administrators</li>
          <li class="flex items-start"><i class="fas fa-check text-green-500 mr-3 mt-1"></i>Manage students, teachers, subjects, and assignments</li>
          <li class="flex items-start"><i class="fas fa-check text-green-500 mr-3 mt-1"></i>Exportable reports (Excel) & printable summaries</li>
          <li class="flex items-start"><i class="fas fa-check text-green-500 mr-3 mt-1"></i>Access control (teacher/admin login) and configurable roles</li>
        </ul>
      </div>

      <div class="p-6 bg-white rounded-lg border shadow-sm">
        <h3 class="text-xl font-bold text-gray-900">Who should use it</h3>
        <p class="mt-2 text-sm text-gray-700">Students use the scanning interface to record presence. Faculty use the teacher dashboard to monitor attendance, manage subjects, and scan students. Administrators use the MIS console to manage users, generate reports, and configure system settings.</p>
      </div>
    </div>

    <div class="mt-8 space-y-4">
      <details class="group bg-white border rounded-lg p-4">
        <summary class="cursor-pointer font-semibold">How to scan (students)</summary>
        <div class="mt-3 text-sm text-gray-700">
          <ol class="list-decimal list-inside space-y-2">
            <li>Open the Scan page and select the appropriate lab session.</li>
            <li>Present the student ID with Unique Barcode and use the provided scanning device to record the event.</li>
            <li>Verify the student's info on the confirmation prompt if shown.</li>
          </ol>
        </div>
      </details>

      <details class="group bg-white border rounded-lg p-4">
        <summary class="cursor-pointer font-semibold">Managing Faculty & subjects</summary>
        <div class="mt-3 text-sm text-gray-700">
          <ol class="list-decimal list-inside space-y-2">
            <li>Go to Manage Faculty to add or edit faculty profiles.</li>
            <li>Assign subjects under Manage Subjects; assignments appear on the teacher dashboard.</li>
            <li>Unassign subjects first before deleting teachers to avoid orphaned records.</li>
          </ol>
        </div>
      </details>

      <details class="group bg-white border rounded-lg p-4">
        <summary class="cursor-pointer font-semibold">Generating and exporting reports</summary>
        <div class="mt-3 text-sm text-gray-700">
          <ol class="list-decimal list-inside space-y-2">
            <li>Use the Reports pages to choose date ranges, subjects, and filters.</li>
            <li>Click Export to download an Excel file with the selected records.</li>
            <li>Use Print Report for printer-friendly summaries.</li>
          </ol>
        </div>
      </details>
    </div>
  </section>

  <!-- Footer -->
  <footer class="mt-auto py-6 text-center">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <p class="text-gray-600">&copy; 2025 Santa Rita College of Pampanga — College of Computer Studies</p>
      <p class="footer-credit mt-2">Developed by Christopher Panoy</p>
    </div>
  </footer>

  <script>
    // Smooth scroll behavior for About link and focus management
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
          var target = document.querySelector(this.getAttribute('href'));
          if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.setAttribute('tabindex', '-1');
            target.focus({ preventScroll: true });
          }
        });
      });
    });
  </script>

  <script>
    // College modal wiring
    (function () {
      const logo = document.querySelector('nav img');
      const modal = document.getElementById('college-modal');
      const close = document.getElementById('college-modal-close');
      const close2 = document.getElementById('college-modal-close-2');

      function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
      }
      function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
      }

      if (logo) {
        logo.style.cursor = 'pointer';
        logo.addEventListener('click', openModal);
      }
      if (close) close.addEventListener('click', closeModal);
      if (close2) close2.addEventListener('click', closeModal);

      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
      });
    })();
  </script>

</body>
</html>
