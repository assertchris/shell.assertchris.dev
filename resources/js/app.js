import './bootstrap';

(function () {
    var html = document.documentElement;
    var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    function applyScheme(scheme) {
        if (scheme === 'dark' || (scheme === 'system' && mediaQuery.matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        document.querySelectorAll('[data-scheme]').forEach(function (button) {
            if (button.getAttribute('data-scheme') === scheme) {
                button.classList.add('bg-foreground/10');
            } else {
                button.classList.remove('bg-foreground/10');
            }
        });
    }

    var stored = localStorage.getItem('color-scheme') || 'system';
    applyScheme(stored);

    document.querySelectorAll('[data-scheme]').forEach(function (button) {
        button.addEventListener('click', function () {
            var chosen = button.getAttribute('data-scheme');
            localStorage.setItem('color-scheme', chosen);
            applyScheme(chosen);
        });
    });

    mediaQuery.addEventListener('change', function () {
        var current = localStorage.getItem('color-scheme') || 'system';
        if (current === 'system') {
            applyScheme('system');
        }
    });
})();
