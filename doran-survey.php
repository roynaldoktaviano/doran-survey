<?php
/**
 * Doran Survey
 *
 * @wordpress-plugin
 * Plugin Name:       Doran Survey
 * Plugin URI:        https://doran.id/
 * Description:       Doran Survey get data from API
 * Version:           2.1.0
 * Requires at least: 3.4
 * Requires PHP:      7.4
 * Author:            PT Doran Sukses Indonesia
 * Text Domain:       plugin-slug
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Mulai Sesi PHP jika belum ada
if ( ! function_exists('doran_survey_start_session') ) {
    function doran_survey_start_session() {
        if ( ! session_id() ) {
            session_start();
        }
    }
}
add_action('init', 'doran_survey_start_session', 1);

// 2. Definisikan CPT untuk menyimpan hasil survei
if ( ! function_exists('doran_survey_register_submission_cpt') ) {
    function doran_survey_register_submission_cpt() {
        // ... (Isi fungsi sama persis dengan kode v3.1 sebelumnya)
        $args = array('labels' => array('name' => 'Hasil Survei', 'singular_name' => 'Hasil Survei', 'menu_name' => 'Doran Survey'), 'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 25, 'menu_icon' => 'dashicons-portfolio', 'supports' => array('title'), 'capability_type' => 'post', 'capabilities' => array('create_posts' => false), 'map_meta_cap' => true);
        register_post_type('survey_submission', $args);
    }
    add_action('init', 'doran_survey_register_submission_cpt');
}

// 3. Hapus Aksi Baris di Tabel Admin
if ( ! function_exists('doran_survey_remove_row_actions') ) {
    function doran_survey_remove_row_actions($actions, $post) {
        if ($post->post_type === 'survey_submission') {
            unset($actions['edit'], $actions['inline hide-if-no-js'], $actions['trash'], $actions['view']);
        }
        return $actions;
    }
    add_filter('post_row_actions', 'doran_survey_remove_row_actions', 10, 2);
}

// ... (Tambahkan if !function_exists untuk SEMUA fungsi global lainnya: remove_bulk_actions, set_custom_columns, dll.) ...

// Di bawah ini saya sertakan lagi semua fungsi dengan pembungkus tersebut.

if ( ! function_exists('doran_survey_remove_bulk_actions') ) {
    function doran_survey_remove_bulk_actions($actions) {
        unset($actions['edit'], $actions['trash']);
        return $actions;
    }
    add_filter('bulk_actions-edit-survey_submission', 'doran_survey_remove_bulk_actions');
}

if ( ! function_exists('doran_survey_set_custom_columns') ) {
    function doran_survey_set_custom_columns($columns) {
        unset($columns['cb'], $columns['date'], $columns['title']);
        $columns['customer_name']   = 'Nama Pelanggan';
        $columns['customer_telp']   = 'No. Telepon';
        $columns['survey_media']    = 'Media Pembelian';
        $columns['submission_time'] = 'Waktu Submit';
        $columns['actions']         = 'Tindakan';
        return $columns;
    }
    add_filter('manage_survey_submission_posts_columns', 'doran_survey_set_custom_columns');
}

if ( ! function_exists('doran_survey_custom_column_data') ) {
    function doran_survey_custom_column_data($column, $post_id) {
        switch ($column) {
            case 'customer_name': echo esc_html(get_post_meta($post_id, 'customer_nama', true)); break;
            case 'customer_telp': echo esc_html(get_post_meta($post_id, 'customer_telp', true)); break;
            case 'survey_media':
                $media_id = intval(get_post_meta($post_id, 'survey_media', true));
                $media_text = 'N/A';
                if ($media_id == 1) $media_text = 'Offline'; elseif ($media_id == 2) $media_text = 'Online'; elseif ($media_id == 3) $media_text = 'Marketplace';
                echo $media_text;
                break;
            case 'submission_time': echo get_the_date('d-m-Y H:i:s', $post_id); break;
            case 'actions': echo '<button type="button" class="button button-primary view-survey-details" data-postid="' . $post_id . '">Lihat Jawaban</button>'; break;
        }
    }
    add_action('manage_survey_submission_posts_custom_column', 'doran_survey_custom_column_data', 10, 2);
}

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


// Untuk mempersingkat, saya akan langsung ke shortcode utama. Prinsipnya sama, bungkus semua fungsi dalam 'if !function_exists'.

if ( ! function_exists('get_doran_survey_questions') ) {
    function get_doran_survey_questions() {
        return [
            'offline' => [
            1 => [
                'soal'          => 'Apakah kualitas produk yang Anda beli sesuai dengan deskripsi yang diberikan?', 
                'jenis'         => 1, 
                'tipe_tampilan' => 'teks',
                'opsi'          => [1=>'Sangat Tidak Sesuai', 2=>'Tidak Sesuai', 3=>'Cukup Sesuai', 4=>'Sesuai', 5=>'Sangat Sesuai']
            ],
            2 => [
                'soal'          => 'Bagaimana penilaian Anda terhadap harga produk di Doran Gadget?', 
                'jenis'         => 1,
                'tipe_tampilan' => 'teks',
                'opsi'          => [1=>'Sangat Tidak Sesuai', 2=>'Tidak Sesuai', 3=>'Cukup Sesuai', 4=>'Sesuai', 5=>'Sangat Sesuai']
            ],
            3 => [
                'soal'          => 'Bagaimana pelayanan tim SPG Doran Gadget saat Anda membutuhkan bantuan?', 
                'jenis'         => 1,
                'tipe_tampilan' => 'teks',
                'opsi'          => [1=>'Sangat Tidak Sesuai', 2=>'Tidak Sesuai', 3=>'Cukup Sesuai', 4=>'Sesuai', 5=>'Sangat Sesuai']
            ],
            4 => [
                'soal'          => 'Apakah metode pembayaran baik tunai maupun non-tunai yang disediakan sudah memenuhi kebutuhan Anda?', 
                'jenis'         => 1,
                'tipe_tampilan' => 'emoji',
                'opsi'          => [
                    // Setiap opsi sekarang adalah array yang berisi 'url' dan 'label'
                    1 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif', 'label' => 'Sangat Buruk'],
                    2 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif', 'label' => 'Buruk'],
                    3 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif', 'label' => 'Cukup'],
                    4 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif', 'label' => 'Bagus'],
                    5 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif', 'label' => 'Sangat Bagus']
                ]
            ],
            5 => [
                'soal'          => 'Bagaimana dengan layanan experience store dimana konsumen mencoba langsung produk yang akan dibeli di toko?', 
                'jenis'         => 1,
                'tipe_tampilan' => 'emoji',
                'opsi'          => [
                    // Setiap opsi sekarang adalah array yang berisi 'url' dan 'label'
                    1 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif', 'label' => 'Sangat Buruk'],
                    2 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif', 'label' => 'Buruk'],
                    3 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif', 'label' => 'Cukup'],
                    4 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif', 'label' => 'Bagus'],
                    5 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif', 'label' => 'Sangat Bagus']
                ]
            ],
            6 => [
                'soal'          => 'Bagaimana pengalaman Anda secara keseluruhan saat berbelanja di Doran Gadget?', 
                'jenis'         => 1,
                'tipe_tampilan' => 'emoji',
                'opsi'          => [
                    // Setiap opsi sekarang adalah array yang berisi 'url' dan 'label'
                    1 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif', 'label' => 'Sangat Buruk'],
                    2 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif', 'label' => 'Buruk'],
                    3 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif', 'label' => 'Cukup'],
                    4 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif', 'label' => 'Bagus'],
                    5 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif', 'label' => 'Sangat Bagus']
                ]
            ],
            7 => [
                'soal'          => 'Seberapa besar kemungkinan Anda merekomendasikan Doran Gadget kepada orang lain?', 
                'jenis'         => 1,
                'tipe_tampilan' => 'teks',
                'opsi'          => [1=>'Sangat Tidak Mungkin', 2=>'Tidak Mungkin', 3=>'Mungkin', 4=>'Sangat Mungkin', 5=>'Pasti Merekomendasikan'] // Label lebih sesuai
            ],
            8 => [
                'soal'  => 'Apa Masukan Anda untuk Doran Gadget untuk lebih baik ke depannya? Tuliskan Jawaban Anda!', 
                'jenis' => 2 
            ]
        ],
        'online' => [
            1 => [
                'soal' => 'Seberapa cepat website/aplikasi Doran Gadget dalam memuat halaman atau produk yang Anda inginkan?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'teks',
                'opsi' => [1=>'Sangat Lambat', 2=>'Lambat', 3=>'Cukup Cepat', 4=>'Cepat', 5=>'Sangat Cepat']
            ],
            2 => [
                'soal' => 'Bagaimana dengan garansi produk yang diberikan oleh Doran Gadget untuk produk yang dibeli?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'teks',
                'opsi' => [1=>'Sangat Tidak Jelas', 2=>'Tidak Jelas', 3=>'Cukup Jelas', 4=>'Jelas', 5=>'Sangat Jelas']
            ],
            3 => [
                'soal' => 'Apakah metode pembayaran yang tersedia di website/aplikasi Doran Gadget memenuhi kebutuhan Anda?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'teks',
                'opsi' => [1=>'Sangat Tidak Lengkap', 2=>'Tidak Lengkap', 3=>'Cukup Lengkap', 4=>'Lengkap', 5=>'Sangat Lengkap']
            ],
            4 => [
                'soal' => 'Bagaimana penilaian Anda terhadap proses navigasi hingga checkout di website atau aplikasi Doran Gadget?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'teks',
                'opsi' => [1=>'Sangat Sulit', 2=>'Sulit', 3=>'Cukup Mudah', 4=>'Mudah', 5=>'Sangat Mudah']
            ],
            5 => [
                'soal' => 'Bagaimana pelayanan customer service Doran Gadget saat Anda membutuhkan bantuan?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'emoji',
                'opsi'          => [
                    // Setiap opsi sekarang adalah array yang berisi 'url' dan 'label'
                    1 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif', 'label' => 'Sangat Buruk'],
                    2 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif', 'label' => 'Buruk'],
                    3 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif', 'label' => 'Cukup'],
                    4 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif', 'label' => 'Bagus'],
                    5 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif', 'label' => 'Sangat Bagus']
                ]
            ],
            6 => [
                'soal' => 'Seberapa besar kemungkinan Anda merekomendasikan untuk berbelanja di Doran Gadget melalui website atau aplikasi kepada orang lain?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'teks',
                'opsi' => [1=>'Sangat Kecil', 2=>'Kecil', 3=>'Cukup', 4=>'Besar', 5=>'Sangat Besar']
            ],
            7 => [
                'soal' => 'Apa Masukan Anda untuk Doran Gadget (Online) untuk lebih baik ke depannya? Tuliskan Jawaban Anda!', 
                'jenis' => 2
            ]
        ],
        'marketplace' => [
            1 => [
                'soal' => 'Seberapa mudah navigasi Anda menemukan produk yang dicari di Doran Gadget melalui marketplace?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'teks',
                'opsi' => [1=>'Sangat Sulit', 2=>'Sulit', 3=>'Cukup Mudah', 4=>'Mudah', 5=>'Sangat Mudah']
            ],
            2 => [
                'soal' => 'Apakah metode pembayaran yang tersedia di Marketplace Doran Gadget memenuhi kebutuhan Anda?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'teks',
                'opsi' => [1=>'Sangat Tidak Lengkap', 2=>'Tidak Lengkap', 3=>'Cukup Lengkap', 4=>'Lengkap', 5=>'Sangat Lengkap']
            ],
            3 => [
                'soal' => 'Seberapa besar kemungkinan Anda merekomendasikan untuk berbelanja di Doran Gadget melalui marketplace kepada orang lain?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'teks',
                'opsi' => [1=>'Sangat Kecil', 2=>'Kecil', 3=>'Cukup', 4=>'Besar', 5=>'Sangat Besar']
            ],
            4 => [
                'soal' => 'Bagaimana dengan garansi produk yang diberikan oleh Doran Gadget untuk produk yang dibeli?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'emoji',
                'opsi'          => [
                    // Setiap opsi sekarang adalah array yang berisi 'url' dan 'label'
                    1 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif', 'label' => 'Sangat Buruk'],
                    2 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif', 'label' => 'Tidak Buruk'],
                    3 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif', 'label' => 'Cukup'],
                    4 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif', 'label' => 'Bagus'],
                    5 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif', 'label' => 'Sangat Bagus']
                ]
            ],
            5 => [
                'soal' => 'Bagaimana pengalaman Anda dengan packing produk yang dibeli?', 
                'jenis' => 1, 
                'tipe_tampilan' => 'emoji',
                'opsi'          => [
                    // Setiap opsi sekarang adalah array yang berisi 'url' dan 'label'
                    1 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f621/512.gif', 'label' => 'Sangat Buruk'],
                    2 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/2639_fe0f/512.gif', 'label' => 'Tidak Buruk'],
                    3 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f610/512.gif', 'label' => 'Cukup'],
                    4 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f60a/512.gif', 'label' => 'Bagus'],
                    5 => ['url' => 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f600/512.gif', 'label' => 'Sangat Bagus']
                ]
            ],
            6 => [
                'soal' => 'Apa Masukan Anda untuk Doran Gadget (Marketplace) untuk lebih baik ke depannya? Tuliskan Jawaban Anda!', 
                'jenis' => 2,
                
            ]
        ]
        ];
    }
}

if ( ! function_exists('doran_multi_page_survey_shortcode') ) {
    function doran_multi_page_survey_shortcode() {
    ob_start();
    $current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'start';

    global $doran_survey_data; // Siapkan variabel global untuk diakses template

    if ($current_step === 'question') {
        // Halaman pertanyaan: ambil semua data dari URL, BUKAN SESI
        $media_id = isset($_GET['media']) ? intval($_GET['media']) : 0;
        $question_number = isset($_GET['q']) ? intval($_GET['q']) : 1;
        $ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
        
        // Kirim data ini ke template
        $doran_survey_data = compact('media_id', 'question_number', 'ref');

        include(plugin_dir_path(__FILE__) . 'templates/doran-survey-question.php');

    } elseif ($current_step === 'thank_you') {
        include(plugin_dir_path(__FILE__) . 'templates/doran-survey-thankyou.php');
    } else { // Default ke halaman 'start'
        if (empty($_GET['ref'])) {
            echo '<p>Error: Link survei tidak valid.</p>';
        } else {
            // Logika untuk halaman start (mengambil data dari API) tetap sama
            $ref = sanitize_text_field($_GET['ref']);
            $base = md5(date('Y-m-d') . '_ord_' . $ref);
            // $api_url = sprintf('https://kasir.doran.id/api/product/detail_order?base=%s&ref=%s', $base, $ref);
            $api_url = sprintf('https://kasir.doran.id/api/product/detail_order?base=f7d520bcc0907a0e39069cc979c101e3&ref=NTczNDIxMQ==');
            $response = wp_remote_get($api_url);
            if(is_wp_error($response)) { echo '<p>Gagal terhubung ke server.</p>'; }
            else {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (!$data || !isset($data['status']) || $data['status'] != 'true' || empty($data['data'])) {
                    echo '<p>Data transaksi tidak ditemukan.</p>';
                } else {
                    $customer_data = $data['data'];
                    $doran_survey_data = [
                        'ref' => $ref,
                        'customer' => [
                            'nama'     => $customer_data['xx_nama_pelanggan'] ?? '',
                            'telp'     => $customer_data['xx_telp_pelanggan'] ?? '',
                            'email'    => $customer_data['xx_email'] ?? '',
                            'domisili' => $customer_data['xx_alamat_pelanggan'] ?? '',
                        ]
                    ];
                    include(plugin_dir_path(__FILE__) . 'templates/doran-survey-start.php');
                }
            }
        }
    }

    return ob_get_clean();
}
    add_shortcode('doran_survey_form', 'doran_multi_page_survey_shortcode');
}




if (!function_exists('doran_survey_handle_submission_with_transient')) {
    function doran_survey_handle_submission_with_transient() {
    if (!isset($_POST['doran_survey_nonce']) || !wp_verify_nonce($_POST['doran_survey_nonce'], 'doran_survey_step_action')) {
        wp_die('Verifikasi keamanan gagal.');
    }

    $ref = sanitize_text_field($_POST['survey_ref']);
    $current_step = sanitize_text_field($_POST['survey_step']);
    $page_url = home_url('/doran-survey/');

    $transient_key = 'doran_survey_progress_' . $ref;
    $survey_progress = get_transient($transient_key);
    
    if ($survey_progress === false) {
        $survey_progress = [];
    }

    $redirect_url = add_query_arg('ref', $ref, $page_url);

    if ($current_step === 'start') {
        // Logika ini sudah benar, membuat transient awal
       $survey_progress = [
            'customer_data' => [
                'nama'     => sanitize_text_field($_POST['customer_nama']),
                'telp'     => sanitize_text_field($_POST['customer_telp']),
                'email'    => sanitize_email($_POST['customer_email']),
                'domisili' => sanitize_text_field($_POST['customer_domisili']),
            ],
            'media_id' => intval($_POST['survey_media']),
            'answers'  => [] 
        ];

        $redirect_url = add_query_arg(['ref' => $ref, 'step' => 'question', 'q' => 1], $page_url);

    } elseif ($current_step === 'question') {
        $question_number = intval($_POST['question_number']);
    
        $current_answers = $survey_progress['answers'] ?? [];
        
        if (isset($_POST['survey_answer'])) {
            $current_answers[$question_number] = sanitize_textarea_field($_POST['survey_answer']);
        }
        $survey_progress['answers'] = $current_answers;
 
        
        $media_id = $survey_progress['media_id'] ?? 0;
        $media_key = ($media_id == 1) ? 'offline' : (($media_id == 2) ? 'online' : 'marketplace');
        $all_questions = get_doran_survey_questions();
        $total_questions = count($all_questions[$media_key] ?? []);
        
        $next_question_number = $question_number + 1;

        if ($next_question_number <= $total_questions) {
            $redirect_url = add_query_arg(['ref' => $ref, 'step' => 'question', 'q' => $next_question_number], $page_url);
        } else {
            // Sebelum redirect, simpan dulu transient terakhir kalinya
            set_transient($transient_key, $survey_progress, HOUR_IN_SECONDS);
            // Baru panggil fungsi final (yang akan membaca transient ini)
            doran_survey_process_final_submission_with_transient($ref);
            $redirect_url = add_query_arg(['ref' => $ref, 'step' => 'thank_you'], $page_url);
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    // Simpan progres ke transient di setiap langkah
    set_transient($transient_key, $survey_progress, HOUR_IN_SECONDS);
    
    wp_redirect($redirect_url);
    exit;
}
    add_action('admin_post_nopriv_submit_doran_survey_step', 'doran_survey_handle_submission_with_transient');
    add_action('admin_post_submit_doran_survey_step', 'doran_survey_handle_submission_with_transient');
}

if (!function_exists('doran_survey_process_final_submission_with_transient')) {
    function doran_survey_process_final_submission_with_transient($ref) {
    $transient_key = 'doran_survey_progress_' . $ref;
    $survey_data = get_transient($transient_key);

    // 1. Validasi: Pastikan data transient lengkap
    if ($survey_data === false || empty($survey_data['customer_data']) || !isset($survey_data['answers']) || !is_array($survey_data['answers'])) {
        error_log("Doran Survey FINAL GAGAL: Data transient tidak lengkap atau tidak ditemukan untuk REF: " . $ref);
        // Mungkin redirect ke halaman error atau kembali ke awal
        wp_redirect(home_url('/doran-survey/?ref=' . $ref . '&survey_error=transient'));
        exit;
    }

    // 2. Ambil data dari transient dengan benar
    $customer_data = $survey_data['customer_data'];
    $answers_data = $survey_data['answers'];
    $media = $survey_data['media_id'] ?? 0;
    $media_key = ($media == 1) ? 'offline' : (($media == 2) ? 'online' : 'marketplace');
    $all_questions = get_doran_survey_questions()[$media_key] ?? [];
    
    // 3. Format data untuk disimpan ke CPT dan dikirim ke API
    $formatted_data_cpt = [];
    $formatted_data_api = [];

    if (!empty($all_questions) && !empty($answers_data)) {
        foreach ($all_questions as $q_num => $q_details) {
            // Cek apakah ada jawaban untuk pertanyaan ini
            if (isset($answers_data[$q_num]) && !empty($answers_data[$q_num])) {
                $answer_text = $answers_data[$q_num];
                
                // Simpan untuk CPT
                $formatted_data_cpt[] = [
                    'urutan' => $q_num,
                    'jenis'  => $q_details['jenis'],
                    'soal'   => $q_details['soal'],
                    'jawab'  => $answer_text,
                    'opsi'   => $q_details['opsi'] ?? []
                ];

                // Siapkan untuk API
                $formatted_data_api[] = [
                    'urutan' => $q_num,
                    'jenis'  => $q_details['jenis'],
                    'soal'   => $q_details['soal'],
                    'jawab'  => $answer_text,
                    'opsi'   => $q_details['opsi'] ?? []
                ];
            }
        }
    }
    
    // Jika tidak ada jawaban sama sekali, jangan lanjutkan
    if (empty($formatted_data_cpt)) {
        error_log("Doran Survey FINAL GAGAL: Tidak ada jawaban valid yang bisa diproses untuk REF: " . $ref);
        delete_transient($transient_key);
        return;
    }

    // 4. Buat CPT baru untuk menyimpan hasil survei
    $post_title = 'Survei dari ' . ($customer_data['nama'] ?? 'Anonim') . ' (' . $ref . ') pada ' . current_time('d-m-Y H:i');
    $post_id = wp_insert_post([
        'post_title'  => $post_title,
        'post_status' => 'publish',
        'post_type'   => 'survey_submission',
    ], true); 
    

    if (is_wp_error($post_id)) {
        error_log("[DORAN SURVEY FINAL] Gagal wp_insert_post() untuk REF {$ref}: " . $post_id->get_error_message());
    } else {
        // Jika CPT berhasil dibuat, simpan semua meta data
        update_post_meta($post_id, 'customer_nama',     $customer_data['nama'] ?? '');
        update_post_meta($post_id, 'customer_telp',     $customer_data['telp'] ?? '');
        update_post_meta($post_id, 'customer_email',    $customer_data['email'] ?? '');
        update_post_meta($post_id, 'customer_domisili', $customer_data['domisili'] ?? '');
        update_post_meta($post_id, 'survey_media',      $media);
        update_post_meta($post_id, 'survey_answers',    $formatted_data_cpt); // Simpan jawaban yang sudah diformat
        update_post_meta($post_id, 'transaction_ref',   $ref);
        error_log("[DORAN SURVEY FINAL] Data survei untuk REF {$ref} berhasil disimpan ke CPT ID: {$post_id}");
    }
    
    // 5. Siapkan dan kirim data ke API eksternal
    $body_payload = [
        'media' => $media,
        'data'  => $formatted_data_api,
    ];

    $base = md5(date('Y-m-d') . '_ord_' . $ref);
    // $api_url = sprintf('https://kasir.doran.id/api/product/submit?base=%s&ref=%s', $base, $ref);
    $api_url = sprintf('https://kasir.doran.id/api/product/submit?base=f7d520bcc0907a0e39069cc979c101e3&ref=NTczNDIxMQ==');
    
    $api_response = wp_remote_post($api_url, [
        'method'      => 'POST',
        'headers'     => ['Content-Type' => 'application/json; charset=utf-8'],
        'body'        => json_encode($body_payload),
        'data_format' => 'body',
    ]);

    if(is_wp_error($api_response)) {
        error_log('[DORAN SURVEY FINAL] Gagal kirim ke API eksternal (WP_Error) untuk REF ' . $ref . ': ' . $api_response->get_error_message());
    } else {
        error_log('[DORAN SURVEY FINAL] Respons dari API eksternal untuk REF ' . $ref . '. Kode: ' . wp_remote_retrieve_response_code($api_response));
    }
    
    // 6. Hapus transient setelah semua proses selesai
    delete_transient($transient_key);
}
}

// ... (Sisa fungsi-fungsi lain dibungkus dalam if !function_exists juga) ...