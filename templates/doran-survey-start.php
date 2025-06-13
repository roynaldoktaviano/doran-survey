<?php

if (!defined('ABSPATH')) exit;

global $doran_survey_data;

// Ambil data dari variabel global untuk kemudahan
$ref = $doran_survey_data['ref'] ?? '';
$customer = $doran_survey_data['customer'] ?? [];

// get_header();
?>
<div id="doran-survey-container">
    <img class="start-img" src="https://dorangadget.com/wp-content/uploads/2025/06/survey.png" alt="Ilustrasi Survei"
        style="">
    <p class="start-greeting">Halo kak <?php echo esc_html($customer['nama']); ?>, </p>
    <h1>Terima kasih telah berbelanja! Kami ingin dengar Feedback Anda.</h1>
    <p class="start-description">Pendapat Anda sangat berarti bagi kami untuk terus meningkatkan pelayanan dan kualitas.
        Luangkan sedikit waktu untuk berbagi pengalaman Anda, ya!</p>

    <form id="doran-survey-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">

        <input type="hidden" name="action" value="submit_doran_survey_step">
        <input type="hidden" name="survey_step" value="start">
        <input type="hidden" name="survey_ref" value="<?php echo esc_attr($ref); ?>">
        <?php wp_nonce_field('doran_survey_step_action', 'doran_survey_nonce'); ?>

        <input type="hidden" name="customer_nama" value="<?php echo esc_attr($customer['nama'] ?? ''); ?>">
        <input type="hidden" name="customer_telp" value="<?php echo esc_attr($customer['telp'] ?? ''); ?>">
        <input type="hidden" name="customer_email" value="<?php echo esc_attr($customer['email'] ?? ''); ?>">
        <input type="hidden" name="customer_domisili" value="<?php echo esc_attr($customer['domisili'] ?? ''); ?>">
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