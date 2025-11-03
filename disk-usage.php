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
		$uploads = wp_upload_dir(
			create_dir: false,
		);
		$directories = [
			// plugins.
			basename( path: WP_PLUGIN_DIR ),
			// themes.
			basename( path: dirname( get_stylesheet_directory() ) ),
			// uploads.
			basename( path: $uploads['basedir'] ?? 'uploads' ),
		];
		sort( $directories );
		$optional_directories = [
			'ai1wm-backups',
			'updraft',
		];
		sort( $optional_directories );
		$html = sprintf( '<strong>%1$s</strong>',
			'Core Directories',
		);
		$directories_formatted = array_map(
			callback: [$this, '_format_directory'],
			array: $directories,
		);
		$html .= '<ul>';
		$html .= implode( $directories_formatted );
		$html .= '</ul>';

		$optional_html = sprintf('<strong>%1$s</strong>',
			'Optional Directories',
		);
		$optional_directories_formatted = array_map(
			callback: [$this, '_format_directory'],
			array: $optional_directories,
		);
		// remove empty elements from array.
		$optional_directories_formatted = array_filter($optional_directories_formatted);
		if ( $optional_directories_formatted ) {
			$optional_html .= '<ul>';
			$optional_html .= implode( $optional_directories_formatted );
			$optional_html .= '</ul>';
			$html .= $optional_html;
		}
		$html .= sprintf('<p><small>As of %1$s.</small></p>',
			date('Y.m.d H.i', time()),
		);
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
		$command = 'du -sh';
		$directory_path = sprintf('%1$s/%2$s',
			WP_CONTENT_DIR,
			$directory,
		);
		if ( file_exists( filename: $directory_path ) ) {
			$exec = exec(
				command: $command . ' ' . $directory_path,
				output: $output,
			);
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
