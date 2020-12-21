(function() {
	'use strict';

	var adminFunctions = function() {

		// based on the admin page perform different things.
		if ( typeof dwpb.page !== 'undefined' ) {
			switch( dwpb.page ) {

				case 'index': // Dashboard.

					// if we don't support comments, the dashboard welcome panel shouldn't show the comment toggle.
					if ( typeof dwpb.commentsSupported !== 'undefined' && ! dwpb.commentsSupported ) {
						document.querySelector('.welcome-icon.welcome-comments').parentNode.classList.add("hidden");
					}

					break;

				case 'options-writing':

					document.querySelector("label[for='default_post_format']").parentNode.parentNode.classList.add("hidden");

					// If we're not supporting categories, then pull this option off the screen.
					// document.querySelector(".form-table[role='presentation'] tbody tr:not(.hidden)").length
					if ( ! dwpb.categoriesSupported ) {
						document.querySelector("label[for='default_category']").parentNode.parentNode.classList.add("hidden");
					}

					break;

				case 'options-permalink':

					// If we're not supporting categories, remove the category base permlink option.
					if ( ! dwpb.categoriesSupported ) {
						document.querySelector("label[for='category_base']").parentNode.parentNode.classList.add("hidden");
					}

					// If we're not supporting tags, remove the tag base permlink option.
					if ( ! dwpb.tagsSupported ) {
						document.querySelector("label[for='tag_base']").parentNode.parentNode.classList.add("hidden");
					}

					// TODO: check if there are any other custom items in the "optional" section, if not then remove the title and intro paragraph.

					break;

				default:
					break;
			}

		} // category_base

	}

	document.addEventListener( 'DOMContentLoaded', adminFunctions );

})();
