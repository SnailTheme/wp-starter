/**
 * Initialize ACF v3 slider previews in their owning editor document.
 *
 * @package ST_WP_Starter
 */

(function () {
    const initializePreview = ( $preview ) => {
        const root = $preview && $preview[0];

        if ( ! root || ! root.querySelectorAll ) {
            return;
        }

        const ownerWindow = root.ownerDocument.defaultView;
        const SplideConstructor = ( ownerWindow && ownerWindow.Splide ) || window.Splide;

        if ( ! SplideConstructor ) {
            return;
        }

        root.querySelectorAll( '.slider-section-splide' ).forEach( ( element ) => {
            if (
                element.classList.contains( 'is-initialized' ) ||
                ! element.querySelector( '.splide__slide' )
            ) {
                return;
            }

            try {
                const options = element.dataset.splide ? JSON.parse( element.dataset.splide ) : {};
                new SplideConstructor( element, options ).mount();
                element.classList.add( 'splide-initialized' );
            } catch ( error ) {
                element.classList.add( 'splide-initialization-failed' );
                console.error( 'Unable to initialize the slider editor preview.', error );
            }
        } );
    };

    // ACF invokes this after each iframe preview render, including cached previews.
    window.acf.addAction( 'render_block_preview', initializePreview );
})();
