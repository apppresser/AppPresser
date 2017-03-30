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
	    },

	    // http://stephenharris.info/grunt-wordpress-development-iii-tasks-for-internationalisation/
	    pot: {
	    	options:{
	    		text_domain: 'apppresser', //Your text domain. Produces my-text-domain.pot
	    		dest: 'languages/', //directory to place the pot file
	    		keywords: [
	    			'__:1',
	    			'_e:1',
	    			'_x:1,2c',
	    			'esc_html__:1',
	    			'esc_html_e:1',
	    			'esc_html_x:1,2c',
	    			'esc_attr__:1', 
	    			'esc_attr_e:1',
	    			'esc_attr_x:1,2c', 
	    			'_ex:1,2c',
	    			'_n:1,2', 
	    			'_nx:1,2,4c',
	    			'_n_noop:1,2',
	    			'_nx_noop:1,2,3c'
	    		], //functions to look for
	    	},
	    	files:{
	    		src: [ 
	    			'**/*.php', //Parse all php files
	    			'!node_modules/**'
	    		],
	    		expand: true,
	    	}
	    },
	    checktextdomain: {
	       options:{
	          text_domain: 'apppresser',
	          correct_domain: true, //Will correct missing/variable domains
	          keywords: [ //WordPress localisation functions
	                '__:1,2d',
	                '_e:1,2d',
	                '_x:1,2c,3d',
	                'esc_html__:1,2d',
	                'esc_html_e:1,2d',
	                'esc_html_x:1,2c,3d',
	                'esc_attr__:1,2d', 
	                'esc_attr_e:1,2d', 
	                'esc_attr_x:1,2c,3d', 
	                '_ex:1,2c,3d',
	                '_n:1,2,4d', 
	                '_nx:1,2,4c,5d',
	                '_n_noop:1,2,3d',
	                '_nx_noop:1,2,3c,4d'
	          ],
	       },
	       files: {
	           src:  [ 
	           	'**/*.php', //All php files
	           	'!node_modules/**'
	           ],
	           expand: true,
	       },
	    },
	    po2mo: {
	        files: {
	            src: 'languages/*.po',
	            expand: true,
	        },
	    }
	});

	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-shell');
	grunt.loadNpmTasks('grunt-pot');
	grunt.loadNpmTasks('grunt-checktextdomain');
	grunt.loadNpmTasks('grunt-po2mo');

	// Warning! This will auto correct text-domains (checktextdomain.options.correct_domain = true)
	grunt.registerTask('lang', ['pot','checktextdomain','po2mo']);

	// Default task.
	grunt.registerTask('default', ['jshint','uglify']);
	grunt.registerTask('rsync', 'shell:rsync');
};