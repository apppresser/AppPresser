(function($) {
	
	$(document).on( 'click', '.nav-tab-wrapper a', function() {
		$('.nav-tab-wrapper a.nav-tab-active').removeClass('nav-tab-active');
		$('section').hide();
		$('section').eq($(this).index()).show();
		$(this).addClass('nav-tab-active');
		return false;
	})
	
})( jQuery );