<?php

/**
 * Plugin Name:   Disk Usage
 * Plugin URI:    https://github.com/baizman-design/disk-usage-mu
 * Description:   A WordPress must-use plugin displaying disk usage.
 * Author:        Saul Baizman
 * Author URI:    https://baizmandesign.com
 * Text Domain:   disk-usage
 * Version:       1.0.0
 * Domain Path:   /languages
 * Version:       1.0.0
 *
 * @package       Baizman_Design_Disk_Usage
 */

namespace disk_usage_mu;

class mu_plugin {

	// current operating system.
	private string $os = '';

	// supported operating systems.
	private array $supported_systems = [
		'Darwin', // macOS
		'Linux',
		'FreeBSD',
	];

	// URL key in the GET request.
	private string $get_url_key = 'refresh-disk-usage-transient';

	// transient duration.
	// note: using the constant couples this to WordPress.
	private const transient_duration = WEEK_IN_SECONDS;

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
	 * @param string $os
	 *
	 * @return void
	 */
	public function set_os(
		string $os = '',
	):void
	{
		// autoset the operating system when none is specified.
		if ( empty( $os ) ) {
			$this->os = php_uname(
				mode: 's',
			);
		} else {
			$this->os = $os;
		}
	}

	/**
	 * Get the operating system name.
	 *
	 * @return string
	 */
	public function get_os():string
	{
		return $this->os;
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
			$meta_box_css = [
				'display' => 'grid',
				'grid-template-columns' => '1fr 1fr',
				'grid-gap' => '20px',
			];
			// css formatting.
			$css_data = [
				'font-size' => '20px',
				'font-weight' => 'bold',
			];
			$css_data_string = implode( separator: '; ', array: array_map(
					fn( string $property, string $value ):string => sprintf( '%1$s: %2$s',
						$property,
						$value,
					),
					array_keys( $css_data ),
					array_values( $css_data ),
				)
			);
			$css_label = [
				'font-size' => '10px',
				'text-transform' => 'uppercase',
				'letter-spacing' => '0.05em',
			];
			$css_label_string = implode(
				separator: '; ',
				array: array_map(
					fn( string $property, string $value ):string => sprintf( '%1$s: %2$s',
						$property,
						$value,
					),
					array_keys( $css_label ),
					array_values( $css_label ),
				)
			);
			$html = sprintf( '<div style="%1$s">',
				implode(
					separator: '; ',
					array: array_map(
					fn( string $property, string $value ):string => sprintf( '%1$s: %2$s',
						$property,
						$value,
					),
					array_keys( $meta_box_css ),
					array_values( $meta_box_css ),
				)),
			);
			$core_directories_formatted = array_map(
				[$this, '_format_directory_entry'],
				array_keys( $core_directories ),
				array_values( $core_directories ),
				array_fill(
					0,
					count( $core_directories ),
					$css_label_string
				),
				array_fill(
					0,
					count( $core_directories ),
					$css_data_string
				),
			);
			if ( $core_directories_formatted ) {
				// left column.
				$html .= '<div class="core-directories">';
				$html .= sprintf( '<p><strong>%1$s</strong></p>',
					'Core Directories',
				);
				$html .= implode( $core_directories_formatted );
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
				array_fill(
					0,
					count( $other_directories ),
					$css_label_string
				),
				array_fill(
					0,
					count( $other_directories ),
					$css_data_string
				),
			);
			if ( $other_directories_formatted ) {
				// right column.
				$optional_html = '<div class="other-directories">';
				$optional_html .= sprintf('<p><strong>%1$s</strong></p>',
					'Other Directories',
				);
				$optional_html .= implode( $other_directories_formatted );
				// end right column.
				$optional_html .= '</div>';
				$html .= $optional_html;
			}
			$html .= '</div>';
			// print total and database usage.
			$total_extra_css = [
				'border-top' => '1px dotted #cccccc',
			];
			$html .= sprintf( '<div style="%1$s">',
				implode(
					separator: '; ',
					array: array_map(
					fn( string $property, string $value ):string => sprintf('%1$s: %2$s',
						$property,
						$value
					),
					array_keys( array_merge(
						$total_extra_css,
						$meta_box_css,
						)
					),
					array_values( array_merge(
						$total_extra_css,
						$meta_box_css,
						)
					),
				)),
			);

			// sum the total.
			$total_bytes = array_sum(
				array: $subdirectories_array,
			);
			$html .= sprintf('<p><span style="%2$s">%1$s</span><br><span style="%3$s">%4$s</span></p>',
				$this->_reformat_size_format(
					size: $total_bytes,
					decimals: 2,
				),
				$css_data_string,
				$css_label_string,
				__( text: 'total disk usage in', domain: 'disk-usage' ) . ' ' . basename( path: WP_CONTENT_DIR ),
			);
			if ( $this->_is_sqlite() ) {
				# https://github.com/wp-cli/db-command/blob/main/src/DB_Command_SQLite.php#L65
				if ( defined( constant_name: 'FQDB' ) ) {
					$db_path = FQDB;
				} else {
					$db_file = defined( constant_name: 'DB_FILE' ) ? DB_FILE : '.ht.sqlite';
					$db_dir  = defined( constant_name: 'FQDBDIR' ) ? FQDBDIR : WP_CONTENT_DIR . '/database';
					$db_path = rtrim( $db_dir, '/' ) . '/' . ltrim( $db_file, '/' );
				}
				$db_bytes = filesize( $db_path );
				$db_type = 'sqlite';
			} else {
				$db_bytes = $GLOBALS['wpdb']->get_var(
					$GLOBALS['wpdb']->prepare(
						'SELECT SUM(data_length + index_length) FROM information_schema.TABLES where table_schema = %s GROUP BY table_schema;',
						DB_NAME
					)
				);
				$db_type = 'mysql';
			}
			$db_size_default_wp_format = size_format(
					 bytes: $db_bytes, decimals: 2,
				 );
			$html .= sprintf('<p><span style="%2$s">%1$s</span><br><span style="%3$s">%4$s</span></p>',
				 strtr(
					 $db_size_default_wp_format,
					 [' ' => '', 'B' => ''], // remove space and uppercase "B".
				 ),
				$css_data_string,
				$css_label_string,
				$db_type . ' ' . __( text: 'database disk usage', domain: 'disk-usage' ),
			);
			$html .= '</div>';

			/*
			$html .= sprintf('<p><span style="%2$s">%1$s</span><br><span style="%3$s">wp-content directory path</span></p>',
				WP_CONTENT_DIR,
				$total_css_string,
				$total_label_css_string,
			);
			*/
			$now = current_datetime();
			/** @noinspection HtmlUnknownTarget */
			$html .= sprintf( '<p><small>Last updated at %1$s on %2$s. (<a href="%3$s">refresh</a>)</small></p>',
				$now->format( format: 'H.i' ), // time
				$now->format( format: 'Y.m.d' ), // date
				add_query_arg(
					[$this->get_url_key => 1],
					admin_url( path: 'index.php' ), // not actually required, here to be explicit.
				),
			);
			set_transient(
				transient: $transient_name,
				value: $html,
				expiration: self::transient_duration,
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
	 * @param string $label_css
	 * @param string $data_css
	 *
	 * @return string
	 */
	private function _format_directory_entry(
		string $directory,
		string $size,
		string $label_css,
		string $data_css,
	):string
	{
		return sprintf( '<p><span style="%3$s">%2$s</span><br><span style="%4$s">%1$s</span></p>',
			basename(
				path: $directory,
			),
			$this->_reformat_size_format(
				size: $size,
			),
			$data_css,
			$label_css,
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
		if ( $this->get_os() == 'Darwin' ) {
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

	/**
	 * Are we running SQLite?
	 * @link https://github.com/wp-cli/db-command/blob/main/src/DB_Command_SQLite.php#L35
	 *
	 * @return bool
	 */
	private function _is_sqlite() {
		// Check if DB_ENGINE constant is defined and set to 'sqlite'.
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			return true;
		}

		// Check if the SQLite drop-in is loaded by looking for SQLITE_DB_DROPIN_VERSION constant.
		if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) ) {
			return true;
		}

		// Check if db.php drop-in exists and contains SQLite markers.
		$wp_content_dir = defined( constant_name: 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$db_dropin_path = $wp_content_dir . '/db.php';

		if ( file_exists( $db_dropin_path ) ) {
			$db_dropin_contents = file_get_contents( $db_dropin_path );
			if ( false !== $db_dropin_contents && false !== strpos( $db_dropin_contents, 'SQLITE_DB_DROPIN_VERSION' ) ) {
				return true;
			}
		}

		return false;
	}
}

// decouple from WordPress.
if ( defined( constant_name: 'ABSPATH' ) ) {
	mu_plugin::run();
}
