<?php
/*
Plugin Name: JQuery Custom Post Type Archive Widget
Plugin URI: TODO http://wpadventures.wordpress.com/plugins/wordpress-com-google-maps-shortcode/
Description: Adds a widget which can be used in sidebars. Displays archives by year in a JQuery accordion with a specified custom post type.
Version: 1.0
Author: fonglh
Author URI: https://wpadventures.wordpress.com
License: GPLv2
*/

/*  Copyright 2011  fonglh  (email : fonglh@msn.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/**
 * Originally from the Custom Post Types plugin
 * Hooks into 'getarchives_where' filter to change the WHERE constraint to support post type filtering.
 * Will replace "post_type = 'post'" with "post_type = '{POST_TYPE}'". 
 * That part will be removed if 'post_type' in $options is set to 'all'.
 * @param string $where the WHERE constraint
 * @param array $options options that are passed to the 'wp_get_archives' function
 * @return string the modified (or not) WHERE constraint
 */
function pta_wp_get_archives_filter($where, $options) {
	if(!isset($options['post_type'])) return $where; // OK - this is regular wp_get_archives call - don't do anything
	
	global $wpdb; // get the DB engine
	
	$post_type = $wpdb->escape($options['post_type']); // escape the passed value to be SQL safe
	if($post_type == 'all') $post_type = ''; // if we want to have archives for all post types
	else $post_type = "post_type = '$post_type' AND"; // otherwise just for specific one
	
	$where = str_replace('post_type = \'post\' AND', $post_type, $where);
	
	return $where;
}
add_filter('getarchives_where', 'pta_wp_get_archives_filter', 10, 2);


/**
 * This function is a wrapper for 'wp_get_archives' function to support post type filtering.
 * It's necessary to have this function so that the links could be fixed to link to proper archives.
 * This needs to be done here, because WordPress lacks hooks in 'wp_get_archives' function.
 * The links won't be changed if post type is 'all' or type in options is 'postbypost' or 'alpha'.
 *
 * In addition, this function has been modified to work with multi-site. Since multi-site adds blog/
 * to the URL for normal posts, this has to be taken out with the regex.
 * 
 * @param string $post_type post type to get archives from. Or you can use 'all' to include all archives.
 * @param array $args optional args. You can use the same options as in 'wp_get_archives' function.
 * @return string the HTML with correct links if 'echo' option is false. Otherwise will echo that.
 * @see wp_get_archives
 * @link http://codex.wordpress.org/Function_Reference/wp_get_archives
 */
function mbpc_get_post_type_archives($post_type, $args = array()) {
	$echo = isset($args['echo']) ? $args['echo'] : true;
	$type = isset($args['type']) ? $args['type'] : 'monthly';
	
	$args['post_type'] = $post_type;
	$args['echo'] = false;
	
	$html = wp_get_archives($args); // let WP do the hard stuff
	
	if($post_type != 'all' and $type != 'postbypost' and $type != 'alpha') {
		$pattern = get_bloginfo('url') . '/blog/';
		$replacement = get_bloginfo('url') . '/' . $post_type .'/';
		
		$html = str_replace($pattern, $replacement, $html);
	}

	//get an array of all the years
	//the generated html is in reverse chronological order
	//this can be used to determine when to insert a year header
	preg_match_all("|<li><a .*>.*([0-9]{4}).*</a></li>|", $html, $out, PREG_PATTERN_ORDER);

	$html = "";
	$year_counts = array();

	//counts the number of months with archives for each year
	foreach ( $out[1] as $entry ) {
		if ( isset( $year_counts[ $entry ] ) ) {		//increment count for the year
			$year_counts[ $entry ] = $year_counts[ $entry ] + 1;
		}
		else {
			$year_counts[ $entry ] = 1;					//1st time this year is encountered
		}
	}

	$years = array_keys( $year_counts );
	$counts = array_values( $year_counts );
	$i = 0;
	$k = 0;

	foreach ( $years as $year ) {
		$html .= '<h3>' . $year . '</h3><div><ul class="accordion-list-content">';		//create a heading for each year
		for ( $j = 0; $j < $counts[ $i ]; $j++) {		//create an entry for each month in the year
			$html .= $out[ 0 ][ $k ];
			$k++;
		}
		$i++;
		$html .= '</ul></div>';
	}
	
	if($echo)
		echo $html;
	else
		return $html;
}

function enqueue_accordion_script() {
	wp_register_script( 'enqueue-accordion-script', get_stylesheet_directory_uri() . '/scripts/jquery-ui-1.8.13.custom.min.js',
						array( 'jquery' ) );
	wp_enqueue_script( 'enqueue-accordion-script' );
}

add_action( 'init', 'enqueue_accordion_script' );

function add_jquery_accordion() {
	wp_enqueue_script( 'add-jquery-accordion', get_stylesheet_directory_uri() . '/scripts/jquery-accordion-init.js', 
						array( 'jquery', 'enqueue-accordion-script' ) );
}
add_action( 'init', 'add_jquery_accordion' );

/**
 * Archives widget class
 * Modified to use custom post type defined in widget
 *
 * Pre-reqs: Custom Post Type Plugin, any post type (declared above)
 * @since 3.0.5
 */
class WP_Widget_Custom_Post_Type_Archives extends WP_Widget {

	function WP_Widget_Custom_Post_Type_Archives() {
		$widget_ops = array('classname' => 'widget_custom_post_type_archive', 'description' => __( 'A monthly archive of your specified post type') );
		$this->WP_Widget('custom_post_type_archives', __('Custom Post Type Archives'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract($args);
		$c = $instance['count'] ? '1' : '0';
		$d = $instance['dropdown'] ? '1' : '0';
		$title = apply_filters('widget_title', empty($instance['title']) ? __('Sermon Archives') : $instance['title'], $instance, $this->id_base);
		$post_type = $instance['posttype'];

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;

		if ( $d ) {
?>
		<select name="custom-post-type-archive-dropdown" onchange='document.location.href=this.options[this.selectedIndex].value;'> <option value=""><?php echo esc_attr(__('Select Month')); ?></option> <?php mbpc_get_post_type_archives($post_type,apply_filters('widget_custom_post_type_archives_dropdown_args', array('type' => 'monthly', 'format' => 'option', 'show_post_count' => $c))); ?> </select>
<?php
		} else {
?>
		<div id="accordion">
		<?php mbpc_get_post_type_archives($post_type,apply_filters('widget_custom_post_type_archives_args', array('type' => 'monthly', 'show_post_count' => $c))); ?>
		</div>
<?php
		}

		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$new_instance = wp_parse_args( (array) $new_instance, array( 'title' => '', 'count' => 0, 'dropdown' => '','posttype' => '') );
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['posttype'] = strip_tags($new_instance['posttype']);
		$instance['count'] = $new_instance['count'] ? 1 : 0;
		$instance['dropdown'] = $new_instance['dropdown'] ? 1 : 0;

		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'count' => 0, 'dropdown' => '', 'posttype' => '') );
		$title = strip_tags($instance['title']);
		$post_type = strip_tags($instance['posttype']);
		$count = $instance['count'] ? 'checked="checked"' : '';
		$dropdown = $instance['dropdown'] ? 'checked="checked"' : '';
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('posttype'); ?>"><?php _e('Custom Post Type:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('posttype'); ?>" name="<?php echo $this->get_field_name('posttype'); ?>" type="text" value="<?php echo esc_attr($post_type); ?>" /></p>
		<p>
			<input class="checkbox" type="checkbox" <?php echo $count; ?> id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" /> <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Show post counts'); ?></label>
			<br />
			<input class="checkbox" type="checkbox" <?php echo $dropdown; ?> id="<?php echo $this->get_field_id('dropdown'); ?>" name="<?php echo $this->get_field_name('dropdown'); ?>" /> <label for="<?php echo $this->get_field_id('dropdown'); ?>"><?php _e('Display as a drop down'); ?></label>
		</p>
<?php
	}
}

add_action('widgets_init', create_function('', 'return register_widget("WP_Widget_Custom_Post_Type_Archives");'));

