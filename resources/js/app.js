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

    var stored;
    try {
        stored = localStorage.getItem('color-scheme') || 'system';
    } catch (e) {
        stored = 'system';
    }

    applyScheme(stored);

    mediaQuery.addEventListener('change', function () {
        try {
            var current = localStorage.getItem('color-scheme') || 'system';
            if (current === 'system') {
                applyScheme('system');
            }
        } catch (e) {}
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-scheme]').forEach(function (button) {
            button.addEventListener('click', function () {
                var chosen = button.getAttribute('data-scheme');
                try {
                    localStorage.setItem('color-scheme', chosen);
                } catch (e) {}
                applyScheme(chosen);
            });
        });
    });
})();
