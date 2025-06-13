<?php
if (!defined('ABSPATH')) exit;

$ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
$question_number = isset($_GET['q']) ? intval($_GET['q']) : 1;

$survey_progress = get_transient('doran_survey_progress_' . $ref);
if ($survey_progress === false) {
    echo '<p>Sesi survei tidak ditemukan atau telah berakhir. Silakan mulai dari awal.</p>';
    return; // Hentikan jika tidak ada data transient
}

$media_id = $survey_progress['media_id'] ?? 0;
$media_key = $media_id == 1 ? 'offline' : ($media_id == 2 ? 'online' : 'marketplace');
$all_questions = get_doran_survey_questions();
$question_set = !empty($media_key) ? ($all_questions[$media_key] ?? []) : [];
$current_question = !empty($question_set) && isset($question_set[$question_number]) ? $question_set[$question_number] : null;
$total_questions = count($question_set);
$saved_answers = $survey_progress['answers'] ?? [];
$saved_answer = $saved_answers[$question_number] ?? '';
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

        <?php if (!empty($saved_answers)) : ?>
        <?php foreach ($saved_answers as $q_num => $q_ans) : ?>
        <input type="hidden" name="previous_answers[<?php echo esc_attr($q_num); ?>]"
            value="<?php echo esc_attr($q_ans); ?>">
        <?php endforeach; ?>
        <?php endif; ?>

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