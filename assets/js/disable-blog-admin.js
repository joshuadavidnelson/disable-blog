(function() {
	'use strict';

	/**
	 * Find an element by selector and walk up a number of parentNode levels,
	 * adding the 'hidden' class to whatever is found.
	 *
	 * Returns null instead of throwing when the selector or an ancestor is
	 * missing, so one absent element can't kill the rest of the calling
	 * switch statement (previously an uncaught TypeError here would).
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
					// Dead on WP 6.1+: core removed this welcome-panel markup, so the
					// selector never matches there; hideRow() no-ops instead of throwing.
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
