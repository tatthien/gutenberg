<?php

/**
 * Returns a full config or one of the sections containing the
 * setup for the theme found in a file, or a void array if none found.
 *
 * @param string $config_path Path to the config file.
 * @param string $section_name Optional name of the section to pick.
 * @return array Full theme config or its subset.
 */
function gutenberg_experimental_get_theme_config( $config_path, $section_name = null ) {
	$empty_config = array();
	if ( ! file_exists( $config_path ) ) {
		return $empty_config;
	}
	$theme_config = json_decode(
		file_get_contents( $config_path ),
		true
	);
	if ( ! is_array( $theme_config ) ) {
		return $empty_config;
	}
	if ( ! $section_name ) {
		return $theme_config;
	}
	if ( ! isset( $theme_config[ $section_name ] ) || ! is_array( $theme_config[ $section_name ] ) ) {
		return $empty_config;
	}
	return $theme_config[ $section_name ];
}


/**
 * Extends block editor settings with the features loaded from the theme config.
 *
 * @param array $settings Default editor settings.
 *
 * @return array Filtered editor settings.
 */
function gutenberg_extend_settings_features( $settings ) {
	$theme_features = gutenberg_experimental_get_theme_config(
		dirname( dirname( __FILE__ ) ) . '/experimental-default-theme.json',
		'features'
	);
	$settings[ '__experimentalFeaturesConfig' ] = $theme_features;

	$theme_blocks = gutenberg_experimental_get_theme_config(
		dirname( dirname( __FILE__ ) ) . '/experimental-default-theme.json',
		'blocks'
	);
	$settings[ '__experimentalBlocksConfig' ] = $theme_blocks;

	return $settings;
}
add_filter( 'block_editor_settings', 'gutenberg_extend_settings_features' );
