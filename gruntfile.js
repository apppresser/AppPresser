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
		},
		shell: {
	        rsync: {
	            command: './rsync.sh'
	        }
	    }
	});

	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-shell');

	// Default task.
	grunt.registerTask('default', ['jshint','uglify']);
	grunt.registerTask('rsync', 'shell:rsync');
};