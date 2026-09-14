<?php require_once __DIR__ . '/../includes/header.php'; ?>

<section class="hero-landing">
    <div class="row align-items-center g-5">
        <div class="col-lg-6">
            <h1 class="display-5 fw-bold">Controla las ventas y finanzas de tu negocio en un solo lugar</h1>
            <p class="lead text-secondary">Registra ventas, controla tu inventario y revisa tus reportes desde cualquier dispositivo, sin complicaciones.</p>
            <div class="d-flex flex-wrap gap-3 mt-4">
                <a href="<?= BASE_URL ?>/contact.php" class="btn btn-primary btn-lg rounded-pill px-4">Comenzar gratis</a>
                <a href="<?= BASE_URL ?>/about.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">Conocer más</a>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="hero-mockup">
                <i class="bi bi-graph-up-arrow" style="font-size: 4rem;"></i>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="text-center mb-5">
        <h2 class="fw-bold">Todo lo que tu negocio necesita</h2>
        <p class="text-secondary">Herramientas simples para vender más y perder menos tiempo.</p>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="p-4 h-100 border rounded-4">
                <div class="feature-icon mb-3"><i class="bi bi-cart-check"></i></div>
                <h3 class="h5 fw-semibold">Control de ventas</h3>
                <p class="text-secondary mb-0">Registra cada venta en segundos y mantén tu caja siempre al día.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 h-100 border rounded-4">
                <div class="feature-icon mb-3"><i class="bi bi-box-seam"></i></div>
                <h3 class="h5 fw-semibold">Inventario al día</h3>
                <p class="text-secondary mb-0">Sabe qué productos tienes disponibles y cuáles necesitas reponer.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 h-100 border rounded-4">
                <div class="feature-icon mb-3"><i class="bi bi-bar-chart-line"></i></div>
                <h3 class="h5 fw-semibold">Reportes claros</h3>
                <p class="text-secondary mb-0">Visualiza cómo va tu negocio con reportes fáciles de entender.</p>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="cta-banner text-center">
        <h2 class="fw-bold">Únete a los negocios que ya organizan sus ventas con <?= APP_NAME ?></h2>
        <p class="mb-4">Crea tu cuenta gratis, no necesitas tarjeta de crédito.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="btn btn-light btn-lg rounded-pill px-4 fw-semibold">Comenzar ahora</a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
