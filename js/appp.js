/**
 * AppPresser cookie tool
 * @since 1.0.3
 * @type  {Object}
 */

// initiate apppCore var if it hasn't been
window.apppCore = typeof window.apppCore !== 'undefined' ? window.apppCore : {};

/**
 * Get things started
 * @since  1.0.3
 */
apppCore.init = function() {
	// initiate apppCore parameters
	apppCore._isApp    = typeof apppCore.mobile_browser_theme_switch !== 'undefined' && apppCore.mobile_browser_theme_switch === 'on' ? true : 'not set';
	apppCore.queryVars = false;

	apppCore.log( 'apppCore', apppCore );

	if ( ! apppCore.is_appp_true && ! apppCore.QueryVars('appp') && ! apppCore._isApp && apppCore.isApp() && apppCore.isMobile() ) {

		// Redirect to query var-ed version
		window.location.href = apppCore.AddQueryVar( window.location.href, 'appp', 1 );

	} else if ( apppCore.QueryVars('appp') ) {
		apppCore.log( 'apppCore.is_appp_true', !! apppCore.is_appp_true );
		apppCore.log( "apppCore.QueryVars('appp')", !! apppCore.QueryVars('appp') );
		apppCore.log( 'apppCore.isApp()', !! apppCore.isApp() );
		apppCore.log( 'apppCore.isMobile()', !! apppCore.isMobile() );
	}
}

/**
 * Gets cookie value by name
 * @since  1.0.3
 * @param  {string} name Name of cookie to retrieve
 * @return {string}      Value of cookie if found
 */
apppCore.ReadCookie = function(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
};

/**
 * Removes cookie value
 * @since  1.0.3
 * @param  {string} name Name of cookie
 */
apppCore.EraseCookie = function(name) {
	if ( apppCore.ReadCookie(name) )
	   document.cookie = name+'=';
	apppCore.log(name+' erased.');
};

/**
 * Deletes cookie reference
 * @since  1.0.3
 * @param  {string} name Name of cookie
 */
apppCore.DeleteCookie = function(name) {
	document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
	apppCore.log(name+' deleted.');
};

/**
 * Set cookie value
 * @since  1.0.3
 * @param  {string} name Name of cookie
 */
apppCore.SetCookie = function(name, value, expires) {

	var cookiestring = [[name, '=', encodeURIComponent( value )].join('')];
	var expire_time = '';

	if ( expires ) {
		expire_time = new Date();
		expire_time.setTime( expire_time.getTime() + expires );
		expire_time = expire_time.toGMTString();
		cookiestring.push( ['expires=', expire_time ].join('') );
	}
	cookiestring = cookiestring.join(';')+';';
	document.cookie = cookiestring;
	apppCore.log( 'SetCookie: '+ name +' set to "'+ value +'"', 'Expires?', expire_time );
};

/**
 * Parse current url & return associative array or bool.
 * @since  1.0.3
 * @param  {string} Query var to check
 * @return {mixed}  Returns either bool or full array of query var parts
 */
apppCore.QueryVars = function( queryVar ) {

	if ( apppCore.queryVars ) {
		if ( queryVar )
			return apppCore.queryVars.hasOwnProperty( queryVar );
		return apppCore.queryVars;
	}
	// string = string ? string : window.location.href;
	string = window.location.href;
	var vars = [], hash, parse = string.search(/\?/i);

	// if we don't find a query string, return false
	if ( parse === -1 ) {
		return false;
	}

	// if we do, break the pieces into an array
	var hashes = string.slice(string.indexOf('?') + 1).split('&');
	for( var i = 0; i < hashes.length; i++ ) {
		hash = hashes[i].split('=');
		vars.push(hash[0]);
		vars[hash[0]] = hash[1];
	}
	apppCore.queryVars = vars;

	// return bool, whether query var exists
	if ( queryVar )
		return apppCore.queryVars.hasOwnProperty( queryVar );

	// return the array
	return apppCore.queryVars;
};

apppCore.AddQueryVar = function( url, queryVar, value ) {
    // Using a positive lookahead (?=\=) to find the
    // given parameter, preceded by a ? or &, and followed
    // by a = with a value after than (using a non-greedy selector)
    // and then followed by a & or the end of the string
    var val = new RegExp('(\\?|\\&)' + queryVar + '=.*?(?=(&|$))'),
        qstring = /\?.+$/;

    // Check if the parameter exists
    if ( val.test( url ) ) {
        // if it does, replace it, using the captured group
        // to determine & or ? at the beginning
        return url.replace(val, '$1' + queryVar + '=' + value);

    } else if (qstring.test(url)) {
        // otherwise, if there is a query string at all
        // add the param to the end of it
        return url + '&' + queryVar + '=' + value;

    } else {
        // if there's no query string, add one
        return url + '?' + queryVar + '=' + value;
    }
}

/**
 * Safely log things if query var is set or WP_DEBUG or SCRIPT_DEBUG is on
 * @since  1.0.3
 */
apppCore.log = function() {
	'use strict';
	if ( ( apppCore.debug || apppCore.QueryVars('appp-debug') ) && console && typeof console.log === 'function' ) {
		console.log.apply(console, arguments);
	}
};


/**
 * Safely log things if query var is set or WP_DEBUG or SCRIPT_DEBUG is on
 * @since  1.0.3
 */
apppCore.isMobile = function() {
	var isMobile = false;
	(function(a,b){if(/(android|bb\d+|meego|android|ipad|playbook|silk).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4)))isMobile = true})(navigator.userAgent||navigator.vendor||window.opera);
	return isMobile;
};

/**
 * Returns number of pageviews cookie value
 * @since  1.0.3
 * @param  {string} name Cookie name
 * @return {int}    Page views count
 */
apppCore.isApp = function( name ) {

	if ( apppCore._isApp === 'not set' ) {

		apppCore.cookieName = name ? name : 'AppPresser_Appp';

		// Check query var to Set the is_app cookie
		if ( apppCore.QueryVars('appp') ) {
			apppCore.SetCookie(apppCore.cookieName, true, 86400 * 30);
		}

		// Check query vars for deleting cookie
		if ( apppCore.QueryVars('erase-AppPresser_Appp') )
			apppCore.EraseCookie(apppCore.cookieName);

		// get our value
		var cookie = apppCore.ReadCookie(apppCore.cookieName);
		apppCore.log( 'isApp: '+ apppCore.cookieName, cookie );

		apppCore._isApp = cookie === true;

	}

	// return our count
	return apppCore._isApp;
};

apppCore.init();

if ( typeof onDeviceReady != 'undefined' ) {
	document.addEventListener("deviceready", onDeviceReady, false);
}

function onDeviceReady() {
	if( navigator.splashscreen && apppCore.is_appp_true == 1 )
    navigator.splashscreen.hide();
}