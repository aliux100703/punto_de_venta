import './bootstrap';
import Alpine from 'alpinejs';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
window.Alpine = Alpine;
Alpine.start();

document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        const search = document.getElementById('globalSearch');
        const target = search?.offsetParent ? search : document.getElementById('productSearch');
        if (target) { event.preventDefault(); target.focus(); target.select(); }
    }
    if (event.key === 'F2') {
        const posLink = document.querySelector('.sidebar-link[href$="/pos"]');
        if (!posLink) return;
        event.preventDefault();
        const productSearch = document.getElementById('productSearch');
        if (productSearch) productSearch.focus();
        else window.location.assign(posLink.href);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab' || !document.body.classList.contains('menu-is-open')) return;
    const links = [...document.querySelectorAll('#app-sidebar a[href], #app-sidebar button')].filter(element => element.offsetParent !== null);
    const first = links[0];
    const last = links.at(-1);
    if (!first) return;
    if (event.shiftKey && (document.activeElement === first || !document.getElementById('app-sidebar').contains(document.activeElement))) {
        event.preventDefault(); last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || !document.getElementById('app-sidebar').contains(document.activeElement))) {
        event.preventDefault(); first.focus();
    }
});
