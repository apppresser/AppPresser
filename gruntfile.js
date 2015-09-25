module.exports = function(grunt) {
	// Project configuration.
	grunt.initConfig({
		jshint:{
			all:[
				'js/appp.js',
				]
		},
		uglify: {
			dist: {
				files: {
					'js/appp.min.js': ['js/appp.js']
				}
			}
		}
	});

	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-uglify');

	// Default task.
	grunt.registerTask('default', ['jshint','uglify']);
};