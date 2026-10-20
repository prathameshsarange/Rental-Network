    </div>
</main>

<footer class="site-footer mt-auto">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="navbar-brand text-white mb-3"><span class="brand-mark">R</span><span>Rental Network</span></div>
                <p class="footer-tagline mb-0">Rent smart. Live better. Make more of what already exists.</p>
            </div>
            <div class="col-6 col-lg-2"><h2 class="footer-heading">Explore</h2><a href="<?php echo e(base_url('items/browse_items.php')); ?>">Browse items</a><a href="<?php echo e(base_url('categories/categories.php')); ?>">Categories</a><a href="<?php echo e(base_url('items/locations.php')); ?>">Locations</a></div>
            <div class="col-6 col-lg-2"><h2 class="footer-heading">Company</h2><a href="<?php echo e(base_url('index.php')); ?>">About Rental Network</a><a href="<?php echo e(base_url('register.php')); ?>">List an item</a><a href="<?php echo e(base_url('login.php')); ?>">Sign in</a></div>
            <div class="col-12 col-lg-3"><h2 class="footer-heading">Stay in the loop</h2><p class="footer-copy">New listings and local rental ideas, occasionally.</p><form class="footer-subscribe d-flex gap-2"><input class="form-control" type="email" placeholder="Your email" aria-label="Your email"><button class="btn btn-primary" type="button">Join</button></form></div>
        </div>
        <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between gap-2 mt-5 pt-4"><span>&copy; <?php echo date('Y'); ?> Rental Network</span><span>Peer-to-peer rentals, made local.</span></div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($include_leaflet)) : ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="<?php echo e(base_url('assets/js/location-picker.js')); ?>"></script>
<?php endif; ?>
<script src="<?php echo e(base_url('assets/js/script.js')); ?>"></script>
</body>
</html>
