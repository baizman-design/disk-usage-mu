<?php

/**
 * Plugin Name: Disk Usage
 * Plugin URI: https://github.com/baizman-design/disk-usage-mu
 * Description: A WordPress must-use plugin displaying disk usage.
 * Author:        Saul Baizman
 * Author URI:    https://baizmandesign.com
 * Version:       1.0.0
 */

namespace disk_usage_mu;

class mu_plugin {

	/**
	 * Add WordPress hook.
	 *
	 * @return void
	 */
	public static function run():void
	{
		$self = new self();
		// add custom dashboard widget (visible only to admins).
		add_action(
			hook_name: 'wp_dashboard_setup',
			callback: [$self, 'add_admin_dashboard_widget'],
		);
	}

	/**
	 * Add dashboard widget.
	 *
	 * @return void
	 */
	public function add_admin_dashboard_widget():void
	{
		// only display the widget if the user is an admin.
		if ( current_user_can( capability: 'manage_options' ) ) {
			$dashboard_widget_title = sprintf('%1$s Website Disk Usage',
				get_bloginfo(
					show: 'name',
				)
			);
			wp_add_dashboard_widget(
				widget_id: 'disk_usage_admin_dashboard_widget',
				widget_name: $dashboard_widget_title,
				callback: [$this, 'admin_dashboard_widget'],
			);
		}
	}

	/**
	 * Add dashboard widget contents.
	 *
	 * @return void
	 */
	public function admin_dashboard_widget():void
	{
		$transient_name = 'bd_disk_usage_mu';
		$disk_usage_transient = get_transient(
			transient: $transient_name,
		);
		if ( $disk_usage_transient === false ) {
			$uploads = wp_upload_dir(
				create_dir: false,
			);
			$core_directories_default = [
				// plugins.
				basename( path: WP_PLUGIN_DIR ),
				// themes.
				basename( path: dirname( get_stylesheet_directory() ) ),
				// uploads.
				basename( path: $uploads['basedir'] ?? 'uploads' ),
				// mu-plugins.
				basename( path: WPMU_PLUGIN_DIR ),
			];
			$wp_content_subdirectories = $this->_get_subdirectory_disk_usage( directory: WP_CONTENT_DIR );
			$subdirectories_array = [];
			// reformat array.
			foreach ( $wp_content_subdirectories as $subdirectory => $details) {
				list( $size, $directory ) = explode(
					separator: "\t",
					string: $details
				);
				$subdirectories_array[$directory] = $size;
			}
			// filter out non-directories.
			$subdirectories_array = array_filter(
				$subdirectories_array,
				function ( $size, $maybe_subdirectory ){
					return is_dir( filename: $maybe_subdirectory ) ;
				},
				ARRAY_FILTER_USE_BOTH
			);
			// find actual core directories.
			$core_directories = array_filter(
				$subdirectories_array,
				function( $size, $subdirectory ) use ( $core_directories_default ) {
					return in_array( needle: basename( $subdirectory ), haystack: $core_directories_default );
				},
				ARRAY_FILTER_USE_BOTH
			);
			// find non-core directories.
			$other_directories = array_filter(
				$subdirectories_array,
				function( $size, $subdirectory ) use ( $core_directories_default ) {
					return ! in_array( needle: basename( $subdirectory ), haystack: $core_directories_default );
				},
				ARRAY_FILTER_USE_BOTH
			);
			$css = 'display:grid; grid-template-columns: 1fr 1fr;';
			$html = sprintf( '<div style="%1$s">',
				$css,
			);
			$core_directories_formatted = array_map(
				[$this, '_format_directory_entry'],
				array_keys( $core_directories ),
				array_values( $core_directories ),
			);
			if ( $core_directories_formatted ) {
				// left column.
				$html .= '<div class="core-directories">';
				$html .= sprintf( '<p><strong>%1$s</strong></p>',
					'Core Directories',
				);
				$html .= '<ul>';
				$html .= implode( $core_directories_formatted );
				$html .= '</ul>';
				// end left column.
				$html .= '</div>';
			} else {
				printf('<p><strong>Error:</strong> %1$s</p>',
					wptexturize( text: 'The "du" command was not found or could not be run.' ),
				);
				return;
			}
			$other_directories_formatted = array_map(
				[$this, '_format_directory_entry'],
				array_keys( $other_directories ),
				array_values( $other_directories ),
			);
			if ( $other_directories_formatted ) {
				// right column.
				$optional_html = '<div class="other-directories">';
				$optional_html .= sprintf('<p><strong>%1$s</strong></p>',
					'Other Directories',
				);
				$optional_html .= '<ul>';
				$optional_html .= implode( $other_directories_formatted );
				$optional_html .= '</ul>';
				// end right column.
				$optional_html .= '</div>';
				$html .= $optional_html;
			}
			$html .= '</div>';
			date_default_timezone_set( timezoneId: 'America/New_York' );
			$html .= sprintf('<p><small>Last updated %1$s.</small></p>',
				date(
					format: 'Y.m.d H.i',
					timestamp: time(),
				),
			);
			set_transient(
				transient: $transient_name,
				value: $html,
				expiration: HOUR_IN_SECONDS,
			);
		} else {
			$html = $disk_usage_transient;
		}
		print( $html );
	}

	/**
	 * Format directory.
	 *
	 * @param string $directory
	 *
	 * @return array
	 */
	private function _get_subdirectory_disk_usage(
		string $directory,
	):array
	{
		$command = 'du';
		$arguments = [
			's', // summary.
			'h', // human-readable.
			];
		$directory_path = $directory;
		if ( file_exists( filename: $directory_path ) ) {
			$exec = exec(
				command: sprintf('%1$s -%2$s %3$s/*',
					$command,
					implode( $arguments ),
					$directory_path,
				),
				output: $output,
			);
			// no output, or the "du" command is not found.
			if ( empty( $exec )) {
				return [];
			}
			return $output;
		}
		return [];
	}

	private function _format_directory_entry(
		$directory,
		$size,
	):string
	{
		return sprintf('<li>+ %1$s &mdash; %2$s</li>',
			basename( path: $directory ),
			$size,
		);
	}

}

mu_plugin::run();
