(function() {
	'use strict';

	/**
	 * Find an element by selector and walk up a number of parentNode levels,
	 * adding the 'hidden' class to whatever is found.
	 *
	 * Returns null instead of throwing when the selector doesn't match, or a
	 * parentNode walk runs out of ancestors, so a missing element on one
	 * admin screen can't kill every later case in the calling switch
	 * statement (DEFECT D6: WordPress 6.1+ removed the Dashboard welcome
	 * panel's '.welcome-icon.welcome-comments' markup entirely, so that one
	 * selector never matches on modern WordPress; before this guard, that
	 * turned into an uncaught TypeError that silently stopped the rest of
	 * this handler from ever running).
	 *
	 * @param {string} selector CSS selector to look up.
	 * @param {number} [levels] Number of parentNode levels to walk up before hiding. Default 0.
	 * @return {?Element} The hidden element, or null if nothing was found.
	 */
	var hideRow = function( selector, levels ) {
		var el = document.querySelector( selector );
		if ( ! el ) {
			return null;
		}

		for ( var i = 0; i < ( levels || 0 ); i++ ) {
			el = el.parentNode;
			if ( ! el ) {
				return null;
			}
		}

		el.classList.add( 'hidden' );
		return el;
	};

	var adminFunctions = function() {

		// based on the admin page perform different things.
		if ( typeof dwpb.page !== 'undefined' ) {
			switch( dwpb.page ) {

				case 'index': // Dashboard.

					// If we don't support comments, the dashboard welcome panel
					// shouldn't show the comment toggle.
					//
					// DEAD CODE AS OF WP 6.1+: WordPress 6.1 removed the
					// '.welcome-icon.welcome-comments' classes from the Dashboard
					// welcome panel markup entirely, so this selector never
					// matches on current WordPress and hideRow() is a harmless
					// no-op here. Whether to retarget this at whatever markup (if
					// any) replaced it, or remove it outright, is a separate
					// decision -- this guard only makes the miss harmless instead
					// of fatal (DEFECT D6).
					if ( typeof dwpb.commentsSupported !== 'undefined' && ! dwpb.commentsSupported ) {
						hideRow( '.welcome-icon.welcome-comments', 1 );
					}

					break;

				case 'options-writing':

					hideRow( "label[for='default_post_format']", 2 );

					// If we're not supporting categories, then pull this option off the screen.
					if ( ! dwpb.categoriesSupported ) {
						hideRow( "label[for='default_category']", 2 );
					}

					break;

				case 'options-permalink':

					// If we're not supporting categories, remove the category base permlink option.
					if ( ! dwpb.categoriesSupported ) {
						hideRow( "label[for='category_base']", 2 );
					}

					// If we're not supporting tags, remove the tag base permlink option.
					if ( ! dwpb.tagsSupported ) {
						hideRow( "label[for='tag_base']", 2 );
					}

					// Remove the "Optional" title and paragraph on this screen if there are no other sub-items.
					var categoryBaseLabel = document.querySelector( "label[for='category_base']" );
					var optionPermalinksTable = categoryBaseLabel ? categoryBaseLabel.closest( 'table' ) : null;

					if ( optionPermalinksTable ) {
						var numberOfOptionalRows = optionPermalinksTable.querySelectorAll( 'tr' ).length;

						if ( 2 == numberOfOptionalRows && ! dwpb.tagsSupported && ! dwpb.categoriesSupported ) {
							optionPermalinksTable.classList.add( 'hidden' );

							var previousRow = optionPermalinksTable.previousElementSibling;
							if ( previousRow ) {
								previousRow.classList.add( 'hidden' );

								var previousPreviousRow = previousRow.previousElementSibling;
								if ( previousPreviousRow ) {
									previousPreviousRow.classList.add( 'hidden' );
								}
							}
						}
					}

					break;

				default:
					break;
			} // end switch
		} // end if
	} // end function

	document.addEventListener( 'DOMContentLoaded', adminFunctions );

})();
