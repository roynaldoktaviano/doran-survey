document.addEventListener('DOMContentLoaded', function () {
    const mediaSelector = document.getElementById('media-selector');
    const allQuestionSets = document.querySelectorAll('.question-set');

    if (!mediaSelector) {
        return; // Keluar jika dropdown tidak ditemukan
    }

    mediaSelector.addEventListener('change', function () {
        const selectedValue = this.value;

        // 1. Sembunyikan semua set pertanyaan dan nonaktifkan semua inputnya
        allQuestionSets.forEach(function (set) {
            set.style.display = 'none';
            const inputs = set.querySelectorAll('.survey-input');
            inputs.forEach(function (input) {
                input.disabled = true;
                input.required = false; // Hapus atribut required jika ada
            });
        });

        // 2. Jika ada media yang dipilih, tampilkan set yang relevan dan aktifkan inputnya
        if (selectedValue) {
            const targetSet = document.getElementById('questions-for-' + selectedValue);
            if (targetSet) {
                targetSet.style.display = 'block';
                const targetInputs = targetSet.querySelectorAll('.survey-input');
                targetInputs.forEach(function (input) {
                    input.disabled = false;
                    // Jika Anda ingin field pertama wajib diisi, tambahkan required
                    if (input.matches('select')) { // Contoh: hanya select yang wajib
                         input.required = true;
                    }
                });
            }
        }
    });
});