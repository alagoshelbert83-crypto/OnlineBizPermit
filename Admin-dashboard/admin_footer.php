  </div> <!-- .wrapper -->

  <script>
    // --- Generic UI Logic ---

    // Dropdown Logic
    const userProfile = document.querySelector('.user-profile');
    if (userProfile) {
        userProfile.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent window click from closing it immediately
            document.querySelector('.dropdown-menu').classList.toggle('show');
        });
    }
    // Close dropdown on click outside
    window.addEventListener('click', (e) => {
        const dropdown = document.querySelector('.dropdown-menu');
        if (dropdown && dropdown.classList.contains('show') && !userProfile.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });

    // Hamburger Menu for mobile (if exists, use the mobile-menu-toggle from sidebar instead)
    const hamburger = document.getElementById('hamburger');
    const mobileToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    // Use mobile-menu-toggle if available (from sidebar), otherwise use hamburger
    if (mobileToggle && sidebar) {
        // Already handled in sidebar script
    } else if (hamburger && sidebar) {
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('active-mobile');
            sidebar.classList.toggle('active');
            const overlay = document.getElementById('mobile-menu-overlay');
            if (overlay) {
                overlay.style.display = sidebar.classList.contains('active') || sidebar.classList.contains('active-mobile') ? 'block' : 'none';
            }
        });
    }
  </script>
</body>
</html>