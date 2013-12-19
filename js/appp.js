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

	if ( apppCore.isApp( name ) && ! apppCore.QueryVars('appp') ) {
		// Redirect to query var-ed version
		window.location.href = apppCore.AddQueryVar( window.location.href, 'appp', 1 )
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
