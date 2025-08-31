import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse'

window.Alpine = Alpine;

Alpine.plugin(collapse)

Alpine.store('authModal', {
    open: false,
    show() { this.open = true },
    hide() { this.open = false },
})
Alpine.start();
