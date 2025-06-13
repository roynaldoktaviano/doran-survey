<?php
global $doran_survey_data;
// get_header(); // Jika ingin menggunakan header tema Anda
?>
<div id="doran-survey-container">
    <img class="start-img" src="https://dorangadget.com/wp-content/uploads/2025/06/survey.png" alt="" style="">
    <p class="start-greeting">Halo kak <?php echo esc_html($doran_survey_data['customer']['nama']); ?>, </p>
    <h1>Terima kasih telah
        berbelanja! Kami ingin dengar Feedback Anda.</h1>
    <p class="start-description">Pendapat Anda sangat berarti bagi kami untuk terus meningkatkan pelayanan dan kualitas.
        Luangkan sedikit waktu untuk berbagi pengalaman Anda, ya!</p>

    <form id="doran-survey-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="submit_doran_survey_step">
        <input type="hidden" name="survey_step" value="start">
        <input type="hidden" name="survey_ref" value="<?php echo esc_attr($doran_survey_data['ref']); ?>">
        <?php wp_nonce_field('doran_survey_step_action', 'doran_survey_nonce'); ?>

        <!--<div class="konfirmasi-container">-->
        <!--    <h3>Konfirmasi Data Diri Anda </h3>-->
        <!--    <div class="data-diri-container">-->
        <!--        <div class="data-diri">-->
        <!--            <p class="data-nama">Nama</p>-->
        <!--            <p class="nama-customer"><?php echo esc_html($doran_survey_data['customer']['nama']); ?></p>-->
        <!--        </div>-->
        <!--        <div class="data-diri">-->
        <!--            <p class="data-nama">No. Telepon</p>-->
        <!--            <p class="nama-customer"><?php echo esc_html($doran_survey_data['customer']['telp']); ?></p>-->
        <!--        </div>-->
        <!--        <div class="data-diri">-->
        <!--            <p class="data-nama">Email</p>-->
        <!--            <p class="nama-customer"><?php echo esc_html($doran_survey_data['customer']['email']); ?></p>-->
        <!--        </div>-->
        <!--        <div class="data-diri">-->
        <!--            <p class="data-nama">Alamat</p>-->
        <!--            <p class="nama-customer"><?php echo esc_html($doran_survey_data['alamat']['email']); ?></p>-->
        <!--        </div>-->
        <!--    </div>-->

        <!--    <hr>-->

        <!--    <div class="form-group">-->
        <!--        <label for="media-selector">Di mana Anda melakukan pembelian?</label>-->
        <!--        <select id="media-selector" name="survey_media" required>-->
        <!--            <option value="">-- Pilih Lokasi Pembelian --</option>-->
        <!--            <option value="1">Store Offline (Mall / Toko)</option>-->
        <!--            <option value="2">Website / Aplikasi Doran Gadget</option>-->
        <!--            <option value="3">Marketplace (Tokopedia, Shopee, dll.)</option>-->
        <!--        </select>-->
        <!--    </div>-->
        <!--</div>-->

        <div class="form-group">
            <select id="media-selector" name="survey_media" required>
                <option value="">-- Pilih Media Pembelian --</option>
                <option value="1">Store Offline (Mall / Toko)</option>
                <option value="2">Website / Aplikasi Doran Gadget</option>
                <option value="3">Marketplace (Tokopedia, Shopee, dll.)</option>
            </select>
        </div>

        <button type="submit" class="button-start-survey">Mulai Survei</button>
    </form>
</div>
<?php
// get_footer();
?>