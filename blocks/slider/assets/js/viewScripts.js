/**
 * Scripts running the front-end.
 *
 * @package ST_WP_Starter
 */

document.addEventListener( 'DOMContentLoaded', function () {
    document.querySelectorAll( '.slider-section-splide' ).forEach( ( element ) => {
        const splide = new Splide( element, {
            type: 'loop',
            perPage: 1,
            perMove: 1,
            focus: 'center',
            rewind: false,
            pagination: false,
            arrows: true,
        } );
        splide.mount();
    } );
} );
