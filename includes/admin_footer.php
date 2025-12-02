        <footer class="admin-footer py-3">
            <div class="text-muted-soft small">&copy; <?php echo date('Y'); ?> Hotel Booking System Admin</div>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <script>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });

        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function(alert) { bootstrap.Alert.getOrCreateInstance(alert).close(); });
        }, 5000);

        var sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                var sidebar = document.querySelector('.admin-sidebar');
                if (sidebar) { sidebar.classList.toggle('show'); }
            });
        }
    </script>
</body>
</html>
