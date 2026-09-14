<?php require_once __DIR__ . '/../includes/header.php'; ?>

<section>
    <h1>Contacto</h1>
    <form action="<?= BASE_URL ?>/process/contact_process.php" method="POST" class="form">
        <label for="name">Nombre</label>
        <input type="text" id="name" name="name" required>

        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" required>

        <label for="message">Mensaje</label>
        <textarea id="message" name="message" rows="5" required></textarea>

        <button type="submit">Enviar</button>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
