/**
 * AppPresser
 * This is a helper file for AppPresser when the app theme is not loaded, but the regular desktop theme is.
 */
(function(window, document, $, undefined) {
	var apppresser = {
		init: function() {
			/**
			 * The AppPresser_Bypass cookie allows the IAB to display the desktop theme.
			 * If the AppPresser_Bypass cookie is in use it needs to be removed before
			 * the current window closes.
			 */
			$(window).unload(apppresser.removeByPassCookie);
		},
		removeByPassCookie: function() {
			apppresser.cookie.delete('AppPresser_Bypass');
		},
		cookie: {
			delete: function(name) {
				document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
			}
		}
	};

	apppresser.init();
})(window, document, jQuery, undefined);