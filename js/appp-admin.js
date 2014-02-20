jQuery(document).ready(function($) {

	$.fn.rotate = function(degrees) {
		return $(this).css({'-webkit-transform' : 'rotate('+ degrees +'deg)', '-moz-transform' : 'rotate('+ degrees +'deg)',  '-ms-transform' : 'rotate('+ degrees +'deg)', 'transform' : 'rotate('+ degrees +'deg)'});
	};

	var deg          = 180;
	var $context     = $('.apppresser_settings');
	var $referrer    = $context.find('[name="_wp_http_referer"]');
	var $slidepanel  = $( '#slidepanel' );
	var $show        = $context.find('.show_hidden' ).after( ' <span>▾</span>' );
	var $arrow       = $show.next('span').css({'display':'inline-block'}).rotate(deg);
	var $navtabs     = $context.find( '.nav-tab' );
	var $tabs        = $context.find( '.appp-tabs' );
	var $ajaxinput   = $('#apppresser--appp_home_page');
	var $ajaxcontext = $ajaxinput.parents('tr');
	var $ajaxresults = $ajaxcontext.find('.appp-ajax-results-posts');
	var $ajaxhelp    = $ajaxcontext.find('.appp-ajax-results-help');
	var $spinner     = $ajaxcontext.find('.appp-spinner');


	$context.find( 'a.help' ).tooltip().click( function(e) { e.preventDefault(); } );

	$context
		.on( 'click', '.show_hidden', function( event ) {
			event.preventDefault();

			$slidepanel.slideToggle(300);
			$show.toggleClass('active');

			deg = deg === 180 ? 0 : 180;
			$arrow.rotate(deg);
		})
		.on( 'click', '.nav-tab', function( event ) {
			event.preventDefault();
			var $self  = $(this);
			var newurl = $self.attr( 'href' );

			$tabs.hide();
			$navtabs.removeClass( 'nav-tab-active' );

			// Set new current tab
			$( '.' + $self.data('selector') ).fadeIn('fast');
			$self.addClass( 'nav-tab-active' );

			// Set referrer to current tab
	    	$referrer.val( newurl );
			if ( typeof window.history.pushState == 'function' ) {
			    window.history.pushState( '','', newurl );
			}

		})
		// when typing a page name..
		.on( 'keyup', '#apppresser--appp_home_page', function(event) {
			// fire our ajax function
			maybeAjax( $(this), event );
		}).blur(function() {
			// when leaving the input
			setTimeout(function(){
				// if it's been 2 seconds, hide our spinner
				$spinner.hide();
			}, 2000);
		})
		// When clicking on a results post, populate our input
		.on( 'click', '.appp-ajax-results-posts a', function(event) {
			event.preventDefault();
			var $self = $(this);
			// hide our spinner
			$spinner.hide();
			// populate post ID to field
			$ajaxinput.val( $self.data('postid') )/*.focus()*/;
			// clear our results
			$ajaxresults.html('');
			$ajaxhelp.hide();

		});


	// function for running our ajax
	function maybeAjax( obj, e ) {
		// get typed value
		var post_search = obj.val();
		// only proceed if the user's typed more than 2 characters
		if ( post_search.length < 2 )
			return;

		// only proceed if the user has pressed a number, letter or backspace
		if (e.which <= 90 && e.which >= 48 || e.which == 8) {
			// clear out our results
			$ajaxresults.html('');
			$ajaxhelp.hide();
			// show our spinner
			$spinner.css({'float':'none'}).show();
			// and run our ajax function
			setTimeout(function(){

				// if they haven't typed in 500 ms
				if ( $ajaxinput.val() == post_search ) {
					$.ajax({
						type : 'post',
						dataType : 'json',
						url : ajaxurl,
						data : {
							'action': 'appp_search_post_handler',
							'page_title': post_search,
							'nonce': $('#_wpnonce').val()
						},
						success : ajaxSuccess
					});
				}
			}, 500);
		}
	}

	function ajaxSuccess(response) {
		console.log( 'response', response );
		// if we have a response id
		if ( typeof response.data !== 'undefined' ) {
			// hide our spinner
			$spinner.hide();
			// and populate our results from ajax response
			$ajaxresults.html(response.data);
			$ajaxhelp.show();
		}
	}

});
