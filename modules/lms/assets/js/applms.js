(function(window, document, $, undefined){
	'use strict';

	var applms = {};

	applms.init = function() {

		$('body').on( 'click', '.applms-mark-complete', applms.completeClick );

		// not working well, especially on android
		// $(document).on('click', '.wpProQuiz_certificate a', applms.certRedirect );

		if( $('#applms-course-progress') ) {
			applms.getProgress();
		}

		window.addEventListener("message", function receiveMessage(event) {

			if(event.data === 'app_view_enter') {
				applms.getProgress();
			}

		});

		applms.fixAjaxAuth();

	};

	applms.completeClick = function( event ) {

		var $spinner = $('.applms-mark-complete .spinner');

		var append = '?appp=3';

		$spinner.show();

		var post_id = $(event.target).data("postid");
		var user_id = $(event.target).data("userid");
		var post_type = $(event.target).data("posttype");
		var course_id = $(event.target).data("courseid");
		var lesson_id = $(event.target).data("lessonid");

		$.ajax({
			type: 'POST',
			dataType: 'json',
			url: apppCore.ajaxurl,
			data: {
				'action': 'applms_mark_complete',
				'user_id': user_id,
				'post_id': post_id,
				'lesson_id': lesson_id,
				'course_id': course_id,
				'post_type': post_type,
				'nonce': window.applms.nonce
			}
		})
		.done( function(data){

			if( data.success === true ) {
				$('.applms-completed-checkmark').show();
				if( data.data === 'Lesson Completed.' ) {

					if( window.applms.lessonCompleteRedirect ) {

						// clicked mark complete on lesson
						setTimeout( function() {
							window.location.href = window.applms.lessonCompleteRedirect + append;
						}, 200 );

					} else if( post_type === 'sfwd-topic' && window.applms.lessonCompleteRedirectTopic ) {

						// completed lesson by completing all topics
						alert( window.applms.lessonCompleted );
						
						setTimeout( function() {
							window.location.href = window.applms.lessonCompleteRedirectTopic + append;
						}, 200 );

					} else if( post_type === 'sfwd-quiz' && window.applms.lessonCompleteRedirectQuiz ) {

						// lesson completed via quiz
						alert( window.applms.lessonCompleted );

						applms.openPage( window.applms.lessonCompleteRedirectQuiz );

					}
				
				} else if( data.data === 'Course completed.' ) {
					alert( window.applms.courseCompleted );
					applms.openPage( window.applms.courseCompleteRedirect );
				} else if( data.data === 'Topics completed.' ) {

					alert( window.applms.topicsCompleted );

					if( window.applms.topicsCompleteRedirect ) {
						window.location.href = window.applms.topicsCompleteRedirect + append;
					} else {
						applms.goBack();
					}
				} else if( data.data === 'Quiz completed.' ) {

					applms.goBackToPreviousLmsPage();

				} else if( post_type === 'sfwd-topic' ) {

					if( window.applms.nextTopic ) {
						setTimeout( function() {
							window.location.href = window.applms.nextTopic + append;
						}, 200 );
					} else {
						window.location.reload();
					}
					
				} else if( post_type === 'sfwd-quiz' && data.data === 'Nothing completed.' ) {
					applms.goBack();
				}
			} else {
				alert( data.data );
			}
		})
		.fail(function(e) {
			console.warn(e);
			alert( "There was a problem, please exit the page and try again." );
		})
		.always(function() {
			$spinner.hide();
		});
	};

	// mimics clicking the app back button to close view
	applms.goBack = function() {

		// dismiss current view
		if(parent) {
			parent.postMessage( 'goback', '*');
		}

	};

	// send postMessage for openPage(p)
	applms.openPage = function( url ) {

		var message = { title: '', apppage: {url: url, root:'true' } };
		parent.postMessage( JSON.stringify(message), '*');

	};

	// send postMessage for openLmsPage(p)
	applms.openLmsPage = function( id, type, permalink, lesson_id, course_id ) {

		var message = { applmspage: { next_item_id: id, next_item_type: type, next_item_permalink: permalink, lesson_id: lesson_id, course_id: course_id } };
		parent.postMessage( JSON.stringify(message), '*');

	};

	// send postMessage for goBackToPreviousLmsPage()
	applms.goBackToPreviousLmsPage = function() {

		var message = { applmspage: { go_back: true } };
		parent.postMessage( JSON.stringify(message), '*');

	};

	applms.getProgress = function() {

		if( !$('#applms-course-progress') )
			return;

		var course_id = $('#applms-course-progress').data('courseid');

		if( !window.applms || !window.applms.user_id || window.applms.user_id === '' || !course_id  ) {
			return;
		}

		$.ajax({
			type: 'POST',
			dataType: 'json',
			url: apppCore.ajaxurl,
			data: {
				'action': 'applms_course_progress',
				'user_id': window.applms.user_id,
				'course_id': course_id,
				'nonce': window.applms.nonce
			}
		})
		.done( function(data){

			$('#applms-course-progress').show();

			var percentage;

			if( !data.data || data.data === '' || data.success === false || !data.data.percentage || data.data.percentage === '' ) {

				percentage = 0;

			} else {
				percentage = data.data.percentage;
			}

			$('#applms-course-progress .course_progress_blue').css( 'width', percentage + '%' );

			
		});
	};

	// fixes ajax in quizzes by adding our token authentication to all ajax requests
	applms.fixAjaxAuth = function() {

		console.log('ajax fix implemented')
	    
	    const urlParams = new URLSearchParams(window.location.search);
	    const token = urlParams.get('token');
	    
	    if( token ) {
		    $.ajaxSetup({
			    data: { 
				    'app_token': urlParams.get('token')
			    }
            });
            console.log('Added token', token);
		}
        
	}
	
	applms.certRedirect = function(e) {
		console.log(e);
		if( e && e.target.href && e.target.href.indexOf('https:') >= 0 ) {
			e.preventDefault();
			var message = { url: e.target.href, title: 'Certificate' };
			parent.postMessage( JSON.stringify(message), '*');
		}
	}

	applms.init();

})(window, document, jQuery);