/**
 * Scripts running the Page Editor.
 *
 * @package ST_WP_Starter
 */

(function () {
    /**
     * Read per-block Splide options printed by the render template.
     *
     * The editor receives rendered block markup, so it should use the same
     * per-instance settings as the front end instead of duplicating option
     * logic here.
     *
     * @param {HTMLElement} element Splide root element.
     *
     * @return {Object}
     */
    const getSplideOptions = ( element ) => {
        if ( ! element.dataset.splide ) {
            return {};
        }

        try {
            return JSON.parse( element.dataset.splide );
        } catch ( error ) {
            return {};
        }
    };

    const initializeSplides = () => {
        document.querySelectorAll( '.slider-section-splide' ).forEach( ( element ) => {
            if ( ! element.classList.contains( 'splide-initialized' ) ) {
                const splide = new Splide( element, getSplideOptions( element ) );
                splide.mount();
                element.classList.add( 'splide-initialized' ); // Prevent re-initialization
            }
        } );
    };

    const observer = new MutationObserver( () => {
        initializeSplides(); // Re-initialize Splide when the DOM changes.
    } );

    // Wait for the editor canvas to be ready.
    wp.domReady( () => {
            const editorCanvas = document.querySelector( '.block-editor' );
            if ( editorCanvas ) {
                initializeSplides(); // Initial setup
                observer.observe( editorCanvas,
                    {childList: true, subtree: true,} );
            }
        }
    );
})();
