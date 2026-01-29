<?php
include '../includes/db.php';

// Ensure session is started before checking auth so header redirects work
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

// Removed admin header include to make this page standalone and accessible directly via URL


// Optional role check
$allowed_roles = ['student', 'teacher', 'admin'];
if (isset($_SESSION['user']['role']) && !in_array($_SESSION['user']['role'], $allowed_roles)) {
    echo "Access denied.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard — Live Scan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root{
            --bg: #f6f8fb;
            --card: #ffffff;
            --muted: #6b7280;
            --accent: #0ea5a0;
            --green: #059669;
            --red: #ef4444;
            --shadow: 0 12px 36px rgba(2,6,23,0.08);
        }
        body { 
            font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; 
            background: var(--bg);
            margin:0; padding:0; 
            color:#0b1220; 
            -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
        }
    .wrap { width:100%; max-width:none; margin:0; padding:0 1rem; }

        .header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:1.25rem; }
        .title { font-size:1.75rem; font-weight:800; letter-spacing:-0.3px; }
        .subtitle { color:var(--muted); font-size:0.95rem; margin-top:4px; }

        /* Card */
        .card { 
            background: var(--card); 
            border-radius: 28px; 
            padding: 4rem; 
            box-shadow: var(--shadow); 
            display: flex; 
            gap: 3rem; 
            align-items: center; 
            transition: transform .18s ease, box-shadow .18s ease; 
            overflow: hidden;  
            font-size: 1.12rem; 
            width: calc(100vw - 5rem); /* nearly full width */
            height: calc(80vh); /* large vertical presence */
            margin: 0 auto;
            position: relative; /* for in-card brand logo */
            align-items: center;
            justify-content: flex-start;
        }
        .avatar {
            width:460px; height:460px; 
            border-radius:20px; 
            object-fit:cover; 
            background:#eef2f6; 
            border:2px solid #e6eef7;
            flex-shrink:0;
            transition:transform .25s ease;
            box-shadow: 0 18px 48px rgba(2,6,23,0.06);
        }
        .info { flex:1; min-width:0; }
    .name { font-size:4.4rem; font-weight:900; margin-bottom:6px; letter-spacing:-0.6px; line-height:1; }
        .meta { color:var(--muted); font-size:0.98rem; margin-bottom:12px; }
        .meta .id-number { display:inline-block; font-weight:900; color:#0b1220; background:rgba(14,165,160,0.06); padding:6px 10px; border-radius:8px; margin-left:8px; }
        .meta .year-badge { display:inline-block; font-weight:800; color:var(--accent); background:rgba(14,165,160,0.08); padding:6px 10px; border-radius:999px; margin-left:10px; font-size:0.9rem; }
        .badges { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
        .badge { padding:8px 14px; border-radius:999px; font-weight:700; font-size:0.95rem; background:transparent; border:1px solid rgba(15,23,42,0.06); }
        .status-present { color:var(--green); border-color: rgba(5,150,105,0.14); background: rgba(5,150,105,0.06); }
        .status-signedout { color:var(--red); border-color: rgba(239,68,68,0.12); background: rgba(239,68,68,0.04); }

    .right { text-align:right; min-width:420px; }
    .scan-time { color:var(--muted); font-size:1.1rem; }
    .subject { font-size:3.0rem; font-weight:900; color:#0f172a; margin-top:6px; }

    /* small brand/logo shown inside the card */
    .brand-logo { position: absolute; top:22px; right:22px; width:140px; height:auto; border-radius:14px; box-shadow:0 14px 36px rgba(2,6,23,0.10); background:rgba(255,255,255,0.94); padding:10px; z-index:3; }

        .placeholder {
            display:flex; flex-direction:column; gap:18px; align-items:center; justify-content:center; padding:1.5rem 1rem;
            border-radius:16px; background:linear-gradient(180deg,#ffffff 0%, #fbfdff 100%); box-shadow: 0 10px 30px rgba(2,6,23,0.05);
            width:100%; max-width:1600px; margin:0 auto; min-height:60vh;
        }
        .placeholder .icon { font-size:4.5rem; color:#94a3b8; }
        .placeholder .text { color:var(--muted); font-size:1.15rem; }

    .big-logo { max-width:90vmin; width:80%; height:auto; opacity:0.98; border-radius:12px; box-shadow:0 22px 60px rgba(2,6,23,0.08); }

        /* Pulse animation */
        @keyframes pulse {
            0% { box-shadow: 0 12px 36px rgba(2,6,23,0.08); transform: translateY(0); }
            50% { box-shadow: 0 26px 56px rgba(14,165,160,0.10); transform: translateY(-6px); }
            100% { box-shadow: 0 12px 36px rgba(2,6,23,0.08); transform: translateY(0); }
        }
        .pulse { animation: pulse 900ms ease-in-out; }

        @media (max-width:1300px){
            .card { flex-direction:column; align-items:center; text-align:center; padding:2.2rem; height:auto; }
            .avatar { width:300px; height:300px; border-radius:12px; }
            .right { text-align:center; width:100%; }
            .big-logo { width:86%; max-width:720px; }
            .brand-logo{ top:14px; right:14px; width:110px; }
        }
        @media (max-width:760px){
            .card { flex-direction:column; align-items:center; text-align:center; padding:1.4rem; height:auto; }
            .avatar { width:160px; height:160px; border-radius:12px; }
            .name{ font-size:1.9rem; }
            .subject{ font-size:1.3rem; }
            .big-logo { width:84%; max-width:520px; }
            .brand-logo{ display:none; }
        }
        @media (max-width:560px){
            .name{ font-size:1.6rem; }
            .avatar{ width:120px; height:120px; }
            .subject{ font-size:1.1rem; }
            .brand-logo{ display:none; }
            .card{ padding:1rem; }
        }

        /* Auto fullscreen wrapper */
        .live-scan-wrapper.fullscreen {
          position: fixed !important;
          top: 0;
          left: 0;
          width: 100vw;
          height: 100vh;
          z-index: 99999;
          background: linear-gradient(180deg, #f7fbfd 0%, #ffffff 60%);
                    padding: 2rem;
          box-sizing: border-box;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center; /* center card vertically */
        }
        /* When idle: center a subtle, very large logo behind content for a professional 'full-screen logo' look */
        .live-scan-wrapper.fullscreen::before{
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('../assets/img/logo.png');
            background-repeat: no-repeat;
            background-position: center 30%;
                        background-size: 64vmin;
                        opacity: 0.08;
            pointer-events: none;
            z-index: 0;
        }
        /* make sure main-area content sits above the decorative logo */
                #main-area{ position: relative; z-index: 2; width:100%; height:100%; display:flex; align-items:center; justify-content:center; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div>
            <div class="title">Student Dashboard — Live Scan</div>
            <div class="subtitle"></div>
        </div>
        <div>
            <small class="muted">Last update: <span id="last-update">—</span></small>
        </div>
    </div>

    <!-- Auto fullscreen wrapper -->
    <div id="liveScanWrapper" class="live-scan-wrapper fullscreen">
        <div id="main-area">
            <div id="placeholder" class="placeholder">
                <img src="../assets/img/logo.png" alt="Logo" class="big-logo">
                <div class="text">Walang bagong scan — maghintay habang nagre-record ang mga guro.</div>
                <div class="text" style="font-size:0.95rem; color:#9ca3b8;">Awtomatikong magre-refresh tuwing ilang segundo.</div>
            </div>
        </div>
    </div>
</div>

<script>
const FETCH_URL = '../ajax/student_attendance_feed.php';
const POLL_MS = 3000;
let lastHash = null;
let lastId = null;

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function parseSQLDateToDate(sql) {
    if (!sql) return null;
    const m = sql.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/);
    if (m) return new Date(m[1], m[2]-1, m[3], m[4], m[5], m[6]);
    const d = new Date(sql); return isNaN(d.getTime()) ? null : d;
}
function formatTo12Hour(sqlDatetime) {
    const d = parseSQLDateToDate(sqlDatetime);
    if (!d) return sqlDatetime || '-';
    return d.toLocaleTimeString(undefined, {hour:'numeric', minute:'2-digit', second:'2-digit', hour12:true});
}
function nowTo12Hour() {
    const d = new Date();
    return d.toLocaleTimeString(undefined, {hour:'numeric', minute:'2-digit', second:'2-digit', hour12:true});
}
function buildCard(item,isNew=false){
    const photoSrc = (item.photo && item.photo.length) ? item.photo : '../assets/img/logo.png';
    const photo = escapeHtml(photoSrc);
    const name=escapeHtml(item.name||'-');
    const studentId=escapeHtml(item.student_id||'-');
    const course=escapeHtml(item.course||'');
    const year=escapeHtml(item.year_level||'');
    const subject=escapeHtml(item.subject_name||'-');
    const statusText=escapeHtml(item.status||'-');
    const scanTime=formatTo12Hour(item.scan_time);
    const statusClass=statusText.toLowerCase().includes('present')?'status-present':'status-signedout';
        const courseLine=[course].filter(Boolean).join(' • ');
        return `
            <div id="profile-card" class="card ${isNew?'pulse':''}">
                <img src="../assets/img/logo.png" alt="Brand" class="brand-logo">
                <img class="avatar" src="${photo}" alt="Student photo">
        <div class="info">
          <div class="name">${name}</div>
                    <div class="meta">ID: <span class="id-number">${studentId}</span>${courseLine? ' • '+courseLine:''}${year? ' <span class="year-badge">'+year+'</span>':''}</div>
          <div class="badges">
            <div class="badge ${statusClass}">${statusText}</div>
            <div class="badge">Subject: ${subject}</div>
          </div>
        </div>
        <div class="right">
          <div class="scan-time small">Scan Time</div>
          <div class="subject">${scanTime}</div>
        </div>
      </div>`;
}
function showPlaceholder(){
        document.getElementById('main-area').innerHTML=`
            <div id="placeholder" class="placeholder">
                <img src="../assets/img/logo.png" alt="Logo" class="big-logo">
                <div class="text">Walang bagong scan — maghintay habang nagre-record ang mga guro.</div>
                <div class="text" style="font-size:0.95rem; color:#9ca3b8;">Awtomatikong magre-refresh tuwing ilang segundo.</div>
            </div>`;
        const last = nowTo12Hour();
        document.getElementById('last-update').innerText = last;
}
function showProfile(item,isNew=false){
    if(!item){showPlaceholder();return;}
    document.getElementById('main-area').innerHTML=buildCard(item,isNew);
    const card=document.getElementById('profile-card');
    if(card)setTimeout(()=>card.classList.remove('pulse'),1000);
    document.getElementById('last-update').innerText=nowTo12Hour();
}
async function loadScans(){
    try{
        const res=await fetch(FETCH_URL,{cache:'no-store'});
        const text=await res.text();
        if(!res.ok)return;
        let data; try{data=JSON.parse(text);}catch{return;}
        if(!Array.isArray(data)||data.length===0){showPlaceholder();lastHash=JSON.stringify(data||[]);lastId=null;return;}
        const latest=data[0]; const hash=JSON.stringify(latest);
        const newScan=latest.id&&latest.id!==lastId;
        if(hash===lastHash&&!newScan){document.getElementById('last-update').innerText=nowTo12Hour();return;}
        lastHash=hash; lastId=latest.id||lastId; showProfile(latest,newScan);
    }catch(err){console.error('loadScans error',err);}
}
loadScans(); setInterval(loadScans, POLL_MS);
</script>
</body>
</html>



