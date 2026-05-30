/**
 * Scripts running the Page Editor.
 *
 * @package ST_WP_Starter
 */

(function () {
    const observedDocuments = new WeakSet();

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

    /**
     * Return the Splide constructor available to a document.
     *
     * In the iframed editor, the block markup can live in a child document. If
     * WordPress loads this script in the parent editor shell, use the parent
     * Splide constructor as a fallback while still initializing iframe markup.
     *
     * @param {Document} ownerDocument Document that owns the slider element.
     *
     * @return {Function|null}
     */
    const getSplideConstructor = ( ownerDocument ) => {
        if ( ownerDocument.defaultView && ownerDocument.defaultView.Splide ) {
            return ownerDocument.defaultView.Splide;
        }

        if ( window.Splide ) {
            return window.Splide;
        }

        return null;
    };

    /**
     * Initialize all slider instances inside a document or element.
     *
     * @param {Document|Element} root Document or element to search inside.
     */
    const initializeSplides = ( root ) => {
        const ownerDocument = root.ownerDocument || root;
        const SplideConstructor = getSplideConstructor( ownerDocument );

        if ( ! SplideConstructor || ! root.querySelectorAll ) {
            return;
        }

        root.querySelectorAll( '.slider-section-splide' ).forEach( ( element ) => {
            if (
                element.classList.contains( 'splide-initialized' ) ||
                element.classList.contains( 'is-initialized' ) ||
                ! element.querySelector( '.splide__slide' )
            ) {
                return;
            }

            try {
                const splide = new SplideConstructor( element, getSplideOptions( element ) );
                splide.mount();
                element.classList.add( 'splide-initialized' ); // Prevent re-initialization.
            } catch ( error ) {
                element.classList.add( 'splide-initialization-failed' );
            }
        } );
    };

    /**
     * Observe same-origin editor iframe markup.
     *
     * @param {HTMLIFrameElement} iframe iframe element.
     */
    const observeIframe = ( iframe ) => {
        const observeIframeDocument = () => {
            try {
                if ( iframe.contentDocument ) {
                    observeDocument( iframe.contentDocument );
                }
            } catch ( error ) {
                // Cross-origin or unavailable iframe documents are ignored.
            }
        };

        observeIframeDocument();
        iframe.addEventListener( 'load', observeIframeDocument );
    };

    /**
     * Observe a document for rendered or re-rendered block previews.
     *
     * @param {Document} currentDocument Document to observe.
     */
    const observeDocument = ( currentDocument ) => {
        if (
            ! currentDocument ||
            ! currentDocument.body ||
            observedDocuments.has( currentDocument )
        ) {
            return;
        }

        observedDocuments.add( currentDocument );
        initializeSplides( currentDocument );

        const observer = new MutationObserver( ( mutations ) => {
            initializeSplides( currentDocument );

            mutations.forEach( ( mutation ) => {
                mutation.addedNodes.forEach( ( node ) => {
                    if ( node.nodeName === 'IFRAME' ) {
                        observeIframe( node );
                    }

                    if ( node.querySelectorAll ) {
                        node.querySelectorAll( 'iframe' ).forEach( observeIframe );
                    }
                } );
            } );
        } );

        observer.observe( currentDocument.body, {
            childList: true,
            subtree: true,
        } );

        currentDocument.querySelectorAll( 'iframe' ).forEach( observeIframe );
    };

    const start = () => {
        observeDocument( document );
    };

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', start );
    } else {
        start();
    }
})();
