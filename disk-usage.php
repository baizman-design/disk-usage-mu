<?php

/**
 * Plugin Name:   Disk Usage
 * Plugin URI:    https://github.com/baizman-design/disk-usage-mu
 * Description:   A WordPress must-use plugin displaying disk usage.
 * Author:        Saul Baizman
 * Author URI:    https://baizmandesign.com
 * Version:       1.0.0
 */

namespace disk_usage_mu;

class mu_plugin {

	// current operating system.
	private string $os = '';
	private array $supported_systems = [
		'Darwin', // macOS
		'Linux',
		'FreeBSD',
	];

	// URL key in the GET request.
	private string $get_url_key = 'refresh-disk-usage-transient';

	/**
	 * Add WordPress hook.
	 *
	 * @return void
	 */
	public static function run():void
	{
		$self = new self();
		$self->set_os();
		// FIXME (maybe): move to add_admin_dashboard_widget()?
		if ( in_array ( needle: $self->os, haystack: $self->supported_systems ) ) {
			// add custom dashboard widget (visible only to admins).
			add_action(
				hook_name: 'wp_dashboard_setup',
				callback: [$self, 'add_admin_dashboard_widget'],
			);
		}
	}

	/**
	 * Set the operating system name.
	 *
	 * @return void
	 */
	private function set_os():void
	{
		$this->os = php_uname(
			mode: 's',
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
		if ( isset( $_GET[$this->get_url_key] ) ) {
			$disk_usage_transient = false;
		}
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
			// reformat array from "0 => 3M\t/path/to/dir" to "/path/to/dir => 3M".
			array_map(
				function ( $subdirectory_entry ) use ( &$subdirectories_array ) {
					list( $size, $directory ) = explode(
						separator: "\t",
						string: $subdirectory_entry
					);
					$subdirectories_array[$directory] = trim( $size );
				},
				$wp_content_subdirectories,
			);
			// filter out non-directories.
			$subdirectories_array = array_filter(
				$subdirectories_array,
				function ( $size, $maybe_subdirectory ){
					return is_dir( filename: $maybe_subdirectory ) ;
				},
				ARRAY_FILTER_USE_BOTH
			);
			// filter out empty directories.
			$subdirectories_array = array_filter(
				$subdirectories_array,
				function ( $size, $subdirectory ) {
					return $size != '0'; // zero bytes.
				},
				ARRAY_FILTER_USE_BOTH
			);
			// sort the array in descending order based on the key's value.
			arsort(
				array: $subdirectories_array,
				flags: SORT_NUMERIC
			);
			// find core directories.
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
			$css = [
				'display' => 'grid',
				'grid-template-columns' => '1fr 1fr',
				'grid-gap' => '20px',
			];
			$html = sprintf( '<div style="%1$s">',
				implode( separator: '; ', array: array_map(
					fn( string $property, string $value ):string => sprintf('%1$s: %2$s',
						$property,
						$value
					),
					array_keys( $css ),
					array_values( $css )
				)),
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
			// sum the total.
			$total_bytes = 0;
			array_map(
				callback: function ( $subdirectory_size ) use ( &$total_bytes ) {
					$total_bytes += $subdirectory_size;
				},
				array: $subdirectories_array,
			);
			$html .= sprintf('<p><strong>total:</strong> %1$s</p>',
				$this->_reformat_size_format(
					size: $total_bytes,
					decimals: 2,
				),
			);
			date_default_timezone_set( timezoneId: 'America/New_York' );
			$now = current_datetime();
			$html .= sprintf('<p><small>Last updated at %1$s on %2$s. (<a href="%3$s">refresh</a>)</small></p>',
				$now->format( format: 'G.i' ), // time
				$now->format( format: 'Y.m.d' ), // date
				add_query_arg(
					[$this->get_url_key => 1],
					admin_url(),
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
	 * Get the disk usage of the subdirectories of a given directory.
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
		];
		if ( file_exists( filename: $directory ) ) {
			$exec = exec(
				command: sprintf('%1$s -%2$s %3$s/*',
					$command,
					implode( $arguments ),
					$directory,
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

	/**
	 * Format each directory entry as an HTML list item.
	 *
	 * @link https://stackoverflow.com/questions/59442474/print-in-bytes-with-du
	 *
	 * @param string $directory
	 * @param string $size
	 *
	 * @return string
	 */
	private function _format_directory_entry(
		string $directory,
		string $size,
	):string
	{
		return sprintf('<li>+ %1$s &mdash; %2$s</li>',
			basename(
				path: $directory
			),
			$this->_reformat_size_format(
				size: $size
			),
		);
	}

	/**
	 * Reformat the return value of size_format(). Example: "5 MB" => "5M".
	 *
	 * @param string $size
	 * @param int $decimals
	 *
	 * @return string
	 */
	private function _reformat_size_format(
		string $size,
		int $decimals = 0,
	):string
	{
		// we can't get the directory in bytes on macOS. see the "du" man page.
		$du_multiplier = 1024;
		if ( $this->os == 'Darwin' ) {
			$du_multiplier = 512;
		}
		list ( $amount, $unit ) = explode(
			separator: ' ',
			string: size_format(
				bytes: $size * $du_multiplier,
				decimals: $decimals,
			)
		);
		return sprintf('%1$s%2$s',
			$amount,
			$unit[0], // get first character.
		);
	}

}
// decouple from WordPress.
if ( defined( constant_name: 'ABSPATH' ) ) {
	mu_plugin::run();
}
