<?php
/*
* Plugin Name: Recently Edited Posts Widget
* Description: Shows the latest posts and pages edited.
* Author: Sean Leavey
* Version: 0.9
* Author URI: http://attackllama.com/
* Plugin URI: http://github.com/SeanDS/
* License: GPL2
*/

// don't load directly
if ( ! function_exists('is_admin')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
	
	exit;
}

/* Function that registers our widget. */
function recently_edited_posts_init() {
	register_widget( 'recently_edited_posts_Widget' );
}

/* Add our function to the widgets_init hook. */
add_action('widgets_init', 'recently_edited_posts_init' );

if (is_admin()) {
	add_action('post_updated', 'recently_edited_posts_delete_transient');
	
	function recently_edited_posts_delete_transient($attachment_id) {
		global $wpdb;
		
		delete_transient('widget_recently_edited_posts');
		
		$wpdb->query('OPTIMIZE TABLE ' . $wpdb->options);
	}
}

class recently_edited_posts_Widget extends WP_Widget {
	function recently_edited_posts_Widget() {
		/* Widget settings. */
		$widget_ops = array(
			"classname" => 'recently_edited_posts',
			"description" => 'The latest posts and pages edited'
		);
		
		/* Create the widget. */
		$this->WP_Widget('recently_edited_posts', 'Recently Edited Posts', $widget_ops);
	}
	
	function widget( $args, $instance ) {
		global $wpdb;
		extract( $args );

		/* User-selected settings. */
		$title = apply_filters('widget_title', $instance['title'] );
		$nb_display = $instance['nb_display'];

		/* Before widget (defined by themes). */
		echo $before_widget;

		/* Title of widget (before and after defined by themes). */
		if ($title) {
			echo $before_title . $title . $after_title;
		}
			
		echo recently_edited_posts_transient($nb_display, $instance);
		
		/* After widget (defined by themes). */
		?><div class="clear"></div><?php 
		echo $after_widget;
	}
	
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		
		if (isset($new_instance)) delete_transient('widget_recently_edited_posts');
		
		$instance['title'] = sanitize_text_field($new_instance['title']);
		$instance['nb_display'] = (isset($new_instance['nb_display']) ? absint($new_instance['nb_display']) : 5);
		
		return $instance;
	}
	
	function form($instance) {
		global $wpdb;
		
		$title = esc_attr($instance['title']);
		$nb_display = esc_attr($instance['nb_display']);
		
		?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">
                <?php echo 'Title:'; ?>
                <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('nb_display'); ?>">
                <?php echo 'Number:'; ?>
                <input class="widefat" id="<?php echo $this->get_field_id('nb_display'); ?>" name="<?php echo $this->get_field_name('nb_display'); ?>" type="text" value="<?php echo $nb_display; ?>" />
            </label>
        </p>
	<?php
	}	
}

function recently_edited_posts_transient($nb_display, $instance) {
	$transient = get_transient('widget_recently_edited_posts');
	
	if ($transient === false) {
		$value = get_recently_edited_posts($nb_display, $instance);
		
		set_transient('widget_recently_edited_posts', $value);
		
		$transient = get_transient('widget_recently_edited_posts');
	}
	
	return $transient; 
}

function get_recently_edited_posts($nb_display, $instance) {
	global $wpdb;
	
	$nb_display = intval($nb_display);
	
	$last_update = $wpdb->get_results("
		SELECT post_modified, post_title, id
		FROM $wpdb->posts
		WHERE post_type <> 'revision' AND post_type <> 'attachment' AND post_type <> 'nav_menu_item' AND post_type <> 'tablepress_table' AND post_status = 'publish'
		ORDER BY post_modified DESC
		LIMIT $nb_display
	");
	
	$display= '<ul>';

	foreach ($last_update as $post) {
		$date = date_i18n(get_option('date_format'), strtotime($post->post_modified));
			
		$display .= "<li><a href=" . get_permalink($post->id) . ">" . $post->post_title . "</a><span style='white-space: nowrap;'> $date</span></li>";
	}
	
	$display .= "<li>Hi!</li>";
		
	$display .= '</ul>';
	
	return $display;
}