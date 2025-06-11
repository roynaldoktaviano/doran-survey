jQuery(document).ready(function($) {
    var overlay = $('#survey-modal-overlay');
    var modal = $('#survey-modal-wrap');
    var content = $('#survey-modal-content');
    var closeButton = $('#survey-modal-close');

    // Ketika tombol "Lihat Jawaban" diklik
    $('.wrap').on('click', '.view-survey-details', function(e) {
        e.preventDefault();

        var post_id = $(this).data('postid');

        // Tampilkan modal dan overlay dengan status loading
        overlay.show();
        modal.show();
        content.html('<p class="loading-text">Memuat data...</p>');

        // Lakukan request AJAX
        $.ajax({
            url: doran_survey_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_survey_details', // Nama action yang kita daftarkan di PHP
                post_id: post_id,
                nonce: doran_survey_ajax.nonce // Kirim nonce untuk verifikasi
            },
            success: function(response) {
                if (response.success) {
                    // Masukkan HTML jawaban ke dalam modal
                    content.html(response.data);
                } else {
                    content.html('<p>Gagal mengambil data. Silakan coba lagi.</p>');
                }
            },
            error: function() {
                content.html('<p>Terjadi error pada koneksi. Silakan coba lagi.</p>');
            }
        });
    });

    // Fungsi untuk menutup modal
    function closeModal() {
        overlay.hide();
        modal.hide();
    }

    // Event listener untuk tombol close dan overlay
    closeButton.on('click', closeModal);
    overlay.on('click', closeModal);
});