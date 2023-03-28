(function() {
	'use strict';

	wp.customize.bind( 'ready', function() {

		// Replace the default text in the homepage settings with the new version.
		if ( typeof dwpbCustomizer.homepageSettingsText !== 'undefined' && typeof wp.customize.section("static_front_page") !== 'undefined' ) {
			wp.customize.section("static_front_page").container.find(".customize-section-description")[0].innerText = dwpbCustomizer.homepageSettingsText;
		} // end if

	} );

})();
