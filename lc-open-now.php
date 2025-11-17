<?php
/**
 * Plugin Name: LC Open Now?
 * Description: Current shop open status and opening times
 * Version: 3.0
 * Author: Lamcat - DS
 * Text Domain: lc
 *
 * @package LC_Open_Now
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    echo wp_kses_post( output_all() );
    exit;
}

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
    exit;
}

// Define constants for plugin paths and URLs.
define( 'LC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'LC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Prevent ACF time_picker fields from converting time to UTC or adjusting for timezone.
add_filter(
	'acf/update_value/type=time_picker',
	function ( $value, $post_id, $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Always save the value as entered, no conversion.
		return $value;
	},
	10,
	3
);

add_filter(
	'acf/load_value/type=time_picker',
	function ( $value, $post_id, $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Always load the value as entered, no conversion.
		return $value;
	},
	10,
	3
);


/**
 * Handles plugin activation.
 *
 * Deactivates the plugin and displays an error message if ACF is not installed.
 */
function lc_plugin_activation() {
    if ( ! class_exists( 'ACF' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'ACF is required for this plugin to work. Plugin deactivated.' );
    }
}
register_activation_hook( __FILE__, 'lc_plugin_activation' );


/**
 * Function to enqueue scripts and styles
 */
function lc_plugin_enqueue_scripts() {
    wp_enqueue_style( 'lc-plugin-style', LC_PLUGIN_URL . 'css/style.css', array(), '2.0' );
}
add_action( 'wp_enqueue_scripts', 'lc_plugin_enqueue_scripts' );


/**
 * Initializes ACF options page and registers the custom block for LC Open Now.
 *
 * Adds an options page for opening times and registers the block if ACF functions exist.
 */
function lc_acf_init() {
    if ( function_exists( 'acf_add_options_page' ) ) {
        acf_add_options_page(
            array(
                'page_title' => 'Open Now?',
                'menu_title' => 'Open Now?',
                'menu_slug'  => 'lc-open-now',
                'capability' => 'edit_posts',
                'redirect'   => false,
            )
        );
    }

    // Register the block.
    if ( function_exists( 'acf_register_block_type' ) ) {
        acf_register_block_type(
			array(
				'name'            => 'lc-open-now',
				'title'           => __( 'Open Now', 'lc' ),
				'description'     => __( 'Displays the open/closed status based on configured hours.', 'lc' ),
				'render_template' => plugin_dir_path( __FILE__ ) . 'block-template.php',
				'category'        => 'widgets',
				'icon'            => 'clock',
				'keywords'        => array( 'open', 'hours', 'status' ),
				'mode'            => 'edit',
				'supports'        => array( 'align' => false ),
			)
		);
    }
    $acf_fields_path = plugin_dir_path( __FILE__ ) . 'lc-open-now--acf.php';

    if ( file_exists( $acf_fields_path ) ) {
        include_once $acf_fields_path;
    } else {
        error_log( 'LC Open Now? acf_field_path not found: ' . $acf_fields_path ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}
add_action( 'acf/init', 'lc_acf_init' );


/**
 * Shortcode handler for displaying opening times.
 *
 * @return string HTML output of opening times.
 */
function lc_opening_times() {
    $output = output_opening_times();
    return $output;
}
add_shortcode( 'lc_opening_times', 'lc_opening_times' );


/**
 * Shortcode handler for displaying the current open/closed state.
 *
 * @return string HTML output of the open/closed state.
 */
function lc_open_state() {
    $output = output_state();
    return $output;
}
add_shortcode( 'lc_open_state', 'lc_open_state' );

/**
 * Shortcode handler for displaying AJAX-based open/closed status.
 *
 * @return string HTML output for AJAX open/closed status.
 */
function lc_open_ajax() {
    $output = output_ajax();
    return $output;
}
add_shortcode( 'lc_open_ajax', 'lc_open_ajax' );




/**
 * Outputs the current open/closed state based on today's opening times.
 *
 * @return string HTML output of the open/closed state.
 */
function output_state() {
    $today = date( 'l' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
    $times = get_field( strtolower( $today ), 'options' );

    $open  = $times['open'];
    $close = $times['close'];

    $is_open = is_open( $open, $close );

    if ( 'open' === $is_open ) {
        return '<div class="open_state mb-4">' . get_field( 'open_message', 'options' ) . '</div>';
    } else {
        return '<div class="open_state mb-4">' . get_field( 'closed_message', 'options' ) . '</div>';
    }
}

/**
 * Outputs the weekly opening times for each day.
 *
 * @param bool $include_schema Whether to include Schema.org markup. Default true.
 * @return string HTML output of the opening times.
 */
function output_opening_times( $include_schema = true ) {
    ob_start();
    $days = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
    echo '<div class="open_times">';
    foreach ( $days as $d ) {
        $times = get_field( strtolower( $d ), 'options' );
        $today = is_today( $d );

        $open  = $times['open'] ?? null;
        $close = $times['close'] ?? null;
		?>
        <div class="open_times__row <?= esc_attr( $today ); ?>">
            <div class="open_times__label">
                <?= esc_html( $times['label'] ); ?>
            </div>
            <div class="open_times__times">
                <?php
                if ( '' === $open ) {
                	?>
                    <div class="open_times__closed">
                        CLOSED
                    </div>
                	<?php
                } else {
                	?>
                    <div class="open_times__open">
                        <?= esc_html( $open ); ?>
                    </div>
                    <div>-</div>
                    <div class="open_times__close">
                        <?= esc_html( $close ); ?>
                    </div>
                	<?php
                }
                ?>
            </div>
        </div>
    	<?php
    }
    echo '</div>';

	if ( $include_schema ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo get_opening_hours_schema();
	}

    return ob_get_clean();
}
/**
 * Outputs a condensed weekly opening times schedule, grouping consecutive days with identical times.
 *
 * @param bool $include_schema Whether to include Schema.org markup. Default true.
 * @return string HTML output of the condensed opening times.
 */
function output_opening_times_short( $include_schema = true ) {
    $days   = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
    $labels = array();
    $times  = array();

    foreach ( $days as $d ) {
        $row      = get_field( strtolower( $d ), 'options' );
        $labels[] = $row['label'] ?? $d;
        $open     = $row['open'] ?? '';
        $close    = $row['close'] ?? '';
        $times[]  = $open . '|' . $close;
    }

    $output     = '<div class="open_times_short">';
    $start      = 0;
    $days_count = count( $days );
    while ( $start < $days_count ) {
        $end = $start;
        while ( $end + 1 < $days_count && $times[ $end + 1 ] === $times[ $start ] ) {
            ++$end;
        }
        $open_close = explode( '|', $times[ $start ] );
        $open       = $open_close[0];
        $close      = $open_close[1];
        if ( $open && $close ) {
            if ( $start === $end ) {
				$output .= '<div class="open_times__row">';
                $output .= '  <div class="open_times__label">' . $labels[ $start ] . '</div>';
				$output .= '  <div class="open_times__times">';
				$output .= '    <div class="open_times__open">' . esc_html( $open ) . '</div>';
				$output .= '    <div>–</div>';
				$output .= '    <div class="open_times__close">' . esc_html( $close ) . '</div>';
				$output .= '  </div>';
				$output .= '</div>';
            } else {
                $output .= '<div class="open_times__row">';
                $output .= '  <div class="open_times__label">' . $labels[ $start ] . ' - ' . $labels[ $end ] . '</div>';
                $output .= '  <div class="open_times__times">';
				$output .= '    <div class="open_times__open">' . esc_html( $open ) . '</div>';
				$output .= '    <div>–</div>';
				$output .= '    <div class="open_times__close">' . esc_html( $close ) . '</div>';
				$output .= '  </div>';
                $output .= '</div>';
            }
        } else { // phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found
            if ( $start === $end ) {
				$output .= '<div class="open_times__row">';
                $output .= '  <div class="open_times__label">' . $labels[ $start ] . '</div>';
				$output .= '  <div class="open_times__times">';
                $output .= '    <div class="open_times__closed">CLOSED</div>';
                $output .= '  </div>';
                $output .= '</div>';
            } else {
                $output .= '<div class="open_times__row">';
                $output .= '  <div class="open_times__label">' . $labels[ $start ] . ' - ' . $labels[ $end ] . '</div>';
				$output .= '  <div class="open_times__times">';
                $output .= '    <div class="open_times__closed">CLOSED</div>';
                $output .= '  </div>';
                $output .= '</div>';
            }
        }
        $start = $end + 1;
    }
    $output .= '</div>';

	if ( $include_schema ) {
		$output .= get_opening_hours_schema();
	}

    return $output;
}

/**
 * Shortcode handler for condensed opening times.
 *
 * @return string HTML output for condensed opening times.
 */
function lc_opening_times_short() {
    return output_opening_times_short();
}
add_shortcode( 'lc_opening_times_short', 'lc_opening_times_short' );

/**
 * Outputs the AJAX container and script for checking store status.
 *
 * @return string HTML and JavaScript for AJAX-based open/closed status.
 */
function output_ajax() {
    ob_start();
    ?>
    <div id="lc-open-now">
        <div>Checking store status...</div>
        <img width=140 height=6 src="<?= esc_url( LC_PLUGIN_URL . '/img/spinner.gif' ); ?>">
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formData = new FormData();
            formData.append('action', 'lc_open_now_action');

            fetch('<?= esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.text())
                .then(data => {
                    const container = document.getElementById('lc-open-now');
                    if (container) {
                        container.innerHTML = data;
                    }
                })
                .catch(error => {
                    console.error('Error loading content:', error);
                });
        });
    </script>
	<?php
    return ob_get_clean();
}

add_action( 'wp_ajax_lc_open_now_action', 'lc_open_now_ajax_handler' );
add_action( 'wp_ajax_nopriv_lc_open_now_action', 'lc_open_now_ajax_handler' );

/**
 * AJAX handler for returning the open/closed status and opening times.
 *
 * Outputs all status and times, then terminates the request.
 */
function lc_open_now_ajax_handler() {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo output_all();
    wp_die(); // This is required to terminate immediately and return a proper response.
}

/**
 * Checks if the given day matches today.
 *
 * @param string $d Day to check.
 * @return string|null 'today' if it matches, otherwise null.
 */
function is_today( $d ) {
    $day = date( 'l' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

    if ( $d !== $day ) {
        return;
    }

    return 'today';
}

/**
 * Gets the opening hours specification array for Schema.org.
 *
 * Returns an array of OpeningHoursSpecification objects that can be
 * used in existing LocalBusiness or Organization schemas.
 *
 * @return array Array of OpeningHoursSpecification objects.
 */
function get_opening_hours_specification_array() {
	$days          = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
	$day_mapping   = array(
		'Monday'    => 'Monday',
		'Tuesday'   => 'Tuesday',
		'Wednesday' => 'Wednesday',
		'Thursday'  => 'Thursday',
		'Friday'    => 'Friday',
		'Saturday'  => 'Saturday',
		'Sunday'    => 'Sunday',
	);
	$opening_hours = array();
	$grouped_hours = array();

	// Collect all opening hours.
	foreach ( $days as $day ) {
		$times = get_field( strtolower( $day ), 'options' );
		$open  = $times['open'] ?? '';
		$close = $times['close'] ?? '';

		if ( '' !== $open && '' !== $close ) {
			// Convert to 24-hour format.
			$open_24  = convert_to_24h( $open );
			$close_24 = convert_to_24h( $close );

			$key = $open_24 . '-' . $close_24;
			if ( ! isset( $grouped_hours[ $key ] ) ) {
				$grouped_hours[ $key ] = array(
					'days'   => array(),
					'opens'  => $open_24,
					'closes' => $close_24,
				);
			}
			$grouped_hours[ $key ]['days'][] = $day_mapping[ $day ];
		}
	}

	// Build OpeningHoursSpecification entries.
	foreach ( $grouped_hours as $group ) {
		$opening_hours[] = array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => $group['days'],
			'opens'     => $group['opens'],
			'closes'    => $group['closes'],
		);
	}

	return $opening_hours;
}

/**
 * Generates OpeningHoursSpecification Schema.org markup as JSON-LD.
 *
 * Creates structured data compatible with existing Organization/LocalBusiness schemas.
 * NOTE: Only outputs if 'lc_output_opening_hours_schema' filter returns true.
 * By default, this is disabled to avoid duplicate schemas.
 *
 * @return string JSON-LD script tag with opening hours schema, or empty string.
 */
function get_opening_hours_schema() {
	// Allow themes to disable schema output if they handle it themselves.
	if ( ! apply_filters( 'lc_output_opening_hours_schema', false ) ) {
		return '';
	}

	$opening_hours = get_opening_hours_specification_array();

	// Only output if there are opening hours.
	if ( empty( $opening_hours ) ) {
		return '';
	}

	// Build complete LocalBusiness schema.
	$schema = array(
		'@context'                  => 'https://schema.org',
		'@type'                     => 'LocalBusiness',
		'@id'                       => get_bloginfo( 'url' ) . '#localbusiness',
		'openingHoursSpecification' => $opening_hours,
	);

	// Allow filtering to merge with or extend existing schema.
	$schema = apply_filters( 'lc_opening_hours_schema', $schema );

	ob_start();
	?>
	<script type="application/ld+json">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	?>
	</script>
	<?php
	return ob_get_clean();
}

/**
 * Converts 12-hour time format to 24-hour format for Schema.org.
 *
 * @param string $time Time in 'g:i a' format (e.g., '9:00 am').
 * @return string Time in 'H:i:s' format (e.g., '09:00:00').
 */
function convert_to_24h( $time ) {
	$dt = DateTime::createFromFormat( 'g:i a', $time );
	if ( ! $dt ) {
		return '';
	}
	return $dt->format( 'H:i:s' );
}



/**
 * Determines if the store is currently open based on opening and closing times.
 *
 * @param string $open  Opening time in 'g:i a' format.
 * @param string $close Closing time in 'g:i a' format.
 * @return string 'open', 'closed', or error message if closing time is before opening time.
 */
function is_open( $open, $close ) {
    $time  = date( 'H:i' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
    $now   = DateTime::createFromFormat( 'H:i', $time );
    $open  = DateTime::createFromFormat( 'g:i a', $open );
    $close = DateTime::createFromFormat( 'g:i a', $close );

    if ( $open > $close ) {
        return 'ERROR: closing time is before opening time';
    }

    if ( $now > $open && $now < $close ) {
        return 'open';
    } else {
        return 'closed';
    }
}

/**
 * Outputs both the opening times and current open/closed state.
 *
 * @return string Combined HTML output of opening times and state.
 */
function output_all() {
    ob_start();
    echo wp_kses_post( output_state() );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo output_opening_times();
    return ob_get_clean();
}

/**
 * Outputs condensed opening times and open state, formatted like output_opening_times(), for AJAX.
 *
 * @return string HTML output for AJAX response.
 */
function output_opening_times_short_ajax() {
    ob_start();
    echo wp_kses_post( output_state() );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo output_opening_times_short();
    return ob_get_clean();
}

/**
 * AJAX handler for condensed opening times and open state.
 */
function lc_opening_times_short_ajax_handler() {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo output_opening_times_short_ajax();
    wp_die();
}
add_action( 'wp_ajax_lc_opening_times_short_ajax', 'lc_opening_times_short_ajax_handler' );
add_action( 'wp_ajax_nopriv_lc_opening_times_short_ajax', 'lc_opening_times_short_ajax_handler' );

/**
 * Shortcode handler for AJAX condensed opening times and open state.
 *
 * @return string HTML and JS for AJAX-powered condensed opening times.
 */
function lc_open_short_ajax() {
    ob_start();
    ?>
    <div id="lc-opening-times-short-ajax">
        <div>Loading opening times...</div>
        <img width=140 height=6 src="<?php echo esc_url( LC_PLUGIN_URL ) . '/img/spinner.gif'; ?>">
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formData = new FormData();
            formData.append('action', 'lc_opening_times_short_ajax');
            fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.text())
                .then(data => {
                    const container = document.getElementById('lc-opening-times-short-ajax');
                    if (container) {
                        container.innerHTML = data;
                    }
                })
                .catch(error => {
                    console.error('Error loading content:', error);
                });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'lc_open_short_ajax', 'lc_open_short_ajax' );


?>