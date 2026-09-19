document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
        document.querySelectorAll('[data-nav-panel]').forEach(function (panel) {
            panel.classList.toggle('open');
        });
    });
});
