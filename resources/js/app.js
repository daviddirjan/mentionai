const root = document.documentElement;

// Light is the default; dark only after an explicit choice.
window.mentionaiToggleTheme = () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    try {
        localStorage.setItem('theme', next);
    } catch (e) {}
};
