/**
 * File admin.js
 *
 * Core admin behavior shared by starter themes.
 * This file is owned by /core/ behavior and should stay generic across
 * generated themes. Project-specific admin scripts belong in ../admin.js.
 *
 * @package ST_WP_Core
 */

/**
 * Handles the dismissal of the plugin notice in the WordPress admin.
 * When the "Don't remind me" link is clicked, it triggers an AJAX request
 * to store the dismissal action for the current user and then hides the notice.
 *
 * @see wp_localize_script() - The st_wp_core_plugin_notice object containing:
 *      - ajax_url (string): The URL for making AJAX requests to WordPress admin.
 *      - nonce (string): A security nonce to validate the AJAX request.
 *
 * @var {object} st_wp_core_plugin_notice The localized object containing the AJAX URL and nonce.
 * @var {string} st_wp_core_plugin_notice.ajax_url The URL for making AJAX requests.
 * @var {string} st_wp_core_plugin_notice.nonce The nonce for security verification of the AJAX request.
 *
 * @package ST_WP_Core
 */
jQuery( document ).ready( function ( $ ) {
    // Only bind the dismiss action when PHP localized the AJAX settings.
    if (
        'undefined' === typeof st_wp_core_plugin_notice ||
        !st_wp_core_plugin_notice.ajax_url ||
        !st_wp_core_plugin_notice.nonce
    ) {
        return;
    }

    $( '.st-dismiss-plugin-notice' ).on( 'click', function ( e ) {
        e.preventDefault(); // Prevent the placeholder link from changing the page URL.

        // Persist dismissal per user; the PHP AJAX action stores user meta.
        $.post( st_wp_core_plugin_notice.ajax_url, {
            action: 'st_wp_core_dismiss_plugin_notice',
            nonce: st_wp_core_plugin_notice.nonce
        } );

        // Remove the notice immediately so the user gets instant feedback.
        $( this ).closest( '.st-plugin-notice' ).fadeOut();
    } );
} );

/** ---------------------------------------------------------------
 *  Image field for nav-menu items
 *  ---------------------------------------------------------------
 *  - Opens the WP media modal when the "Select/Upload" button
 *    ( .upload-menu-image ) is clicked.
 *  - Stores the chosen attachment ID in the hidden input
 *    ( .menu-item-image-id ).
 *  - Shows a thumb in the <span class="menu-image-preview">.
 * ----------------------------------------------------------------
 */
jQuery( function ( $ ) {
    // The media modal only exists when core/scripts.php calls wp_enqueue_media().
    if ( 'undefined' === typeof wp || !wp.media ) {
        return;
    }

    $( document ).on( 'click', '.upload-menu-image', function ( e ) {
        e.preventDefault();

        const $upload = $( this ); // The button clicked inside one menu item.
        const $field = $upload.closest( '.field-image' );
        const $input = $field.find( '.menu-item-image-id' );
        const $preview = $field.find( '.menu-image-preview' );
        const $remove = $field.find( '.remove-menu-image' );

        // Reuse one frame per upload button so repeated clicks keep state tidy.
        let frame = $upload.data( 'media-frame' );
        if ( !frame ) {
            frame = wp.media( {
                title: 'Select menu image',
                library: { type: 'image' },
                button: { text: 'Use this image' },
                multiple: false
            } );

            // Store the selected attachment ID and update the menu-item preview.
            frame.on( 'select', () => {
                const attachment = frame.state().get( 'selection' ).first().toJSON();
                const thumb = attachment.sizes?.thumbnail?.url || attachment.url;

                $input.val( attachment.id ).trigger( 'change' );
                $preview.html( `<img src="${ thumb }" alt="">` );
                $field.addClass( 'has-image' );
                $upload.text( 'Change' );
                $remove.show();
            } );

            $upload.data( 'media-frame', frame );
        }

        // Highlight the currently saved image when reopening the media modal.
        frame.off( 'open' ).on( 'open', () => {
            const selection = frame.state().get( 'selection' );
            const id = parseInt( $input.val(), 10 );

            selection.reset();

            if ( id ) {
                const attachment = wp.media.attachment( id );

                attachment.fetch();
                selection.add( attachment );
            }
        } );

        frame.open();
    } );

    $( document ).on( 'click', '.remove-menu-image', function ( e ) {
        e.preventDefault();

        const $field = $( this ).closest( '.field-image' );
        const $input = $field.find( '.menu-item-image-id' );
        const $preview = $field.find( '.menu-image-preview' );
        const $upload = $field.find( '.upload-menu-image' );

        // Clearing value and attribute avoids stale menu meta on the next save.
        $input.val( '' ).removeAttr( 'value' );
        $preview.empty();
        $field.removeClass( 'has-image' );

        $upload.text( 'Set image' );
        $( this ).hide();
    } );
} );
