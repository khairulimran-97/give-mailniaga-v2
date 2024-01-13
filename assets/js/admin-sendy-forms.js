/*!
 * Give Sendy Admin Forms JS
 *
 * @description: The Give Admin Forms scripts. Only enqueued on the give_forms CPT; used to validate fields, show/hide, and other functions
 * @package:     Give
 * @subpackage:  Assets/JS
 * @copyright:   Copyright (c) 2016, WordImpress
 * @license:     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

jQuery.noConflict();
(function ( $ ) {

	/**
	 * Toggle Conditional Form Fields
	 *
	 *  @since: 1.0
	 */
	var toggle_mailniaga_sendy_fields = function () {

		var cc_enable_option = $( '.give-mailniaga-sendy-enable' );
		var cc_disable_option = $( '.give-mailniaga-sendy-disable' );

		cc_enable_option.on( 'change', function () {

			var cc_enable_option_val = $(this ).prop('checked');

			if ( cc_enable_option_val === false ) {
				$( '.give-mailniaga-sendy-field-wrap' ).slideUp('fast');
			} else {
				$( '.give-mailniaga--sendy-field-wrap' ).slideDown('fast');
			}

		} ).change();

		cc_disable_option.on( 'change', function () {

			var cc_disable_option_val = $(this ).prop('checked');

			if ( cc_disable_option_val === false ) {
				$( '.give-mailniaga-sendy-field-wrap' ).slideDown('fast');
			} else {
				$( '.give-mailniaga-sendy-field-wrap' ).slideUp('fast');
			}

		} ).change();

	};


	//On DOM Ready
	$( function () {

		toggle_mailniaga_sendy_fields();

	} );


})( jQuery );