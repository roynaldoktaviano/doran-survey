<?php

/**
 * Doran Survey
 *
 * @wordpress-plugin
 * Plugin Name:       Doran Survey
 * Plugin URI:        https://doran.id/
 * Description:       Doran Survey get data from API
 * Version:           1.0.0
 * Requires at least: 3.4
 * Requires PHP:      7.4
 * Author:            PT Doran Sukses Indonesia
 * Text Domain:       plugin-slug
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */
// Mencegah akses langsung ke file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mendaftarkan Custom Post Type untuk menampung data hasil survei.
 */
function doran_survey_register_submission_cpt() {
    $args = array(
        'labels'        => array(
            'name'          => 'Survey Submissions',
            'singular_name' => 'Survey Submission',
            'add_new'       => _x( 'Add New', 'survey_submission' ), // Teks ini tidak akan muncul
            'add_new_item'  => _x( 'Add New Survey Submission', 'survey_submission' ),
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_position' => 20,
        'menu_icon'     => 'dashicons-feedback',
        'supports'      => array( 'title' ),
        'capability_type' => 'post', // Gunakan 'post' sebagai dasar
        
        // --- BAGIAN PENTING DIMULAI DI SINI ---
        'capabilities' => array(
            // Matikan kemampuan untuk membuat post baru
            'create_posts' => false, 
        ),
        // Map meta caps untuk mencegah edit dan delete
        // Ini adalah cara WordPress untuk memastikan bahkan admin tidak bisa edit/delete post tipe ini
        'map_meta_cap' => true,
        // --- BAGIAN PENTING SELESAI ---
    );
    register_post_type( 'survey_submission', $args );
}
add_action( 'init', 'doran_survey_register_submission_cpt' );

function remove_survey_submission_row_actions($actions, $post) {
    // Cek apakah kita berada di CPT yang benar
    if ($post->post_type === 'survey_submission') {
        // Hapus link 'Edit'
        unset($actions['edit']);
        // Hapus link 'Quick Edit' (kuncinya adalah 'inline hide-if-no-js')
        unset($actions['inline hide-if-no-js']);
        // Hapus link 'Trash'
        unset($actions['trash']);
        // Hapus link 'View' (jika ada)
        unset($actions['view']);
    }
    return $actions;
}
add_filter('post_row_actions', 'remove_survey_submission_row_actions', 10, 2);

function remove_survey_submission_bulk_actions($actions) {
    // Kembalikan array kosong untuk menghapus semua opsi bulk action
    return array();
}
add_filter('bulk_actions-edit-survey_submission', 'remove_survey_submission_bulk_actions');

function set_custom_survey_submission_columns($columns) {
    unset($columns['cb']); 
    unset($columns['date']); // Hapus kolom tanggal default
    unset($columns['title']); // Hapus kolom tanggal default
    $columns['customer_name'] = 'Nama Pelanggan';
    $columns['customer_telp'] = 'No. Telepon';
    $columns['survey_media'] = 'Media Pembelian';
    $columns['submission_time'] = 'Waktu Submit';
    $columns['actions'] = 'Tindakan'; // Kolom baru untuk tombol
    return $columns;
}
add_filter('manage_survey_submission_posts_columns', 'set_custom_survey_submission_columns');

function custom_survey_submission_column_data($column, $post_id) {
    switch ($column) {
        case 'customer_name':
            echo esc_html(get_post_meta($post_id, 'customer_nama', true));
            break;

        case 'customer_telp':
            echo esc_html(get_post_meta($post_id, 'customer_telp', true));
            break;

        case 'survey_media':
            $media_id = intval(get_post_meta($post_id, 'survey_media', true));
            $media_text = $media_id;
            if ($media_id == 1) $media_text = 'Offline';
            elseif ($media_id == 2) $media_text = 'Online';
            elseif ($media_id == 3) $media_text = 'Marketplace';
            echo $media_text;
            break;
            
        case 'submission_time':
            echo get_the_date('d-m-Y H:i:s', $post_id);
            break;
            
        case 'actions':
            // Tombol ini akan memicu pop-up. Kita tambahkan post ID sebagai data-attribute.
            echo '<button type="button" class="button button-primary view-survey-details" data-postid="' . $post_id . '">Lihat Jawaban</button>';
            break;
    }
}
add_action('manage_survey_submission_posts_custom_column', 'custom_survey_submission_column_data', 10, 2);

function add_survey_details_modal_html() {
    global $post_type;
    // Pastikan modal hanya muncul di halaman CPT kita
    if ($post_type == 'survey_submission') {
        echo '
        <div id="survey-modal-overlay" style="display:none;"></div>
        <div id="survey-modal-wrap" style="display:none;">
            <div id="survey-modal-header">
                <h2>Detail Jawaban Survei</h2>
                <button type="button" class="notice-dismiss" id="survey-modal-close"><span class="screen-reader-text">Dismiss this notice.</span></button>
            </div>
            <div id="survey-modal-content">
                <p class="loading-text">Memuat data...</p>
            </div>
        </div>
        ';
    }
}
add_action('admin_footer', 'add_survey_details_modal_html');

/**
 * Fungsi yang menangani request AJAX untuk mengambil detail jawaban.
 */
function get_survey_details_ajax_handler() {
    // Verifikasi keamanan
    check_ajax_referer('get_survey_details_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if ($post_id === 0) {
        wp_send_json_error('ID Post tidak valid.');
    }

    $answers = get_post_meta($post_id, 'survey_answers', true);
    
    if (empty($answers)) {
        wp_send_json_success('<p>Tidak ada data jawaban yang tersimpan.</p>');
    }

    // Ubah data jawaban menjadi HTML yang rapi
    $html = '<table class="wp-list-table widefat striped">';
    $html .= '<thead><tr><th scope="col">Pertanyaan</th><th scope="col">Jawaban</th></tr></thead>';
    $html .= '<tbody>';

    foreach ($answers as $answer) {
        $html .= '<tr>';
        $html .= '<td>' . esc_html($answer['soal']) . '</td>';
        $html .= '<td><strong>' . esc_html($answer['jawab']) . '</strong></td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';

    wp_send_json_success($html);
}
add_action('wp_ajax_get_survey_details', 'get_survey_details_ajax_handler');

function doran_survey_enqueue_assets() {
    // Gunakan global $post untuk memastikan kita berada di dalam konteks halaman
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'doran_survey_form' ) ) {
        // Enqueue CSS
        wp_enqueue_style('doran-survey-style', plugin_dir_url( __FILE__ ) . 'css/style.css', [], '1.1.0');
        // PERBAIKAN: Enqueue JavaScript hanya di halaman survei
        wp_enqueue_script('doran-survey-script', plugin_dir_url( __FILE__ ) . 'js/script.js', [], '2.0.0', true);
    }
}
add_action( 'wp_enqueue_scripts', 'doran_survey_enqueue_assets' );

/**
 * Memuat script dan style khusus untuk halaman admin CPT kita.
 */
function load_custom_admin_assets($hook) {
    global $post_type;
    // Hanya load di halaman daftar CPT 'survey_submission'
    if ($hook == 'edit.php' && $post_type == 'survey_submission') {
        wp_enqueue_script('doran-admin-script', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), '1.0', true);
        wp_enqueue_style('doran-admin-style', plugin_dir_url(__FILE__) . 'css/admin.css', array(), '1.0');
        
        // Mengirim data penting dari PHP ke JavaScript, seperti nonce untuk keamanan AJAX
        wp_localize_script('doran-admin-script', 'doran_survey_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('get_survey_details_nonce')
        ));
    }
}
add_action('admin_enqueue_scripts', 'load_custom_admin_assets');



function doran_survey_display_form_shortcode() {
    // Gunakan output buffering untuk menangkap HTML
    ob_start();

    // === LOGIKA GET API DAN AUTOfILL ===

    // 1. Cek apakah parameter 'ref' ada. Jika tidak, tampilkan pesan dan berhenti.
    if ( empty( $_GET['ref'] ) ) {
        echo '<p>Error: Link survei tidak valid atau tidak lengkap.</p>';
        return ob_get_clean(); // Mengembalikan buffer dan menghentikan eksekusi
    }

    // 2. Ambil dan bersihkan parameter 'ref'
    $ref = sanitize_text_field( $_GET['ref'] );

    // 3. Buat parameter 'base'
    $base = md5( date('Y-m-d') . '_ord_' . $ref );

    // 4. Lakukan request GET API
    $api_url = sprintf('https://kasir.doran.id/api/product/detail_order?base=e55c758095b2361eede2cc07627e6555&ref=NTczNDIxMQ==');
    $response = wp_remote_get( $api_url );

    // Inisialisasi variabel autofill
    $nama = '';
    $telp = '';
    $email = '';
    $domisili = '';
    $error_message = '';
    
    if ( is_wp_error( $response ) ) {
        $error_message = 'Gagal terhubung ke server. Silakan coba lagi nanti.';
    } else {
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data || $data['status'] != 'true' ) {
            $error_message = 'Data transaksi tidak ditemukan. Pastikan link survei Anda benar.';
        } else {
            // Isi variabel dari response API
            $detail = $data['data'];
            $nama = esc_attr( $detail['xx_nama_pelanggan'] );
            $telp = esc_attr( $detail['xx_telp_pelanggan'] );
            $email = esc_attr( $detail['xx_email'] );
            $domisili = esc_attr( $detail['xx_alamat_pelanggan'] );
        }
    }
    
    // Jika ada error, tampilkan dan berhenti
    if ( ! empty( $error_message ) ) {
        echo '<p>' . esc_html( $error_message ) . '</p>';
        return ob_get_clean();
    }

    // === TAMPILKAN HTML FORM ===
    ?>
<div id="doran-survey-container">
    <form id="doran-survey-form" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">

        <input type="hidden" name="action" value="submit_doran_survey">
        <input type="hidden" name="survey_ref" value="<?php echo esc_attr( $ref ); ?>">
        <?php wp_nonce_field( 'doran_survey_action', 'doran_survey_nonce' ); ?>



        <h3>Data Anda</h3>
        <div class="data-diri-atas">
            <div class="form-group">
                <label for="nama">Nama</label>
                <input type="text" id="nama" name="customer_nama" value="<?php echo $nama; ?>" readonly required>
            </div>
            <div class="form-group">
                <label for="telp">No. Telepon</label>
                <input type="text" id="telp" name="customer_telp" value="<?php echo $telp; ?>" readonly required>
            </div>
        </div>

        <div class="data-diri-bawah">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="customer_email" value="<?php echo $email; ?>"
                    <?php echo empty($email) ? '' : 'readonly'; ?> required>
            </div>
            <div class="form-group">
                <label for="domisili">Domisili</label>
                <input type="text" id="domisili" name="customer_domisili" value="<?php echo $domisili; ?>"
                    <?php echo empty($domisili) ? '' : 'readonly'; ?> required>
            </div>
        </div>



        <hr>
        <h3>Form Survei</h3>

        <div class="form-group">
            <label for="media-selector">Di mana Anda melakukan pembelian?</label>
            <select id="media-selector" name="survey_media" required>
                <option value="">-- Pilih Lokasi Pembelian --</option>
                <option value="1">Store Offline (Mall / Toko)</option>
                <option value="2">Website / Aplikasi Doran Gadget</option>
                <option value="3">Marketplace (Tokopedia, Shopee, dll.)</option>
            </select>
        </div>

        <div id="questions-for-1" class="question-set" style="display:none;">
            <h4>Pertanyaan Khusus Toko Offline</h4>

            <!--  Offline Q1  -->
            <div class="form-group">
                <label>1. Apakah kualitas produk yang Anda beli sesuai dengan deskripsi yang diberikan?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][0][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>
                    <input type="hidden" name="survey_data[offline][0][opsi][1]" value="Sangat Tidak Sesuai">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][0][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>
                    <input type="hidden" name="survey_data[offline][0][opsi][2]" value="Tidak Sesuai">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][0][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>
                    <input type="hidden" name="survey_data[offline][0][opsi][3]" value="Cukup Sesuai">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][0][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>
                    <input type="hidden" name="survey_data[offline][0][opsi][4]" value="Sesuai">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][0][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>
                    <input type="hidden" name="survey_data[offline][0][opsi][5]" value="Sangat Sesuai">

                </div>
                <input type="hidden" name="survey_data[offline][0][soal]"
                    value="Apakah kualitas produk yang Anda beli sesuai dengan deskripsi yang diberikan?">
                <input type="hidden" name="survey_data[offline][0][jenis]" value="1">
            </div>

            <!--  Offline Q2  -->
            <div class="form-group">
                <label>2. Bagaimana penilaian Anda terhadap harga produk di Doran Gadget?</label>
                <div class="rating-scale-group">
                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][1][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>
                    <input type="hidden" name="survey_data[offline][1][opsi][1]" value="Sangat Tidak Sesuai">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][1][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>
                    <input type="hidden" name="survey_data[offline][1][opsi][2]" value="Tidak Sesuai">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][1][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>
                    <input type="hidden" name="survey_data[offline][1][opsi][3]" value="Cukup Sesuai">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][1][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>
                    <input type="hidden" name="survey_data[offline][1][opsi][4]" value="Sesuai">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][1][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>
                    <input type="hidden" name="survey_data[offline][1][opsi][5]" value="Sangat Sesuai">

                </div>
                <input type="hidden" name="survey_data[offline][1][soal]"
                    value="Bagaimana penilaian Anda terhadap harga produk di Doran Gadget?">
                <input type="hidden" name="survey_data[offline][1][jenis]" value="1">
            </div>

            <!--  Offline Q3  -->
            <div class="form-group">
                <label>3. Bagaimana pelayanan tim SPG Doran Gadget saat Anda membutuhkan bantuan?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][2][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][2][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][2][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][2][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][2][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[offline][2][soal]"
                    value="Bagaimana pelayanan tim SPG Doran Gadget saat Anda membutuhkan bantuan?">
                <input type="hidden" name="survey_data[offline][2][jenis]" value="1">
            </div>

            <!--  Offline Q4  -->
            <div class="form-group">
                <label>4. Apakah metode pembayaran baik tunai maupun non-tunai yang disediakan sudah memenuhi kebutuhan
                    Anda?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][3][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif" alt="😡" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][3][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif" alt="☹"
                                width="32" height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][3][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle"><img
                                src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif" alt="😐" width="32"
                                height="32"></span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][3][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif" alt="😊" width="32"
                                height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][3][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif" alt="😀" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[offline][3][soal]"
                    value="Apakah metode pembayaran baik tunai maupun non-tunai yang disediakan sudah memenuhi kebutuhan Anda?">
                <input type="hidden" name="survey_data[offline][3][jenis]" value="1">
            </div>

            <!--  Offline Q5  -->
            <div class="form-group">
                <label>5. Apakah Anda merasa terbantu dengan layanan experience store dimana konsumen mencoba langsung
                    produk yang akan dibeli di toko?
                    Anda?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][4][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif" alt="😡" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][4][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif" alt="☹"
                                width="32" height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][4][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle"><img
                                src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif" alt="😐" width="32"
                                height="32"></span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][4][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif" alt="😊" width="32"
                                height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][4][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif" alt="😀" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[offline][4][soal]"
                    value="Apakah Anda merasa terbantu dengan layanan experience store dimana konsumen mencoba langsung produk yang akan dibeli di toko?">
                <input type="hidden" name="survey_data[offline][4][jenis]" value="1">
            </div>

            <!--  Offline Q6  -->
            <div class="form-group">
                <label>6. Bagaimana pengalaman Anda secara keseluruhan saat berbelanja di Doran Gadget?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][5][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif" alt="😡" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][5][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif" alt="☹"
                                width="32" height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][5][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle"><img
                                src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif" alt="😐" width="32"
                                height="32"></span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][5][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif" alt="😊" width="32"
                                height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][5][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif" alt="😀" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[offline][5][soal]"
                    value="Bagaimana pengalaman Anda secara keseluruhan saat berbelanja di Doran Gadget?">
                <input type="hidden" name="survey_data[offline][5][jenis]" value="1">
            </div>

            <!--  Offline Q7  -->
            <div class="form-group">
                <label>7. Seberapa besar kemungkinan Anda merekomendasikan Doran Gadget kepada orang lain?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][6][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][6][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][6][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][6][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[offline][6][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[offline][6][soal]"
                    value="Seberapa besar kemungkinan Anda merekomendasikan Doran Gadget kepada orang lain?">
                <input type="hidden" name="survey_data[offline][6][jenis]" value="1">
            </div>

            <!--  Offline Q8  -->
            <div class="form-group">
                <label>8. Apa Masukan Anda untuk Doran Gadget untuk lebih baik ke depannya? Tuliskan Jawaban
                    Anda!</label>
                <textarea name="survey_data[offline][7][jawab]" class="survey-input" rows=5 disabled></textarea>
                <input type="hidden" name="survey_data[offline][7][soal]"
                    value="Apa Masukan Anda untuk Doran Gadget untuk lebih baik ke depannya? Tuliskan Jawaban Anda!">
                <input type="hidden" name="survey_data[offline][7][jenis]" value="2">
            </div>
        </div>

        <div id="questions-for-2" class="question-set" style="display:none;">
            <h4>Pertanyaan Khusus Belanja Online</h4>
            <!--  online Q1  -->
            <div class="form-group">
                <label>1. Seberapa cepat website/aplikasi Doran Gadget dalam memuat halaman atau produk yang Anda
                    inginkan?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][0][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][0][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][0][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][0][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][0][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[online][0][soal]"
                    value="Seberapa cepat website/aplikasi Doran Gadget dalam memuat halaman atau produk yang Anda inginkan?">
                <input type="hidden" name="survey_data[online][0][jenis]" value="1">
            </div>

            <!--  online Q2  -->
            <div class="form-group">
                <label>2. Bagaimana dengan garansi produk yang diberikan oleh Doran Gadget untuk produk yang
                    dibeli?</label>
                <div class="rating-scale-group">
                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][1][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][1][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][1][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][1][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][1][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[online][1][soal]"
                    value="Bagaimana dengan garansi produk yang diberikan oleh Doran Gadget untuk produk yang dibeli?">
                <input type="hidden" name="survey_data[online][1][jenis]" value="1">
            </div>

            <!--  online Q3  -->
            <div class="form-group">
                <label>3. Apakah metode pembayaran yang tersedia di website/aplikasi Doran Gadget memenuhi kebutuhan
                    Anda?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][2][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][2][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][2][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][2][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][2][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[online][2][soal]"
                    value="Apakah metode pembayaran yang tersedia di website/aplikasi Doran Gadget memenuhi kebutuhan Anda?">
                <input type="hidden" name="survey_data[online][2][jenis]" value="1">
            </div>

            <!--  online Q4  -->
            <div class="form-group">
                <label>4. Bagaimana penilaian Anda terhadap proses navigasi hingga checkout di website atau aplikasi
                    Doran Gadget?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][3][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][3][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][3][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][3][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][3][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[online][3][soal]"
                    value="Bagaimana penilaian Anda terhadap proses navigasi hingga checkout di website atau aplikasi Doran Gadget?">
                <input type="hidden" name="survey_data[online][3][jenis]" value="1">
            </div>

            <!--  online Q5  -->
            <div class="form-group">
                <label>5. Bagaimana pelayanan customer service Doran Gadget saat Anda membutuhkan bantuan?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][4][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif" alt="😡" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][4][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif" alt="☹"
                                width="32" height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][4][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle"><img
                                src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif" alt="😐" width="32"
                                height="32"></span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][4][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif" alt="😊" width="32"
                                height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][4][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif" alt="😀" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[online][4][soal]"
                    value="Bagaimana pelayanan customer service Doran Gadget saat Anda membutuhkan bantuan?">
                <input type="hidden" name="survey_data[online][4][jenis]" value="1">
            </div>

            <!--  online Q6  -->
            <div class="form-group">
                <label>6. Seberapa besar kemungkinan Anda merekomendasikan untuk berbelanja di Doran Gadget melalui
                    website atau aplikasi kepada orang lain?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][5][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][5][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][5][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][5][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[online][5][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[online][5][soal]"
                    value="Seberapa besar kemungkinan Anda merekomendasikan Doran Gadget kepada orang lain?">
                <input type="hidden" name="survey_data[online][5][jenis]" value="1">
            </div>

            <!--  online Q7  -->
            <div class="form-group">
                <label>7. Apa Masukan Anda untuk Doran Gadget untuk lebih baik ke depannya? Tuliskan Jawaban
                    Anda!</label>
                <textarea name="survey_data[online][6][jawab]" class="survey-input" rows=5 disabled></textarea>
                <input type="hidden" name="survey_data[online][6][soal]"
                    value="Apa Masukan Anda untuk Doran Gadget untuk lebih baik ke depannya? Tuliskan Jawaban Anda!">
                <input type="hidden" name="survey_data[online][6][jenis]" value="2">
            </div>
        </div>

        <div id="questions-for-3" class="question-set" style="display:none;">
            <h4>Pertanyaan Khusus Belanja di Marketplace</h4>
            <!--  marketplace Q1  -->
            <div class="form-group">
                <label>1. Seberapa mudah navigasi Anda menemukan produk yang dicari di Doran Gadget melalui
                    marketplace?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][0][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][0][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][0][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][0][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][0][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[marketplace][0][soal]" value="Seberapa mudah navigasi Anda menemukan produk yang dicari di Doran Gadget melalui
                    marketplace?">
                <input type="hidden" name="survey_data[marketplace][0][jenis]" value="1">
            </div>

            <!--  marketplace Q2  -->
            <div class="form-group">
                <label>2.Apakah metode pembayaran yang tersedia di Marketplace Doran Gadget memenuhi kebutuhan
                    Anda?</label>
                <div class="rating-scale-group">
                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][1][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][1][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][1][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][1][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][1][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[marketplace][1][soal]" value="Apakah metode pembayaran yang tersedia di Marketplace Doran Gadget memenuhi kebutuhan
                    Anda?">
                <input type="hidden" name="survey_data[marketplace][1][jenis]" value="1">
            </div>

            <!--  marketplace Q3  -->
            <div class="form-group">
                <label>3.Seberapa besar kemungkinan Anda merekomendasikan untuk berbelanja di Doran Gadget melalui
                    marketplace kepada orang lain?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][2][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">1</span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][2][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">2</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][2][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle">3</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][2][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">4</span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][2][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">5</span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[marketplace][2][soal]" value="Seberapa besar kemungkinan Anda merekomendasikan untuk berbelanja di Doran Gadget melalui
                    marketplace kepada orang lain?">
                <input type="hidden" name="survey_data[marketplace][2][jenis]" value="1">
            </div>


            <!--  marketplace Q4  -->
            <div class="form-group">
                <label>4. Bagaimana dengan garansi produk yang diberikan oleh Doran Gadget untuk produk yang
                    dibeli?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][3][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif" alt="😡" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][3][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif" alt="☹"
                                width="32" height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][3][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle"><img
                                src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif" alt="😐" width="32"
                                height="32"></span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][3][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif" alt="😊" width="32"
                                height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][3][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif" alt="😀" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[marketplace][3][soal]" value="Bagaimana dengan garansi produk yang diberikan oleh Doran Gadget untuk produk yang
                    dibeli?">
                <input type="hidden" name="survey_data[marketplace][3][jenis]" value="1">
            </div>

            <!--  marketplace Q5  -->
            <div class="form-group">
                <label>5. Bagaimana dengan garansi produk yang diberikan oleh Doran Gadget untuk produk yang
                    dibeli?</label>
                <div class="rating-scale-group">

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][4][jawab]" value="1" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif" alt="😡" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Tidak Sesuai</span>
                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][4][jawab]" value="2" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif" alt="☹"
                                width="32" height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][4][jawab]" value="3" class="survey-input"
                            disabled>
                        <span class="rating-circle"><img
                                src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif" alt="😐" width="32"
                                height="32"></span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][4][jawab]" value="4" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif" alt="😊" width="32"
                                height="32">
                        </span>

                    </label>

                    <label class="rating-label">
                        <input type="radio" name="survey_data[marketplace][4][jawab]" value="5" class="survey-input"
                            disabled>
                        <span class="rating-circle">
                            <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif" alt="😀" width="32"
                                height="32">
                        </span>
                        <span class="rating-text">Sangat Sesuai</span>
                    </label>

                </div>
                <input type="hidden" name="survey_data[marketplace][4][soal]"
                    value="Bagaimana pengalaman Anda dengan packing produk yang dibeli?">
                <input type="hidden" name="survey_data[marketplace][4][jenis]" value="1">
            </div>


            <!--  marketplace Q6  -->
            <div class="form-group">
                <label>6. Apa Masukan Anda untuk Doran Gadget untuk lebih baik ke depannya? Tuliskan Jawaban
                    Anda!</label>
                <textarea name="survey_data[marketplace][5][jawab]" class="survey-input" rows=5 disabled></textarea>
                <input type="hidden" name="survey_data[marketplace][5][soal]"
                    value="Apa Masukan Anda untuk Doran Gadget untuk lebih baik ke depannya? Tuliskan Jawaban Anda!">
                <input type="hidden" name="survey_data[marketplace][5][jenis]" value="2">
            </div>
        </div>

        <div class="form-group">
            <button type="submit">Submit Survey</button>
        </div>
    </form>
</div>
<?php

    // Mengembalikan semua output HTML
    return ob_get_clean();
}
add_shortcode( 'doran_survey_form', 'doran_survey_display_form_shortcode' );

function doran_survey_handle_form_submission() {
    // wp_die('<pre>' . print_r($_POST, true) . '</pre>');
    // 1. Keamanan: Cek Nonce
    if ( ! isset( $_POST['doran_survey_nonce'] ) || ! wp_verify_nonce( $_POST['doran_survey_nonce'], 'doran_survey_action' ) ) {
        wp_die('Verifikasi keamanan gagal. Silakan kembali dan coba lagi.');
    }

    // 2. Ambil data dari form dan bersihkan
    // $ref = sanitize_text_field( $_POST['survey_ref'] );
    // $base = md5( date('Y-m-d') . '_ord_' . $ref );
    $media = isset($_POST['survey_media']) ? intval($_POST['survey_media']) : 0;
    $submitted_data = $_POST['survey_data']; 

     // Ambil data pelanggan untuk disimpan di WP
    $customer_nama = sanitize_text_field( $_POST['customer_nama'] );
    $customer_telp = sanitize_text_field( $_POST['customer_telp'] );
    $customer_email = sanitize_email( $_POST['customer_email'] );

    // Ambil hanya set pertanyaan yang aktif sesuai media
    $media_key = '';
    if ($media === 1) $media_key = 'offline';
    if ($media === 2) $media_key = 'online';
    if ($media === 3) $media_key = 'marketplace';
    
    $active_questions = isset($submitted_data[$media_key]) ? $submitted_data[$media_key] : [];

    //  wp_die('<p>Data yang akan di-loop ($active_questions):</p><pre>' . print_r($active_questions, true) . '</pre>');

    // 3. Format ulang data survei sesuai spek API
    $formatted_data = [];
     $formatted_data = [];
    if (!empty($active_questions)) {
        foreach ($active_questions as $index => $item) {
            if (isset($item['jawab']) && !empty($item['jawab'])) {
                $jenis = isset($item['jenis']) ? intval($item['jenis']) : 0;
                $soal = isset($item['soal']) ? sanitize_text_field($item['soal']) : '';
                $jawab = ($jenis === 1) ? sanitize_text_field($item['jawab']) : sanitize_textarea_field($item['jawab']);
                $opsi = isset($item['opsi']) && is_array($item['opsi']) ? array_map('sanitize_text_field', $item['opsi']) : [];

                // Variabel $new_item sekarang PASTI terisi dengan benar
                $new_item = [
                    'urutan' => $index + 1,
                    'jenis' => $jenis,
                    'soal' => $soal,
                    'jawab' => $jawab,
                    'opsi' => $opsi,
                ];
                
                $formatted_data[] = $new_item;
            }
        }
    }

    // Cek jika ada jawaban untuk disimpan
    if ( ! empty( $formatted_data ) ) {

        // Buat judul post yang deskriptif
        $post_title = 'Survei dari ' . $customer_nama . ' pada ' . date('d-m-Y H:i');

        // Siapkan data post
        $post_data = array(
            'post_title'   => $post_title,
            'post_status'  => 'publish', // Langsung publish agar terlihat
            'post_type'    => 'survey_submission', // Nama CPT yang kita daftarkan
        );

        
        // Masukkan post baru ke database dan dapatkan ID-nya
        $post_id = wp_insert_post( $post_data );

        // if (!is_wp_error($post_id)) {  
        //     // --- TAMBAHKAN KODE DEBUG INI ---
        //     $debug_data_to_save = [
        //         'ID Post yang Dibuat' => $post_id,
        //         'Nama Pelanggan' => $customer_nama,
        //         'Telepon Pelanggan' => $customer_telp,
        //         'Email Pelanggan' => $customer_email,
        //         'REF Transaksi' => $ref,
        //         'ID Media' => $media,
        //         'Data Jawaban' => $formatted_data
        //     ];
        //     wp_die('<p>Data yang akan disimpan ke database:</p><pre>' . print_r($debug_data_to_save, true) . '</pre>');
        //     // ---------------------------------

        //     // Kode update_post_meta Anda ada di bawahnya
        //     update_post_meta($post_id, 'customer_nama', $customer_nama);
        //     // ...
        // }

        // Jika post berhasil dibuat, simpan semua data lainnya sebagai custom fields (post meta)
        if ( $post_id && ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, 'customer_nama', $customer_nama );
            update_post_meta( $post_id, 'customer_telp', $customer_telp );
            update_post_meta( $post_id, 'customer_email', $customer_email );
            update_post_meta( $post_id, 'transaction_ref', $ref );
            update_post_meta( $post_id, 'survey_media', $media );
            
            // Simpan semua jawaban survei dalam satu field (WordPress akan menanganinya sebagai array)
            update_post_meta( $post_id, 'survey_answers', $submitted_data[$media_key] );
        }
    }

    // 5. Siapkan payload untuk POST API
    $body_payload = [
        'media' => $media,
        'data'  => $formatted_data
    ];

    $api_url = sprintf( 'https://kasir.doran.id/api/product/submit?base=e55c758095b2361eede2cc07627e6555&ref=NTczNDIxMQ==');
    
    $response = wp_remote_post( $api_url, [
        'method'    => 'POST',
        'headers'   => ['Content-Type' => 'application/json; charset=utf-8'],
        'body'      => json_encode( $body_payload ),
        'data_format' => 'body',
    ]);
    
    // 6. Redirect pengguna setelah submit
    // Buat halaman "Terima Kasih" di WordPress dengan slug 'terima-kasih'
    $redirect_url = home_url('/thank-you'); 

    if ( is_wp_error( $response ) ) {
        // Jika gagal, redirect dengan parameter error
        $redirect_url = add_query_arg('submit_status', 'error', home_url('/doran-survey'));
    } else {
        $response_code = wp_remote_retrieve_response_code( $response );
        if ($response_code !== 200) {
            // Jika status tidak 200, anggap error
            $error_body = wp_remote_retrieve_body($response);
    wp_die(
        'SUBMIT FAILED!<br>' .
        'Response Code: ' . esc_html($response_code) . '<br><br>' .
        'Response Body: <pre>' . esc_html($error_body) . '</pre>'
    );
            $redirect_url = add_query_arg('submit_status', 'failed', home_url('/doran-survey'));
        } else {
            // Sukses
            $redirect_url = add_query_arg('submit_status', 'success', home_url('/terima-kasih'));
        }
    }
    
    wp_redirect( $redirect_url );
    exit;
}
// Hook untuk pengguna yang tidak login
add_action( 'admin_post_nopriv_submit_doran_survey', 'doran_survey_handle_form_submission' );
// Hook untuk pengguna yang login
add_action( 'admin_post_submit_doran_survey', 'doran_survey_handle_form_submission' );
?>