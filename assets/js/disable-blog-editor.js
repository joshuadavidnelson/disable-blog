(function() {
	'use strict';

	// based on the admin page perform different things.
	if ( typeof dwpbEditor.disabledBlocks !== 'undefined' && !!dwpbEditor.disabledBlocks.forEach ) {
		wp.domReady( () => {
			dwpbEditor.disabledBlocks.forEach(function( block ) {
				wp.blocks.unregisterBlockType( block );
			})
		} );
	} // end if

})();
