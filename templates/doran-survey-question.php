<?php
/**
 * Template untuk menampilkan satu pertanyaan survei.
 * Versi final yang sudah diperbaiki.
 */

if (!defined('ABSPATH')) exit;

// Ambil data yang sudah disiapkan oleh fungsi shortcode melalui variabel global
global $doran_survey_data;

// Logika untuk mendapatkan pertanyaan dari data yang dioper
$question_number = $doran_survey_data['question_number'] ?? 1;
$media_id = $doran_survey_data['media_id'] ?? 0;
$ref = $doran_survey_data['ref'] ?? '';
$media_key = '';
if ($media_id == 1) $media_key = 'offline';
elseif ($media_id == 2) $media_key = 'online';
elseif ($media_id == 3) $media_key = 'marketplace';

$all_questions = get_doran_survey_questions();
$question_set = !empty($media_key) && isset($all_questions[$media_key]) ? $all_questions[$media_key] : [];
$current_question = !empty($question_set) && isset($question_set[$question_number]) ? $question_set[$question_number] : null;
$total_questions = count($question_set);

// Ambil jawaban tersimpan dari sesi untuk pre-fill (jika pengguna kembali ke pertanyaan sebelumnya)
if (!session_id()) session_start();
$saved_answer = $_SESSION['doran_survey_answers_final'][$question_number] ?? '';

// get_header();
?>
<div id="doran-survey-container-question">
    <?php if ($current_question) : // Kondisi ini akan true jika pertanyaan ditemukan ?>
    <div class="survey-progress">
        <p>Pertanyaan <span class="prog-num"><?php echo esc_html($question_number); ?></span> dari
            <?php echo esc_html($total_questions); ?></p>
        <div class="progress-bar-container">
            <div class="progress-bar" style="width: <?php echo ($question_number / $total_questions) * 100; ?>%;"></div>
        </div>
    </div>

    <form id="doran-survey-form-question" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="submit_doran_survey_step">
        <input type="hidden" name="survey_step" value="question">
        <input type="hidden" name="survey_ref" value="<?php echo esc_attr($ref); ?>">
        <input type="hidden" name="question_number" value="<?php echo esc_attr($question_number); ?>">
        <input type="hidden" name="survey_media" value="<?php echo esc_attr($media_id); ?>">
        <?php wp_nonce_field('doran_survey_step_action', 'doran_survey_nonce'); ?>

        <div class="form-group">
            <label class="question-title"><?php echo esc_html($current_question['soal']); ?></label>

            <div class="answers-container">
                <?php if ($current_question['jenis'] == 1) : // Tipe Pilihan Ganda / Rating ?>

                <?php $display_type = $current_question['tipe_tampilan'] ?? 'teks'; ?>
                <div class="rating-scale-group <?php echo 'display-type-' . esc_attr($display_type); ?>">

                    <?php foreach ($current_question['opsi'] as $value => $option_data) : ?>
                    <label class="rating-label">
                        <input type="radio" name="survey_answer" value="<?php echo esc_attr($value); ?>"
                            <?php checked($saved_answer, $value); ?> required>

                        <?php if ($display_type === 'emoji' && is_array($option_data)) : ?>
                        <div class="emoji-quest">
                            <span class="rating-emoji"><img src="<?php echo esc_url($option_data['url']); ?>"
                                    alt="<?php echo esc_attr($option_data['label']); ?>" width="32" height="32"></span>
                            <span class="rating-text"><?php echo esc_html($option_data['label']); ?></span>
                        </div>
                        <?php else : // Default ke tampilan 'teks' ?>
                        <span class="rating-circle"><?php echo esc_html($value); ?></span>
                        <span class="rating-text"><?php echo esc_html($option_data); ?></span>
                        <?php endif; ?>

                    </label>
                    <?php endforeach; ?>
                </div>

                <?php elseif ($current_question['jenis'] == 2) : // Tipe Isian ?>
                <textarea name="survey_answer" class="survey-input" rows="5" required
                    placeholder="Tulis masukan Anda di sini..."><?php echo esc_textarea($saved_answer); ?></textarea>
                <?php endif; ?>
            </div>
        </div>

        <div class="survey-navigation-buttons">
            <?php if ($question_number > 1) : ?>
            <?php $prev_url = add_query_arg(['ref' => $ref, 'step' => 'question', 'q' => ($question_number - 1), 'media' => $media_id], home_url('/doran-survey/')); ?>
            <a href="<?php echo esc_url($prev_url); ?>" class="button-prev-question">Kembali</a>
            <?php endif; ?>
            <button type="submit" class="button-next-question">
                <?php echo ($question_number == $total_questions) ? 'Kirim Survey' : 'Selanjutnya'; ?>
            </button>
        </div>
    </form>
    <?php else: ?>
    <p>Pertanyaan tidak ditemukan. Pastikan Anda telah memilih media pembelian di halaman awal.</p>
    <?php endif; ?>
</div>
<?php
// get_footer();
?>