<?php

/**
 * Plugin Name: Disk Usage
 * Plugin URI: https://github.com/baizman-design/disk-usage-mu
 * Description: A WordPress must-use plugin displaying disk usage.
 * Author:        Saul Baizman
 * Author URI:    https://baizmandesign.com
 * Version:       1.0.0
 */

// TODO: check if "du" command is present.

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
			$core_directories = [
				// plugins.
				basename( path: WP_PLUGIN_DIR ),
				// themes.
				basename( path: dirname( get_stylesheet_directory() ) ),
				// uploads.
				basename( path: $uploads['basedir'] ?? 'uploads' ),
			];
			sort( $core_directories );
			// mu-plugins, always at the end.
			$core_directories[] = basename( path: WPMU_PLUGIN_DIR );
			$optional_directories = [
				'ai1wm-backups',
				'updraft',
			];
			sort( $optional_directories );
			$css  = 'display:grid; grid-template-columns: 1fr 1fr;';
			$html = sprintf( '<div style="%1$s">',
				$css,
			);
			// left column.
			$core_directories_formatted = array_map(
				callback: [ $this, '_format_directory' ],
				array: $core_directories,
			);
			// core
			$core_directories_formatted = array_filter( $core_directories_formatted );
			if ( $core_directories_formatted ) {
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

			$optional_directories_formatted = array_map(
				callback: [$this, '_format_directory'],
				array: $optional_directories,
			);
			// remove empty elements from array.
			$optional_directories_formatted = array_filter($optional_directories_formatted);
			if ( $optional_directories_formatted ) {
				// right column.
				$optional_html = '<div class="optional-directories">';
				$optional_html .= sprintf('<p><strong>%1$s</strong></p>',
					'Optional Directories',
				);
				$optional_html .= '<ul>';
				$optional_html .= implode( $optional_directories_formatted );
				$optional_html .= '</ul>';
				// end right column.
				$optional_html .= '</div>';
				$html .= $optional_html;
			}
			$html .= '</div>';
			date_default_timezone_set( timezoneId: 'America/New_York' );
			$html .= sprintf('<p><small>As of %1$s.</small></p>',
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
	 * @return string
	 */
	private function _format_directory(
		string $directory,
	):string
	{
		$command = 'du';
		$arguments = [
			's', // summary.
			'h', // human-readable.
			];
		$directory_path = sprintf('%1$s/%2$s',
			WP_CONTENT_DIR,
			$directory,
		);
		if ( file_exists( filename: $directory_path ) ) {
			$exec = exec(
				command: sprintf('%1$s -%2$s %3$s',
					$command,
					implode( $arguments ),
					$directory_path,
				),
				output: $output,
			);
			// no output, or the "du" command is not found.
			if ( empty( $exec )) {
				return '';
			}
			list ( $disk_usage ) = explode(
				separator: "\t",
				string: $output[0],
			);
			return sprintf('<li>+ %1$s: %2$s</li>',
				$directory,
				$disk_usage,
			);
		}
		return '';
	}

}

mu_plugin::run();
