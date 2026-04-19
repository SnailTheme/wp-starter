/**
 * AnimateCSS element in-view detection
 *  - You can modify what you import per-project
 *  - You can activate animations site-wide from Theme Options
 *  - Or enqueue per template by calling:
 *  - wp_enqueue_script('plugins.animatecss.animate');
 *
 * @package ST_WP_Starter
 */

window.addEventListener( 'DOMContentLoaded', function () {

    /**
     * Detect elements when they are within view
     * src: https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
     */
    const initEnterView = () => {
        const observer = new IntersectionObserver( ( entries ) => {
            entries.forEach( ( entry ) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add( 'entered' )
                    entry.target.querySelectorAll( '.animate__animated.opacity-0' ).forEach( ( el ) => {
                        el.classList.remove( 'opacity-0' )
                        el.classList.add( el.dataset.animateClass )
                    } )
                }
            } )
        }, {threshold: 0, rootMargin: '0px 0px -120px 0px'} )

        document.querySelectorAll( '.enter-view' ).forEach( ( el ) => {
            observer.observe( el )
        } )
    }

    initEnterView();
} );
