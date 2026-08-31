<section class="section">
    <div class="container" style="max-width:720px;margin-inline:auto;">
        <p class="kicker">Contact</p>
        <h1>Take back the edge</h1>
        <p class="lede">Send us your details, and let's talk about The Playbook. Informational use only — not betting advice.</p>
        <?= component('alert') ?>
        <form method="post" class="form-card">
            <?= csrf_field() ?>
            <label>Name</label>
            <input name="name" required value="<?= e((string) old('name')) ?>">
            <label>Email</label>
            <input type="email" name="email" required value="<?= e((string) old('email')) ?>">
            <label>Subject</label>
            <input name="subject" required value="<?= e((string) old('subject')) ?>">
            <label>Message</label>
            <textarea name="message" required><?= e((string) old('message')) ?></textarea>
            <button class="btn btn-primary" type="submit">Send</button>
        </form>
    </div>
</section>
