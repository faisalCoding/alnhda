import registerSiteComponents from './site/components';

window.navigateTo = function(url){
    document.body.style.opacity = 0;
    setTimeout(()=>location.href = url, 50);
}

window.addEventListener('pageshow', () => {
    document.body.style.opacity = '';
});

// Links marked data-fade-link fade the page out before leaving it. A click
// with a modifier (a new tab, a new window, a download) is the browser's.
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-fade-link]');

    if (! link || event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    window.navigateTo(link.href);
});

// Livewire starts Alpine on DOMContentLoaded, after this module has run.
document.addEventListener('alpine:init', () => registerSiteComponents(window.Alpine));
