import defaultConfig from '@wordpress/scripts/config/webpack.config.js';

const configs = Array.isArray( defaultConfig )
	? defaultConfig
	: [ defaultConfig ];
const [ scriptConfig, moduleConfig ] = configs;

const scriptEntries = {
	'calendar/runtime': './src/calendar/index.js',
	'opportunity-hub/view': './blocks/opportunity-hub/view.js',
	'member-directory/view': './blocks/member-directory/view.js',
	'video-slider/view': './blocks/video-slider/view.js',
};

const moduleEntries = {
	'opportunity-hub/view-module': './blocks/opportunity-hub/view.js',
};

const buildConfigs = [
	{
		...scriptConfig,
		entry: scriptEntries,
	},
];

if ( moduleConfig ) {
	buildConfigs.push( {
		...moduleConfig,
		entry: moduleEntries,
	} );
}

export default buildConfigs;
