<?php /** Delivery panel footer + scripts. */ ?>
        </div><!-- /.admin-content -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-shell -->
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<script>
    document.getElementById('admin-burger')?.addEventListener('click', function () {
        document.getElementById('admin-sidebar')?.classList.toggle('open');
    });
</script>
</body>
</html>
