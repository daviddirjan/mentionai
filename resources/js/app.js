const root = document.documentElement;

function applyTheme(theme) {
    root.dataset.theme = theme;
}

window.mentionaiToggleTheme = () => {
    const next = root.dataset.theme === 'light' ? 'dark' : 'light';
    applyTheme(next);
    try {
        localStorage.setItem('theme', next);
    } catch (e) {}
};

// Follow the OS setting until the visitor makes an explicit choice.
window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', (event) => {
    try {
        if (localStorage.getItem('theme')) {
            return;
        }
    } catch (e) {}
    applyTheme(event.matches ? 'light' : 'dark');
});
