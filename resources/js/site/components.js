/**
 * The public site's Alpine components.
 *
 * Livewire ships the CSP build of Alpine, which evaluates attribute
 * expressions with a parser instead of eval: calls, assignments, comparisons
 * and literals, but no function bodies, no arrow functions and no reaching for
 * window or document. Anything that needs more than that lives here, and the
 * markup only names the component and calls its methods.
 */
export default function registerSiteComponents(Alpine) {
    Alpine.data('stickyHeader', () => ({
        scrolled: false,

        onScroll() {
            this.scrolled = window.scrollY > 20;
        },
    }));

    Alpine.data('mobileMenu', () => ({
        open: false,

        init() {
            this.$watch('open', (open) => document.body.classList.toggle('overflow-hidden', open));
        },

        destroy() {
            document.body.classList.remove('overflow-hidden');
        },
    }));

    // Steps live in Alpine: walking between three questions is not worth a
    // network round trip each way, so only the finished answer reaches the
    // server. Choices are pushed onto the Livewire component as they are made.
    Alpine.data('contactWizard', () => ({
        open: false,
        step: 1,
        role: '',
        unitType: '',
        done: false,

        asksUnitType() {
            return this.role === 'client';
        },

        get lastStep() {
            return this.asksUnitType() ? 3 : 2;
        },

        get progressWidth() {
            return `width: ${((this.step - 1) / (this.lastStep - 1)) * 100}%`;
        },

        chooseRole(role) {
            this.role = role;
            this.$wire.role = role;
            this.unitType = '';
            this.$wire.unitType = '';
            this.step = 2;
        },

        chooseUnitType(type) {
            this.unitType = type;
            this.$wire.unitType = type;
            this.step = 3;
        },

        back() {
            if (this.step > 1) {
                this.step--;
            }
        },

        show() {
            this.done = false;
            this.step = 1;
            this.role = '';
            this.unitType = '';
            this.open = true;
        },

        close() {
            this.open = false;
        },
    }));

    // Appears after a few seconds, then keeps opening its label for a moment
    // to catch the eye. One timer is ever pending, so leaving the page (or
    // wire:navigate swapping it) stops the loop instead of stacking another.
    Alpine.data('whatsappBubble', () => ({
        visible: false,
        open: false,
        timer: null,

        init() {
            this.later(5000, () => {
                this.visible = true;
                this.later(1200, () => this.cycle());
            });
        },

        cycle() {
            this.open = true;
            this.later(3500, () => {
                this.open = false;
                this.later(2500, () => this.cycle());
            });
        },

        later(delay, callback) {
            this.timer = setTimeout(callback, delay);
        },

        destroy() {
            clearTimeout(this.timer);
        },
    }));

    // The home page's project strip scrolls by dragging with a mouse. A drag
    // must not count as a click on the card it ends over.
    Alpine.data('projectCarousel', () => ({
        dragging: false,
        startX: 0,
        startScroll: 0,
        moved: false,

        onMouseDown(event) {
            this.dragging = true;
            this.moved = false;
            this.startX = event.pageX - this.$refs.track.offsetLeft;
            this.startScroll = this.$refs.track.scrollLeft;
            this.$refs.track.style.cursor = 'grabbing';
            this.$refs.track.style.userSelect = 'none';
        },

        onMouseMove(event) {
            if (! this.dragging) {
                return;
            }

            const walk = event.pageX - this.$refs.track.offsetLeft - this.startX;

            if (Math.abs(walk) > 5) {
                this.moved = true;
            }

            this.$refs.track.scrollLeft = this.startScroll - walk;
        },

        onMouseUp() {
            this.dragging = false;
            this.$refs.track.style.cursor = 'grab';
            this.$refs.track.style.userSelect = '';
            setTimeout(() => {
                this.moved = false;
            }, 100);
        },

        onMouseLeave() {
            this.dragging = false;
            this.$refs.track.style.cursor = 'grab';
        },

        openProject(url) {
            if (! this.moved) {
                window.navigateTo(url);
            }
        },
    }));

    // The "sold" badge that follows the pointer over a sold project's card.
    Alpine.data('cursorBadge', () => ({
        showBadge: false,
        x: 0,
        y: 0,

        follow(event) {
            this.x = event.clientX;
            this.y = event.clientY;
        },

        get position() {
            return `left: ${this.x}px; top: ${this.y}px; transition: transform 0.05s linear;`;
        },
    }));

    // A unit's photos: the inline slider, and the lightbox that opens on the
    // photo that was clicked. Both Swipers are built by the page's own script.
    Alpine.data('unitGallery', () => ({
        showModal: false,

        openLightbox(index) {
            this.showModal = true;
            this.$nextTick(() => {
                window.lightboxSwiper?.slideTo(index, 0);
                window.lightboxSwiper?.update();
            });
        },
    }));

    // A YouTube video that loads only once its thumbnail is clicked. The CSP
    // build of Alpine refuses to evaluate anything on an iframe, so the
    // address (data-video on the root) is set from here and a wrapper does
    // the hiding.
    Alpine.data('youtubePlayer', () => ({
        playing: false,

        play() {
            this.$refs.frame.src = this.$root.dataset.video;
            this.playing = true;
        },
    }));

    // "Saved." beside a settings form, shown for two seconds after the
    // component dispatches the event its data-event names.
    Alpine.data('actionMessage', () => ({
        shown: false,
        timeout: null,

        init() {
            this.$wire.on(this.$root.dataset.event, () => {
                clearTimeout(this.timeout);
                this.shown = true;
                this.timeout = setTimeout(() => {
                    this.shown = false;
                }, 2000);
            });
        },
    }));
}
