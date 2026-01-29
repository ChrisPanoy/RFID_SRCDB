<?php
// Footer include - closes layout wrappers opened by header.php and renders site footer.
// Safe, responsive footer: offset on wide viewports to sit under a fixed sidebar (approx 18rem).
?>

<!-- Close main-content and outer layout wrapper opened in header.php -->
</div>
</div>

<!-- Ensure footer sits below any fixed sidebar or floated elements -->
<div style="clear: both; width: 100%;"></div>

<style>
    /* Footer always full-width and below main content */
    .site-footer-inner { box-sizing: border-box; clear: both; width: 100%; position: relative; left: 0; }
    /* Small visual tweak to ensure footer spacing on short pages */
    body { --footer-bottom-gap: 24px; }
</style>

<hr>
<footer class="site-footer-inner" style="background: linear-gradient(90deg, #e0eafc 0%, #cfdef3 100%); border-top: 1.5px solid #e0eafc; padding: 24px 0 32px 0; text-align: center; font-family: 'Segoe UI', Arial, sans-serif; font-size: 1.05rem; color: #26324d; margin-top: 40px; margin-bottom: 24px;">
    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
        <span style="font-size: 1.8rem; color: #6366f1;"><i class="fa-solid fa-school"></i></span>
        <p style="margin: 0; font-weight: 600; letter-spacing: 0.4px;">&copy; <?php echo date('Y'); ?> Santa Rita College of Pampanga</p>
        <small style="color: #6b7280; font-size: 0.95rem;">Automated Ingress and Egress</small>
    </div>
</footer>

</body>
</html>