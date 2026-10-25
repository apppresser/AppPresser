// Setup our object
window.appcamera = {
	Attachment : {},
	AllAttachments : {},
	Views : {},
};

(function(window, document, $, undefined) {

	appData = window.appCamAdmin;
	appcam = window.appcamera;
	appcam.updateCount = function( count ){
		this.$menu   = this.$menu || $('.toplevel_page_apppresser_settings');
		count        = count ? parseFloat( count ) : 0;
		var $counter = this.$menu.find('.update-plugins');

		if ( count ) {
			$counter.attr( 'class', 'update-plugins count-' + count );
			$('span', $counter).text( count );
		} else {
			$counter.remove();
		}
	};

	/**
	 * Model
	 */
	appcam.Attachment = Backbone.Model.extend({
		defaults: {
			url: '',
			date: '',
			mime: '',
			title: '',
			thumb: '',
			post_id: '',
			editLink: '',
			approved: false,
			authorURL: '',
			authorName: '',
			parentTitle: '',
			parentEditURL: ''
		},

		url: function() {
			// add query vars to our ajax url
			var action = this.get( 'approved' ) ? 'approve' : 'deny';
			var url = window.ajaxurl +'?action='+ appData.action +'&id='+ encodeURIComponent( this.id ) +'&approved='+ encodeURIComponent( action );
			return url;
		}
	});

	/**
	 * Collection
	 */
	appcam.AllAttachments = Backbone.Collection.extend({ model : appcam.Attachment });

	/**
	 * Table view
	 */
	appcam.Views.Table = Backbone.View.extend({
		initialize: function() {
			var self = this;
			this._rows = [];
			// create a sub view for every model in the collection
			this.collection.each( function( model ) {
				self._rows.push( new appcam.Views.Row({ model: model }) );
			});
			if ( ! this._rows.length ) {
				$('.no-photos').text( $('.no-photos').text() + appData.redirected );
				setTimeout( function() {
					window.location.href = appData.settingsURL;
				}, 2500 );
			}
			this.render();
			this.listenTo( this.collection, 'remove', this.checkEmpty );
		},

		checkEmpty: function() {
			if ( ! this.collection.length ) {
				this.$el.parents('.have-photos').after( '<p>'+ appData.no_photos +'</p>' ).remove();
			}
			appcam.updateCount( this.collection.length );
		},

		render: function() {
			this.$el.empty();
			var addedElements = document.createDocumentFragment();
			// render each row, appending to our root element
			_.each( this._rows, function( row ) {
				addedElements.appendChild( row.render().el );
			});
			this.$el.append( addedElements );
		}
	});

	/**
	 * Single row
	 */
	appcam.Views.Row = Backbone.View.extend({
		tagName  : 'tr',
		// Get the template from the DOM
		template : wp.template( appData.rowTemplate ),
		spinner  : '',
		// Attach events
		events   : {
			'click .row-actions .edit a'   : 'approve',
			'click .row-actions .delete a' : 'removeIt'
		},

		// Render the row
		render: function() {
			this.$el.html( this.template( this.model.toJSON() ) );
			this.spinner = this.$el.find('.spinner');
			return this;
		},

		// Perform the Approval
		approve: function(e) {
			e.preventDefault();
			this.spinner.show();
			this.model.set( 'approved', true );
			this.removeIt(e);
		},

		// Perform the Denial
		removeIt: function(e) {
			e.preventDefault();
			var _this     = this;
			var $errormsg = _this.$el.find('.file-error');

			// Ajax error handler
			var destroyError = function( model, response ) {
				_this.log( 'destroyError', response );
				_this.spinner.hide();
				// whoops.. re-show row and add error message
				$errormsg.show();
				_this.$el.fadeIn( 300 );
			};

			// Ajax success handler
			var destroySuccess = function( model, response ) {
				// If our response reports success
				if ( response.success ) {
					_this.log( 'destroySuccess', response );
					_this.spinner.hide();
					// remove our row completely
					_this.$el.remove();
				} else {
					// whoops, error
					destroyError( model, response );
				}
			};

			// Show spinner
			_this.spinner.show();
			// Hide error message (if it's showing)
			$errormsg.hide();
			// Optimistically hide row
			_this.$el.fadeOut( 300 );

			// Remove model and fire ajax event
			this.model.destroy({ success: destroySuccess, error: destroyError, wait: true });

		},

		log: function( tolog ) {
			'use strict';
			if ( console && typeof console.log === 'function' ) {
				console.log.apply(console, arguments);
			}
		}

	});


	/**
	 * Init
	 */
	(function(){
		// Get our attachment model data from the dom, and initiate the collection
		var collection     = new appcam.AllAttachments( appData.attachments );
		// Send the model data to our table view
		var collectionview = new appcam.Views.Table({
			collection: collection,
			el: appData.tableTemplate
		});
	})();

})(window, document, jQuery);
