<?php
/**
 * Plugin Name:       Doran Survey (Multi-Page)
 * Description:       Survei multi-halaman berdasarkan data transaksi dari API.
 * Version:           2.1.0
 * Author:            PT Doran Sukses Indonesia (Modified by AI)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Mulai Sesi PHP jika belum ada
if ( ! session_id() ) {
    session_start();
}

// 2. Definisikan CPT untuk menyimpan hasil survei
function doran_survey_register_submission_cpt() {
    $args = array(
        'labels'         => array('name' => 'Hasil Survei', 'singular_name' => 'Hasil Survei'),
        'public'         => false, 'show_ui'        => true, 'show_in_menu'   => true,
        'menu_position'  => 20, 'menu_icon'      => 'dashicons-feedback',
        'supports'       => array('title'), 'capability_type' => 'post',
        'capabilities'   => array('create_posts' => false), // Mencegah pembuatan manual
        'map_meta_cap'   => true,
    );
    register_post_type( 'survey_submission', $args );
}
add_action( 'init', 'doran_survey_register_submission_cpt' );

// 3. Buat Aturan URL (Rewrite Rules)
function doran_survey_rewrite_rules() {
    add_rewrite_rule('^survei/([^/]+)/?$', 'index.php?survey_ref=$matches[1]&survey_step=start', 'top');
    add_rewrite_rule('^survei/([^/]+)/pertanyaan/([0-9]+)/?$', 'index.php?survey_ref=$matches[1]&survey_step=question&question_number=$matches[2]', 'top');
    add_rewrite_rule('^survei/([^/]+)/terima-kasih/?$', 'index.php?survey_ref=$matches[1]&survey_step=thank_you', 'top');
}
add_action( 'init', 'doran_survey_rewrite_rules' );

// Daftarkan variabel query kustom
function doran_survey_register_query_vars( $vars ) {
    $vars[] = 'survey_ref';
    $vars[] = 'survey_step';
    $vars[] = 'question_number';
    return $vars;
}
add_filter( 'query_vars', 'doran_survey_register_query_vars' );

// 4. Struktur Pertanyaan (Lengkapi sesuai kebutuhan Anda)
function get_doran_survey_questions() {
    return [
        'offline' => [
            1 => ['soal' => 'Apakah kualitas produk yang Anda beli sesuai dengan deskripsi yang diberikan?', 'jenis' => 1, 'opsi' => [1=>'Sangat Tidak Sesuai', 2=>'Tidak Sesuai', 3=>'Cukup Sesuai', 4=>'Sesuai', 5=>'Sangat Sesuai']],
            2 => ['soal' => 'Bagaimana penilaian Anda terhadap harga produk di Doran Gadget?', 'jenis' => 1, 'opsi' => [1=>'Sangat Buruk', 2=>'Buruk', 3=>'Cukup', 4=>'Baik', 5=>'Sangat Baik']],
            // ... Tambahkan pertanyaan offline lainnya ...
            8 => ['soal' => 'Apa Masukan Anda untuk Doran Gadget untuk lebih baik ke depannya?', 'jenis' => 2]
        ],
        'online' => [
            1 => ['soal' => 'Seberapa cepat website/aplikasi Doran Gadget dalam memuat halaman?', 'jenis' => 1, 'opsi' => [1=>'Sangat Lambat', 2=>'Lambat', 3=>'Cukup', 4=>'Cepat', 5=>'Sangat Cepat']],
            // ... Tambahkan pertanyaan online lainnya ...
        ],
        'marketplace' => [
            1 => ['soal' => 'Seberapa mudah Anda menemukan produk di Marketplace Doran Gadget?', 'jenis' => 1, 'opsi' => [1=>'Sangat Sulit', 2=>'Sulit', 3=>'Cukup', 4=>'Mudah', 5=>'Sangat Mudah']],
            // ... Tambahkan pertanyaan marketplace lainnya ...
        ]
    ];
}

// 5. Otak Pengarah Halaman (Template Redirect)
function doran_survey_template_redirect() {
    $survey_ref = get_query_var('survey_ref');
    $survey_step = get_query_var('survey_step');

    if (empty($survey_ref) || empty($survey_step)) return;

    global $doran_survey_data;
    
    // Ambil data pelanggan dari API
    $ref = sanitize_text_field($survey_ref);
    $base = md5(date('Y-m-d') . '_ord_' . $ref); // Logika 'base' Anda
    $api_url = sprintf('https://kasir.doran.id/api/product/detail_order?base=%s&ref=%s', $base, $ref);
    $response = wp_remote_get($api_url);
    
    $customer_data = [];
    $error_message = '';
    
    if (is_wp_error($response)) {
        $error_message = 'Gagal terhubung ke server. Silakan coba lagi nanti.';
    } else {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!$data || !isset($data['status']) || $data['status'] != 'true' || empty($data['data'])) {
            $error_message = 'Data transaksi tidak ditemukan. Pastikan link survei Anda benar.';
        } else {
            $detail = $data['data'];
            $customer_data['nama'] = esc_attr($detail['xx_nama_pelanggan'] ?? '');
            $customer_data['telp'] = esc_attr($detail['xx_telp_pelanggan'] ?? '');
            $customer_data['email'] = esc_attr($detail['xx_email'] ?? '');
            $customer_data['domisili'] = esc_attr($detail['xx_alamat_pelanggan'] ?? '');
        }
    }
    
    if (!empty($error_message)) {
        wp_die($error_message, 'Error Survei', ['response' => 404]);
    }

    $doran_survey_data = [
        'ref' => $ref,
        'customer' => $customer_data,
    ];

    $template_name = '';
    if ($survey_step === 'start') {
        unset($_SESSION['doran_survey_answers']);
        $_SESSION['doran_customer_data'] = $customer_data;
        $template_name = 'survey-start.php';
    } elseif ($survey_step === 'question') {
        $doran_survey_data['question_number'] = intval(get_query_var('question_number'));
        $template_name = 'survey-question.php';
    } elseif ($survey_step === 'thank_you') {
        $template_name = 'survey-thank-you.php';
    }

    if ($template_name) {
        $template_path = plugin_dir_path(__FILE__) . 'templates/' . $template_name;
        if (file_exists($template_path)) {
            status_header(200);
            include($template_path);
            exit;
        } else {
            wp_die('Template survei tidak ditemukan: ' . esc_html($template_name));
        }
    }
}
add_action('template_redirect', 'doran_survey_template_redirect');

// 6. Fungsi untuk Menangani Submit per Halaman
function doran_survey_handle_step_submission() {
    if (!isset($_POST['doran_survey_nonce']) || !wp_verify_nonce($_POST['doran_survey_nonce'], 'doran_survey_step_action')) {
       wp_die('Verifikasi keamanan gagal.');
    }

    $survey_ref = sanitize_text_field($_POST['survey_ref']);
    $current_step = sanitize_text_field($_POST['survey_step']);
    
    if (!isset($_SESSION['doran_survey_answers'])) $_SESSION['doran_survey_answers'] = [];
    if (!isset($_SESSION['doran_customer_data'])) $_SESSION['doran_customer_data'] = [];

    if ($current_step === 'start') {
        $_SESSION['doran_customer_data']['media'] = intval($_POST['survey_media']);
        // Data customer lain sudah ada di sesi dari halaman pertama dimuat
        wp_redirect(home_url("/survei/{$survey_ref}/pertanyaan/1/"));
        exit;
    } 
    elseif ($current_step === 'question') {
        $question_number = intval($_POST['question_number']);
        if (isset($_POST['survey_answer'])) {
            $_SESSION['doran_survey_answers'][$question_number] = wp_kses_post($_POST['survey_answer']);
        }
        
        $media = $_SESSION['doran_customer_data']['media'] ?? 0;
        $media_key = $media == 1 ? 'offline' : ($media == 2 ? 'online' : 'marketplace');
        $all_questions = get_doran_survey_questions();
        $total_questions = count($all_questions[$media_key] ?? []);
        
        $next_question_number = $question_number + 1;

        if ($next_question_number <= $total_questions) {
            wp_redirect(home_url("/survei/{$survey_ref}/pertanyaan/{$next_question_number}/"));
            exit;
        } else {
            doran_survey_process_final_submission($survey_ref);
            wp_redirect(home_url("/survei/{$survey_ref}/terima-kasih/"));
            exit;
        }
    }
}
add_action('admin_post_nopriv_submit_doran_survey_step', 'doran_survey_handle_step_submission');
add_action('admin_post_submit_doran_survey_step', 'doran_survey_handle_step_submission');

// 7. Fungsi untuk Memproses Semua Data di Akhir Survei
function doran_survey_process_final_submission($ref) {
    if (empty($_SESSION['doran_customer_data']) || empty($_SESSION['doran_survey_answers'])) { return; }

    global $wpdb;
    $customer_data = $_SESSION['doran_customer_data'];
    $answers_data = $_SESSION['doran_survey_answers'];
    $media = $customer_data['media'] ?? 0;
    $media_key = $media == 1 ? 'offline' : ($media == 2 ? 'online' : 'marketplace');
    $all_questions = get_doran_survey_questions()[$media_key] ?? [];

    $formatted_data_for_cpt = [];
    $formatted_data_for_api = [];

    foreach ($all_questions as $q_num => $q_details) {
        $answer = isset($answers_data[$q_num]) ? $answers_data[$q_num] : '';
        if (!empty($answer)) {
            $formatted_data_for_cpt[] = ['soal' => $q_details['soal'], 'jawab' => $answer];
            $formatted_data_for_api[] = ['urutan' => $q_num, 'jenis' => $q_details['jenis'], 'soal' => $q_details['soal'], 'jawab' => $answer, 'opsi' => $q_details['opsi'] ?? []];
        }
    }
    
    $post_title = 'Survei dari ' . ($customer_data['nama'] ?? 'Unknown') . ' (' . $ref . ') pada ' . current_time('d-m-Y H:i');
    $post_id = wp_insert_post(['post_title' => $post_title, 'post_status' => 'publish', 'post_type' => 'survey_submission']);

    if ($post_id && !is_wp_error($post_id)) {
        update_post_meta($post_id, 'customer_nama', $customer_data['nama'] ?? '');
        update_post_meta($post_id, 'customer_telp', $customer_data['telp'] ?? '');
        update_post_meta($post_id, 'survey_media', $media);
        update_post_meta($post_id, 'survey_answers', $formatted_data_for_cpt);
        update_post_meta($post_id, 'transaction_ref', $ref);
    }
    
    $body_payload = ['media' => $media, 'data' => $formatted_data_for_api];
    $base = md5(date('Y-m-d') . '_ord_' . $ref);
    $api_url = sprintf('https://kasir.doran.id/api/product/submit?base=%s&ref=%s', $base, $ref);
    wp_remote_post($api_url, [
        'method' => 'POST',
        'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
        'body' => json_encode($body_payload),
    ]);
    
    unset($_SESSION['doran_survey_answers'], $_SESSION['doran_customer_data']);
}

// 8. Sisakan semua fungsi admin yang relevan
// ... (set_custom_survey_submission_columns, custom_survey_submission_column_data, add_survey_details_modal_html, get_survey_details_ajax_handler, load_custom_admin_assets, dll. dari kode lama Anda bisa tetap di sini).

// 9. Fungsi aktivasi/deaktivasi yang benar
function doran_survey_plugin_activation() {
    doran_survey_register_submission_cpt();
    doran_survey_rewrite_rules(); // Gunakan fungsi rewrite rule yang benar
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'doran_survey_plugin_activation');

function doran_survey_plugin_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'doran_survey_plugin_deactivation');




// halaman
<?php
global $doran_survey_data;
// get_header(); // Jika ingin menggunakan header tema Anda
?>
<div id="doran-survey-container">
    <h1>Selamat Datang di Survei Doran!</h1>
    <p>Terima kasih telah melakukan pembelian. Mohon luangkan waktu sejenak untuk mengisi survei ini.</p>

    <form id="doran-survey-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="submit_doran_survey_step">
        <input type="hidden" name="survey_step" value="start">
        <input type="hidden" name="survey_ref" value="<?php echo esc_attr($doran_survey_data['ref']); ?>">
        <?php wp_nonce_field('doran_survey_step_action', 'doran_survey_nonce'); ?>

        <h3>Data Anda (Otomatis dari Transaksi)</h3>
        <p>Nama: <strong><?php echo esc_html($doran_survey_data['customer']['nama']); ?></strong></p>
        <p>No. Telepon: <strong><?php echo esc_html($doran_survey_data['customer']['telp']); ?></strong></p>
        <p>Email: <strong><?php echo esc_html($doran_survey_data['customer']['email']); ?></strong></p>
        <p>Domisili: <strong><?php echo esc_html($doran_survey_data['customer']['domisili']); ?></strong></p>
        <hr>

        <div class="form-group">
            <label for="media-selector">Di mana Anda melakukan pembelian?</label>
            <select id="media-selector" name="survey_media" required>
                <option value="">-- Pilih Lokasi Pembelian --</option>
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



// hstep
<?php
global $doran_survey_data;
// Ambil detail pertanyaan saat ini
$question_number = $doran_survey_data['question_number'];
$media = $_SESSION['doran_customer_data']['media'] ?? 0;
$media_key = $media == 1 ? 'offline' : ($media == 2 ? 'online' : 'marketplace');
$all_questions = get_doran_survey_questions()[$media_key] ?? [];
$current_question = $all_questions[$question_number] ?? null;
$total_questions = count($all_questions);

// get_header();
?>
<div id="doran-survey-container">
    <?php if ($current_question) : ?>
        <h1>Pertanyaan <?php echo esc_html($question_number); ?> dari <?php echo esc_html($total_questions); ?></h1>

        <form id="doran-survey-form-question" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="submit_doran_survey_step">
            <input type="hidden" name="survey_step" value="question">
            <input type="hidden" name="survey_ref" value="<?php echo esc_attr($doran_survey_data['ref']); ?>">
            <input type="hidden" name="question_number" value="<?php echo esc_attr($question_number); ?>">
            <?php wp_nonce_field('doran_survey_step_action', 'doran_survey_nonce'); ?>

            <div class="form-group">
                <label><?php echo esc_html($question_number . '. ' . $current_question['soal']); ?></label>

                <?php if ($current_question['jenis'] == 1) : // Tipe Pilihan Ganda / Rating ?>
                    <div class="rating-scale-group">
                        <?php foreach ($current_question['opsi'] as $value => $label) : ?>
                            <label class="rating-label">
                                <input type="radio" name="survey_answer" value="<?php echo esc_attr($value); ?>" required>
                                <span class="rating-circle"><?php echo esc_html($label); // Jika labelnya emoji, akan tampil ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($current_question['jenis'] == 2) : // Tipe Isian ?>
                    <textarea name="survey_answer" class="survey-input" rows="5" required></textarea>
                <?php endif; ?>
            </div>

            <button type="submit" class="button-next-question">Lanjut</button>
        </form>
    <?php else: ?>
        <p>Pertanyaan tidak ditemukan.</p>
    <?php endif; ?>
</div>
<?php
// get_footer();
?>


// terima-kasih

<?php
// get_header();
?>
<div id="doran-survey-container">
    <h1>Terima Kasih!</h1>
    <p>Terima kasih telah meluangkan waktu untuk mengisi survei kami. Masukan Anda sangat berharga bagi kami.</p>
    <a href="<?php echo home_url('/'); ?>">Kembali ke Beranda</a>
</div>
<?php
// get_footer();
?>