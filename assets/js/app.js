(function () {
    document.addEventListener('click', function (event) {
        var target = event.target.closest('a, button');
        if (!target || target.getAttribute('aria-disabled') === 'true') return;
        target.classList.add('afe-click-feedback');
        window.setTimeout(function () { target.classList.remove('afe-click-feedback'); }, 180);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = 'true';
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
            button.dataset.originalText = button.textContent;
            button.disabled = true;
            button.dataset.appLoading = 'true';
            if (button.tagName === 'BUTTON') button.textContent = 'Memproses...';
        });
    });
}());