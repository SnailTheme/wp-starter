/**
 * Toolkit mega-menu behavior.
 *
 * One WordPress menu tree powers desktop disclosure panels and recursive
 * mobile drawers. Responsive mode follows a CSS sentinel compiled from the
 * component's Sass breakpoint, keeping presentation and behavior synchronized.
 *
 * @package ST_WP_Starter
 */

( function () {
	const roots = document.querySelectorAll( '[data-st-toolkit-mega-menu]' );
	const focusableSelector = [
		'a[href]',
		'button:not([disabled])',
		'input:not([disabled])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])',
	].join( ',' );

	if ( ! roots.length ) {
		return;
	}

	/**
	 * Return visible focusable descendants.
	 *
	 * @param {Element} container Element to inspect.
	 * @return {HTMLElement[]} Focusable elements currently visible to the user.
	 */
	const visibleFocusable = ( container ) => [ ...container.querySelectorAll( focusableSelector ) ]
		.filter( ( element ) => ! element.closest( '[inert]' )
			&& ! element.hidden
			&& null !== element.offsetParent
			&& 'hidden' !== window.getComputedStyle( element ).visibility );

	/**
	 * Build a mobile drawer header inside one submenu list.
	 *
	 * @param {string} title Parent item title.
	 * @return {HTMLLIElement} Drawer navigation row.
	 */
	const createDrawerNavigation = ( title ) => {
		const row = document.createElement( 'li' );
		const button = document.createElement( 'button' );
		const label = document.createElement( 'span' );

		row.className = 'st-toolkit-mega-menu__drawer-navigation';
		button.className = 'st-toolkit-mega-menu__back-button';
		button.type = 'button';
		button.innerHTML = '<svg class="st-toolkit-mega-menu__interface-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg><span>Back</span>';
		label.className = 'st-toolkit-mega-menu__drawer-navigation-title';
		label.textContent = title;
		row.append( button, label );

		return row;
	};

	roots.forEach( ( root, rootIndex ) => {
		if ( root.classList.contains( 'st-toolkit-mega-menu--ready' ) ) {
			return;
		}

		const navigation = root.closest( '.main-navigation' );
		const menuToggle = navigation ? navigation.querySelector( ':scope > .menu-toggle' ) : null;
		const drawer = root.querySelector( '.st-toolkit-mega-menu__drawer' );
		const drawerClose = root.querySelector( '.st-toolkit-mega-menu__drawer-close' );
		const backdrop = root.querySelector( '[data-st-toolkit-mega-menu-close]' );
		const menuList = root.querySelector( '.st-toolkit-mega-menu__list' );
		const breakpoint = root.querySelector( '.st-toolkit-mega-menu__breakpoint' );
		const searchToggle = root.querySelector( '.st-toolkit-mega-menu__search-toggle' );
		const searchForm = root.querySelector( '.st-toolkit-mega-menu__search' );
		const searchInput = root.querySelector( '.st-toolkit-mega-menu__search-input' );
		const searchClose = root.querySelector( '.st-toolkit-mega-menu__search-close' );

		if ( ! navigation || ! menuToggle || ! drawer || ! menuList || ! breakpoint ) {
			return;
		}

		const topRecords = [];
		const panelRecords = [];
		const panelStack = [];
		let desktop = false;
		let drawerOpen = false;
		let searchOpen = false;
		let lastMode = null;
		let hoverOpenTimer = 0;
		let hoverCloseTimer = 0;
		let resizeTimer = 0;

		const drawerId = drawer.id || `st-toolkit-mega-menu-drawer-${ rootIndex + 1 }`;
		drawer.id = drawerId;
		menuToggle.setAttribute( 'aria-controls', drawerId );
		menuToggle.setAttribute( 'aria-expanded', 'false' );
		drawer.inert = true;

		if ( searchForm ) {
			searchForm.hidden = true;
		}

		root.querySelectorAll( '.menu-item-has-children' ).forEach( ( item, itemIndex ) => {
			const submenu = item.querySelector( ':scope > .st-toolkit-mega-menu__sub-menu' );
			const topTrigger = item.querySelector( ':scope > .st-toolkit-mega-menu__trigger' );
			const drawerTrigger = item.querySelector( ':scope > .st-toolkit-mega-menu__drawer-trigger' );
			const trigger = topTrigger || drawerTrigger;

			if ( ! submenu || ! trigger ) {
				return;
			}

			const labelNode = trigger.querySelector( '.st-toolkit-mega-menu__trigger-label' );
			const label = labelNode ? labelNode.textContent.trim() : trigger.textContent.trim();
			const submenuId = submenu.id || `st-toolkit-mega-menu-${ rootIndex + 1 }-submenu-${ itemIndex + 1 }`;
			const drawerNavigation = createDrawerNavigation( label );
			const backButton = drawerNavigation.querySelector( '.st-toolkit-mega-menu__back-button' );

			submenu.id = submenuId;
			trigger.setAttribute( 'aria-controls', submenuId );
			trigger.setAttribute( 'aria-expanded', 'false' );
			submenu.setAttribute( 'aria-hidden', 'true' );
			submenu.prepend( drawerNavigation );

			const record = {
				item,
				submenu,
				trigger,
				backButton,
				isTop: Boolean( topTrigger ),
			};

			panelRecords.push( record );

			if ( record.isTop ) {
				topRecords.push( record );
			}

			backButton.addEventListener( 'click', () => {
				if ( ! desktop ) {
					closeCurrentPanel( true );
				}
			} );

			trigger.addEventListener( 'click', () => {
				if ( desktop ) {
					if ( record.isTop ) {
						toggleDesktopPanel( record );
					}
					return;
				}

				openMobilePanel( record );
			} );

			if ( record.isTop ) {
				item.addEventListener( 'pointerenter', ( event ) => {
					if ( ! desktop || 'touch' === event.pointerType ) {
						return;
					}

					window.clearTimeout( hoverCloseTimer );
					hoverOpenTimer = window.setTimeout( () => openDesktopPanel( record ), 90 );
				} );

				item.addEventListener( 'pointerleave', ( event ) => {
					if ( ! desktop || 'touch' === event.pointerType ) {
						return;
					}

					window.clearTimeout( hoverOpenTimer );
					hoverCloseTimer = window.setTimeout( () => {
						if ( ! item.contains( document.activeElement ) ) {
							closeDesktopPanel( record );
						}
					}, 180 );
				} );

				item.addEventListener( 'focusout', ( event ) => {
					if ( desktop && ! item.contains( event.relatedTarget ) ) {
						window.setTimeout( () => {
							if ( ! item.contains( document.activeElement ) ) {
								closeDesktopPanel( record );
								}
						}, 0 );
					}
				} );

				trigger.addEventListener( 'keydown', ( event ) => {
					if ( ! desktop || ! [ 'ArrowDown', 'ArrowUp' ].includes( event.key ) ) {
						return;
					}

					event.preventDefault();
					openDesktopPanel( record );
					const focusable = visibleFocusable( submenu );

					if ( focusable.length ) {
						( 'ArrowUp' === event.key ? focusable[ focusable.length - 1 ] : focusable[ 0 ] ).focus();
					}
				} );
			}
		} );

		/**
		 * Set one submenu's accessible open state.
		 *
		 * @param {Object} record Panel record.
		 * @param {boolean} open Open state.
		 */
		function setPanelState( record, open ) {
			record.trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			record.submenu.setAttribute( 'aria-hidden', open ? 'false' : 'true' );
		}

		/**
		 * Open one desktop top-level disclosure and close its siblings.
		 *
		 * @param {Object} record Top-level panel record.
		 */
		function openDesktopPanel( record ) {
			if ( ! desktop ) {
				return;
			}

			closeSearch( false );

			topRecords.forEach( ( candidate ) => {
				if ( candidate !== record ) {
					closeDesktopPanel( candidate );
				}
			} );

			record.submenu.hidden = false;
			record.submenu.inert = false;
			setPanelState( record, true );
		}

		/**
		 * Close one desktop disclosure.
		 *
		 * @param {Object} record Top-level panel record.
		 */
		function closeDesktopPanel( record ) {
			if ( ! desktop || ! record.isTop ) {
				return;
			}

			record.submenu.hidden = true;
			record.submenu.inert = true;
			setPanelState( record, false );
		}

		/**
		 * Toggle one desktop disclosure.
		 *
		 * @param {Object} record Top-level panel record.
		 */
		function toggleDesktopPanel( record ) {
			if ( 'true' === record.trigger.getAttribute( 'aria-expanded' ) ) {
				closeDesktopPanel( record );
				return;
			}

			openDesktopPanel( record );
		}

		/**
		 * Close every desktop disclosure.
		 */
		function closeDesktopPanels() {
			topRecords.forEach( closeDesktopPanel );
		}

		/**
		 * Open the off-canvas root drawer.
		 */
		function openDrawer() {
			if ( desktop || drawerOpen ) {
				return;
			}

			closeSearch( false );
			drawerOpen = true;
			drawer.inert = false;
			root.classList.add( 'st-toolkit-mega-menu--drawer-open' );
			document.body.classList.add( 'st-toolkit-mega-menu-drawer-open', 'st-toolkit-mega-menu-scroll-locked' );
			menuToggle.setAttribute( 'aria-expanded', 'true' );

			window.setTimeout( () => {
				if ( drawerClose ) {
					drawerClose.focus();
				}
			}, 0 );
		}

		/**
		 * Close the off-canvas root and reset its nested history.
		 *
		 * @param {boolean} restoreFocus Restore focus to the burger button.
		 */
		function closeDrawer( restoreFocus = false ) {
			if ( ! drawerOpen ) {
				return;
			}

			while ( panelStack.length ) {
				closeCurrentPanel( false );
			}

			drawerOpen = false;
			root.classList.remove( 'st-toolkit-mega-menu--drawer-open' );
			document.body.classList.remove( 'st-toolkit-mega-menu-drawer-open', 'st-toolkit-mega-menu-scroll-locked' );
			menuToggle.setAttribute( 'aria-expanded', 'false' );
			drawer.inert = true;

			if ( restoreFocus ) {
				menuToggle.focus();
			}
		}

		/**
		 * Open one recursive mobile submenu drawer.
		 *
		 * @param {Object} record Panel record.
		 */
		function openMobilePanel( record ) {
			if ( desktop ) {
				return;
			}

			const currentPanel = panelStack.length
				? panelStack[ panelStack.length - 1 ].submenu
				: menuList;

			[ ...currentPanel.children ].forEach( ( row ) => {
				row.inert = row !== record.item;
			} );
			currentPanel.scrollLeft = 0;
			record.trigger.inert = true;
			record.submenu.inert = false;
			record.submenu.classList.add( 'st-toolkit-mega-menu__sub-menu--active' );
			setPanelState( record, true );
			panelStack.push( record );

			window.setTimeout( () => {
				currentPanel.scrollLeft = 0;
				record.backButton.focus( { preventScroll: true } );
			}, 0 );
		}

		/**
		 * Return from the active mobile submenu.
		 *
		 * @param {boolean} restoreFocus Restore focus to the item that opened it.
		 */
		function closeCurrentPanel( restoreFocus = false ) {
			const record = panelStack.pop();

			if ( ! record ) {
				return;
			}

			record.submenu.classList.remove( 'st-toolkit-mega-menu__sub-menu--active' );
			record.submenu.inert = true;
			record.trigger.inert = false;
			setPanelState( record, false );

			[ ...record.item.parentElement.children ].forEach( ( row ) => {
				row.inert = false;
			} );
			record.item.parentElement.scrollLeft = 0;

			if ( restoreFocus ) {
				record.trigger.focus( { preventScroll: true } );
			}
		}

		/**
		 * Open search and move focus to its field.
		 */
		function openSearch() {
			if ( ! searchForm || ! searchInput || searchOpen ) {
				return;
			}

			closeDesktopPanels();
			closeDrawer( false );
			searchOpen = true;
			searchForm.hidden = false;
			root.classList.add( 'st-toolkit-mega-menu--search-open' );
			document.body.classList.add( 'st-toolkit-mega-menu-search-open' );
			searchToggle.setAttribute( 'aria-expanded', 'true' );
			window.setTimeout( () => searchInput.focus(), 0 );
		}

		/**
		 * Close search and optionally restore its toggle focus.
		 *
		 * @param {boolean} restoreFocus Restore focus to search toggle.
		 */
		function closeSearch( restoreFocus = false ) {
			if ( ! searchForm || ! searchToggle || ! searchOpen ) {
				return;
			}

			searchOpen = false;
			searchForm.hidden = true;
			root.classList.remove( 'st-toolkit-mega-menu--search-open' );
			document.body.classList.remove( 'st-toolkit-mega-menu-search-open' );
			searchToggle.setAttribute( 'aria-expanded', 'false' );

			if ( restoreFocus ) {
				searchToggle.focus();
			}
		}

		/**
		 * Apply desktop or mobile accessibility state after a mode change.
		 */
		function configureMode() {
			desktop = 'none' !== window.getComputedStyle( breakpoint ).display;

			if ( desktop === lastMode ) {
				return;
			}

			lastMode = desktop;
			closeSearch( false );

			if ( drawerOpen ) {
				closeDrawer( false );
			}

			panelStack.splice( 0 );
			menuList.inert = false;
			menuList.querySelectorAll( '.st-toolkit-mega-menu__item, .st-toolkit-mega-menu__drawer-navigation' ).forEach( ( row ) => {
				row.inert = false;
			} );

			panelRecords.forEach( ( record ) => {
				record.submenu.classList.remove( 'st-toolkit-mega-menu__sub-menu--active' );
				record.trigger.inert = false;

				if ( desktop ) {
					record.submenu.hidden = record.isTop;
					record.submenu.inert = record.isTop;
					setPanelState( record, ! record.isTop );
				} else {
					record.submenu.hidden = false;
					record.submenu.inert = true;
					setPanelState( record, false );
				}
			} );

			if ( desktop ) {
				drawer.removeAttribute( 'aria-modal' );
				drawer.removeAttribute( 'role' );
				drawer.inert = false;
			} else {
				drawer.setAttribute( 'aria-modal', 'true' );
				drawer.setAttribute( 'role', 'dialog' );
				drawer.inert = true;
			}
		}

		menuToggle.addEventListener( 'click', () => {
			if ( drawerOpen ) {
				closeDrawer( true );
			} else {
				openDrawer();
			}
		} );

		if ( drawerClose ) {
			drawerClose.addEventListener( 'click', () => closeDrawer( true ) );
		}

		if ( backdrop ) {
			backdrop.addEventListener( 'click', () => closeDrawer( true ) );
		}

		if ( searchToggle ) {
			searchToggle.addEventListener( 'click', openSearch );
		}

		if ( searchClose ) {
			searchClose.addEventListener( 'click', () => closeSearch( true ) );
		}

		menuList.addEventListener( 'click', ( event ) => {
			if ( ! desktop && event.target.closest( '.st-toolkit-mega-menu__link' ) ) {
				closeDrawer( false );
			}
		} );

		root.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' === event.key ) {
				if ( searchOpen ) {
					event.preventDefault();
					closeSearch( true );
					return;
				}

				if ( ! desktop && panelStack.length ) {
					event.preventDefault();
					closeCurrentPanel( true );
					return;
				}

				if ( drawerOpen ) {
					event.preventDefault();
					closeDrawer( true );
					return;
				}

				const openRecord = topRecords.find( ( record ) => 'true' === record.trigger.getAttribute( 'aria-expanded' ) );

				if ( openRecord ) {
					event.preventDefault();
					closeDesktopPanel( openRecord );
					openRecord.trigger.focus();
				}
			}

			if ( desktop && [ 'ArrowLeft', 'ArrowRight' ].includes( event.key ) ) {
				const topControls = [ ...menuList.querySelectorAll( ':scope > .st-toolkit-mega-menu__item > .st-toolkit-mega-menu__link, :scope > .st-toolkit-mega-menu__item > .st-toolkit-mega-menu__trigger' ) ];
				const currentIndex = topControls.indexOf( document.activeElement );

				if ( -1 !== currentIndex ) {
					event.preventDefault();
					const direction = 'ArrowRight' === event.key ? 1 : -1;
					const nextIndex = ( currentIndex + direction + topControls.length ) % topControls.length;
					topControls[ nextIndex ].focus();
				}
			}

			if ( ! desktop && drawerOpen && 'Tab' === event.key ) {
				const focusable = visibleFocusable( drawer );

				if ( ! focusable.length ) {
					return;
				}

				const first = focusable[ 0 ];
				const last = focusable[ focusable.length - 1 ];

				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
		} );

		document.addEventListener( 'pointerdown', ( event ) => {
			if ( desktop && ! root.contains( event.target ) ) {
				const focusedPanel = topRecords.find( ( record ) => record.submenu.contains( document.activeElement ) );

				if ( focusedPanel ) {
					focusedPanel.trigger.focus( { preventScroll: true } );
				}

				window.setTimeout( closeDesktopPanels, 0 );
			}

			if (
				searchOpen
				&& ! searchForm.contains( event.target )
				&& ! searchToggle.contains( event.target )
			) {
				closeSearch( false );
			}
		} );

		window.addEventListener( 'resize', () => {
			window.clearTimeout( resizeTimer );
			resizeTimer = window.setTimeout( configureMode, 100 );
		} );

		root.classList.add( 'st-toolkit-mega-menu--ready' );
		document.body.classList.add( 'st-toolkit-mega-menu-ready' );
		configureMode();
	} );
}() );
