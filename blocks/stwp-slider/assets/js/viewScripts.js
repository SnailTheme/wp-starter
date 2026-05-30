/**
 * Scripts running the front-end.
 *
 * @package ST_WP_Starter
 */

document.addEventListener( 'DOMContentLoaded', function () {
    /**
     * Read per-block Splide options printed by the render template.
     *
     * Each block instance can have its own settings, such as horizontal or
     * vertical direction, so the render file owns the options and JavaScript
     * only boots the slider.
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

    document.querySelectorAll( '.slider-section-splide' ).forEach( ( element ) => {
        if (
            typeof Splide === 'undefined' ||
            element.classList.contains( 'splide-initialized' ) ||
            element.classList.contains( 'is-initialized' ) ||
            ! element.querySelector( '.splide__slide' )
        ) {
            return;
        }

        try {
            const splide = new Splide( element, getSplideOptions( element ) );
            splide.mount();
            element.classList.add( 'splide-initialized' );
        } catch ( error ) {
            element.classList.add( 'splide-initialization-failed' );
        }
    } );
} );
