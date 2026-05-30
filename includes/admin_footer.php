<?php /** Admin panel footer + scripts. */ ?>
        </div><!-- /.admin-content -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-shell -->
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<script>
    // Sidebar toggle for small screens
    document.getElementById('admin-burger')?.addEventListener('click', function () {
        document.getElementById('admin-sidebar')?.classList.toggle('open');
    });
</script>
<?php if (!empty($page_scripts)) foreach ($page_scripts as $s): ?>
<script src="<?= e(url('assets/js/' . $s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
