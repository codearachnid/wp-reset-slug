<?php
/*
Plugin Name: Reset Slugs
Plugin URI: 
Description: Because sometimes you just want to revert your slugs to what WordPress thinks is best.
Version: 1.0
Author: Timothy Wood @codearachnid
Author URI: http://www.codearachnid.com
Text Domain: wprs
Domain Path: /lang/
License: GPL v3

WordPress Reset Slugs
Copyright (C) 2014, Timothy Wood - tim@imaginesimplicity.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Don't load directly
if ( !defined( 'ABSPATH' ) )
	die( '-1' );

add_action('admin_menu', 'wprs_add_menu');
function wprs_add_menu(){
	add_options_page( 
		'Reset Slugs', 
		'Reset Slugs', 
		'manage_options', 
		'wprs_options', 
		'wprs_option_page'
		);
}

add_action( 'admin_init', 'wprs_register_setting' );
function wprs_register_setting() {

	if ( !empty( $_REQUEST['settings-updated'] ) && $_REQUEST['settings-updated'] == true ) {
		$options = get_option('wprs_options');
		$post_types = !empty( $options['post_types'] ) ? (array) $options['post_types'] : array();
		if( wprs_sanitize_slugs( $post_types ) ){
			add_settings_error('general', 'slug_reset', __('Slugs reset.'), 'updated');
		} else {
			add_settings_error('general', 'slug_reset_error', __('There is an issue resetting the slugs.'), 'error');
		}
	}

	register_setting( 
		'wprs_options', 
		'wprs_options', 
		'wprs_validate_options' 
		);

	add_settings_section( 
		'wprs_main', 
		__('Configure:', 'wprs' ), 
		null, 
		'wprs_options' 
		);
	add_settings_field( 
		__( 'Select Post Types', 'wprs' ), 
		__( 'Select Post Types', 'wprs' ), 
		'wprs_field_post_types', 
		'wprs_options', 
		'wprs_main' 
		);
	
	add_settings_section( 
		'wprs_instructions', 
		__( 'WARNING:', 'wprs' ), 
		'wprs_instruct_section_text', 
		'wprs_options' 
		);
	add_settings_field( 
		'wprs_agree_before_run', 
		'', 
		'wprs_field_agree_before_run', 
		'wprs_options', 
		'wprs_instructions' 
		);
} 

function wprs_field_post_types(){
	$options = get_option('wprs_options');
	$post_types = !empty( $options['post_types'] ) ? (array) $options['post_types'] : array();
	echo '<ul>';
	foreach( get_post_types() as $post_type ) {
		?><li>
				<label>
				<input id='wprs_reset_post_types' name='wprs_options[post_types][<?php echo $post_type; ?>]' type='checkbox' value='1' <?php checked( in_array( $post_type, $post_types ) ); ?> />
				<?php echo ucwords( strtolower( str_replace( '-', ' ', str_replace( '_', ' ', $post_type ) ) ) ); ?></label>
		</li><?php
	}
	echo '</ul>';
}

function wprs_validate_options($input) {
	$input['post_types'] = array_keys( $input['post_types'] );
	return $input;
}

function wprs_instruct_section_text(){
	?><p><?php _e( 'Please understand that you will not be able to recover changes after proceeding. This can potentially adversely affect your SEO, site functionality and performance. You assume all risk and reward to your site based on the actions of this plugin.', 'wprs' ); ?></p><?php
}
function wprs_field_agree_before_run(){
	?>
	<label>
		<input type="checkbox" id="wprs_options_agree_before_run" name="wprs_options[agree_before_run]" value="<?php echo current_time('mysql'); ?>" />
		<?php _e( 'I understand the risks and agree to continue.', 'wprs' ); ?>
	</label>
	<script type="text/javascript">
		jQuery('form').submit(function( event ){
			// On submit disable if agreement is not checked
			if( ! jQuery("#wprs_options_agree_before_run").is(":checked") ) {
				event.preventDefault();
				alert( "<?php _e( 'You must agree to the understanding the warning before proceeding.', 'wprs' ); ?>" );
			}
		});
	</script>
	<?php
}

function wprs_option_page(){
	?><div class="wrap">
		<h2><?php _e( 'Reset Slugs', 'wprs' ); ?></h2>
		<h4><?php _e( 'Because sometimes you just want to revert your slugs to what WordPress thinks is best.', 'wprs' ); ?></h4>
		<form action="options.php" method="post">
			<input type="hidden" name="sanitize_slugs" value="1" />
			<?php 

			settings_fields( 'wprs_options' ); 
			do_settings_sections( 'wprs_options' );
			submit_button( __('Reset Slugs', 'wprs' ) );

			?>
		</form>
	</div><?php
}


/**
 * Reset the slugs on a particular post type to use the default sanatized slugs
 * @param  string  $post_type         filter by post type
 * @param  int $offset                set the paginated post to start
 * @param  boolean $force_guid_update * WARNING * never enable this
 * 
 */
function wprs_sanitize_slugs( $post_type = null, $offset = 0, $force_guid_update = false ){

	global $wpdb;

	if( empty($post_type) )
		return;

	$get_post_args = array(
		'post_type' => $post_type, 
		'post_status' => 'any'
		);

	if( $offset == 0 ){
		$get_post_args['posts_per_page'] = -1;
	} else {
		$get_post_args['offset'] = $offset;
	}

	$posts_to_fix = new WP_Query( $get_post_args );

	foreach( $posts_to_fix->posts as $post_to_fix ){
		$wpdb->update( 
			$wpdb->posts, 
			array( 'post_name' => sanitize_title( $post_to_fix->post_title ) ), 
			array( 'ID' => $post_to_fix->ID ), 
			array( '%s' ), 
			array( '%d' ) 
		);
	}

	// you should really leave this alone (do not set to true unless you need to)
	//http://codex.wordpress.org/Changing_The_Site_URL#Important_GUID_Note
	if( $force_guid_update ){
		$siteurl = trailingslashit( get_option('siteurl') );
		$wpdb->query( "UPDATE {$wpdb->posts} SET guid = CONCAT( '{$siteurl}?p=', ID ) WHERE post_type = {$post_type} OR post_type = 'revision';" );
	}
	
	return true;
}
