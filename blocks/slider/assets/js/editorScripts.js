/**
 * Scripts running the Page Editor.
 *
 * @package ST_WP_Starter
 */

(function () {
    const initializeSplides = () => {
        document.querySelectorAll( '.slider-section-splide' ).forEach( ( element ) => {
            if (!element.classList.contains( 'splide-initialized' )) {
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
                element.classList.add( 'splide-initialized' ); // Prevent re-initialization
            }
        } );
    };

    const observer = new MutationObserver( () => {
        initializeSplides(); // Re-initialize Splide when the DOM changes.
    } );

    // Wait for the editor canvas to be ready
    wp.domReady( () => {
            const editorCanvas = document.querySelector( '.block-editor' );
            console.log( "Editor canvas detected: ", editorCanvas );
            if (editorCanvas) {
                initializeSplides(); // Initial setup
                observer.observe( editorCanvas,
                    {childList: true, subtree: true,} );
            }
        }
    );
})();
