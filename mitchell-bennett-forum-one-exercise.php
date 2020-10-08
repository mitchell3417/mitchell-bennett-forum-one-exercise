<?php
/*
Plugin Name: Mitchell Bennett: Forum One Interview Exercise
Description: The exercise for the Forum One interview.
Author: Mitchell Bennett
Author URI: https://mitchellbennett.rocks
Version: 0.1
*/

// Get rid of intruders
defined( 'ABSPATH' ) || exit;

// Get the user defined API Endpoint
$config = array(
	'api_endpoint'		=> get_option( 'mb_api_endpoint' ),
);

class CollegeScores {
	function __construct( $config ) {
		$this->api_endpoint = $config['api_endpoint'];
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_menu', array( $this, 'mb_admin_menu' ) );
		add_shortcode( 'mb-college-scores', array ( $this, 'get_college_scores' ) );
	}


	function assets() {

		// Front end styles
		wp_enqueue_style(
			'college-scores-main-styles',
			plugins_url( 'assets/css/style.css', __FILE__ ),
			array(),
			'1.0'
		);
	}

	function mb_admin_menu() {

		add_options_page( 'College Scores', 'MB Forum One', 'manage_options', 'mb-forum-one-exercise', 'mb_forum_one_options' );

		function mb_forum_one_options() {
			// Only admins may edit these options
			if ( !current_user_can( 'manage_options' ) )  {
				wp_die( 'You do not have sufficient permissions to access this page.' );
			}

			// Variables for the field and option names
		    $opt_name = 'mb_api_endpoint';
			$hidden_field_name = 'mb_submit_hidden';
		    $data_field_name = 'mb_api_endpoint';

		    // Read in existing option value from database
		    $opt_val = get_option( $opt_name );

		    // See if the user has posted us some information
		    // If they did, this hidden field will be set to 'Y'
		    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
				// Save the option in the database
		        $opt_val = $_POST[ $data_field_name ];
		        update_option( $opt_name, $opt_val );

		        // Put a "settings saved" message on the screen
				?>
					<div class="updated"><p><strong><?php _e('settings saved.', 'menu-test' ); ?></strong></p></div>
				<?php

		    }

		    // The content for the settings page. Mainly the form
			echo '<div class="wrap">';
			echo '<h1 class="wp-heading-inline">Mitchell Bennett Forum One Exercise Settings';
			?>
			<form name="form1" method="post" action="">
				<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

				<p>API Endpoint
					<input type="url" name="<?php echo $data_field_name; ?>" value="<?php echo $opt_val; ?>" size="80">
				</p>
				<hr />
				<p class="submit">
					<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
				</p>

			</form>
			<?php
			echo '</h1>';
			echo '</div>';
		}
	}

	function get_college_scores( $atts, $content = "" ) {

		// Define the shortcode attributes and defaults
		$atts = shortcode_atts( array(
			'school'	=> 'Missouri'
		), $atts );

		// Create a variable to store the scores
		$final_data = array();

		// Get the data from the API and check to make sure it worked
		$year = date('Y');
		$endpoint = esc_url_raw( $this->api_endpoint ) . '?year=' .  $year . '&team=' . $atts['school'];
		$response = wp_safe_remote_get( $endpoint, array() );

		// Error check
		if ( is_wp_error( $response ) || empty( $response ) || $response['response']['code'] == '400' ) {
			return false;
		}

		// Get the 4 most recent games played this year
		$games = json_decode( $response['body'] );
		foreach ( $games as $key => $game ) {
			$date = strtotime( $game->start_date );

			if ( $date <= strtotime( 'now' ) ) {
				preg_match( '/[0-9]*-[0-9]*-[0-9]*/', $game->start_date, $formattted_date );
				$game->start_date = $formattted_date[0];
				$final_data[] = $game;
			}
			else {
				unset( $games[$key] );
			}
		}
		if ( count( $games ) > 4 ) {
			$final_data = array_slice($final_data, -4, 4, true);
		}

		// If there's not 4 games this year, go back and get what you need from last year
		if ( count( $games ) < 4 ) {
			$num_of_games_short = 4 - count( $games );
			$previous_year = $year - 1;
			$previous_year = wp_safe_remote_get(esc_url_raw( $this->api_endpoint ) . '?year=' .  $previous_year . '&team=' . $atts['school'], array());
			$previous_games = json_decode( $previous_year['body'] );
			$reduced_games = array_slice($previous_games, -$num_of_games_short, $num_of_games_short, true);

			foreach ( $reduced_games as $key => $game ) {
				preg_match( '/[0-9]*-[0-9]*-[0-9]*/', $game->start_date, $formattted_date );
				$game->start_date = $formattted_date[0];
				$final_data[] = $game;
			}
		}

		// Put them in the order that they happened
		function sort_by_date( $a, $b ) {
		    return strtotime($a->start_date) - strtotime($b->start_date);
		}
		usort($final_data, 'sort_by_date');

		// Save the html to a variable so we can return it
		$display = '';
		$display .= '<div class="mb-college-scores">';
			$display .= '<h2>' . $atts['school'] . '\'s last 4 games</h2>';
			$display .= '<table>';
				$display .= '<tr>';
					$display .= '<th>Date</th>';
					$display .= '<th>Guests</th>';
					$display .= '<th>Score</th>';
					$display .= '<th>Home</th>';
					$display .= '<th>Score</th>';
				$display .= '</tr>';
						foreach ( $final_data as $game ) {
							$home = '';
							$away = '';
							if ( $game->home_points > $game->away_points ) $home = 'winner';
							if ( $game->home_points < $game->away_points ) $away = 'winner';
							$display .= '<tr><td>' . $game->start_date .
							'</td><td class="' . $away . '">' . $game->away_team .
							'</td><td class="' . $away . '">' . $game->away_points .
							'</td><td class="' . $home . '">' . $game->home_team .
							'</td><td class="' . $home . '">' . $game->home_points .
							'</td></tr>';
						}
			$display .= '</table>';
		$display .= '</div>';

		// Return the completed html
		return $display;
	}

}

// Fire up the class
new CollegeScores( $config );
