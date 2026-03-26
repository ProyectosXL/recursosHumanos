<?php
// templates/footer.php
?>
</main> <!-- Cierra el .container -->

<footer class="text-center text-muted mt-auto py-3"></footer>

<!-- jQuery (requerido por Select2 y DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>

<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Tu script personalizado general -->
<script src="<?= BASE_URL ?>js/main.js"></script>

<!-- Placeholder para scripts específicos de cada página (como el de DataTables) -->
<?= $pageScripts ?? '' ?>

</body>
</html>