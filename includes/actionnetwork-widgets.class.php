<?php
	
class ActionNetwork_Calendar_Widget extends WP_Widget {
	
	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		$widgets_ops = array(
			'classname' => 'actionnetwork_calendar_widget',
			'description' => 'Displays list of upcoming Action Network events',
		);
		parent::__construct( 'actionnetwork_calendar_widget', 'Action Network Calendar', $widgets_ops );
	}
	
	/**
	 * Output the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if (!empty( $instance['title'])) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title']) . $args ['after_title'];
		}
		$shortcode = '[actionnetwork_calendar ignore_url_id="1"';
		$shortcode_args = array(
			'n',
			'no_events',
			'location',
			'description',
			'date_format',
			'link_format',
			'link_text',
			'container_element',
			'container_class',
			'item_element',
			'item_class',
		);
		foreach ($shortcode_args as $arg) {
			if (isset($instance[$arg]) && $instance[$arg]) {
				$shortcode .= " $arg=\"".$instance[$arg].'"';
			}
		}
		$shortcode .= ']';
		if ( isset($instance['twig']) && $instance['twig'] ) {
			$shortcode .= $instance['twig'] . '[/actionnetwork_calendar]';
		}
		echo do_shortcode( $shortcode );
		if ( isset($instance['footer']) && $instance['footer'] ) {
			echo wpautop( $instance['footer'] );
		}
		echo $args['after_widget'];
	}
	
	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {
		// outputs the options form on admin
		$args = array(
			'title' => array(
				'label' => __('Widget Title', 'actionnetwork'),
				'type' => 'text',
				'default' => __('Upcoming Events', 'actionnetwork'),
				'advanced' => false,
			),
			'n' => array(
				'label' => __('Number of Events to display', 'actionnetwork'),
				'type' => 'number',
				'default' => 3,
				'advanced' => false,
				'description' => __( 'Set to zero to display all available events', 'actionnetwork' ),
			),
			'date_format' => array(
				'label' => __('Date format', 'actionnetwork'),
				'type' => 'text',
				'advanced' => false,
				'classes' => 'widget-control-code',
				'description' => __( 'Formatting string for date. Leave blank to default to F j, Y.', 'actionnetwork' ) . ' <a href="http://php.net/date" target="_blank">' . __( 'Date format documentation', 'actionnetwork' ) . '</a>',
			),
			'link_text' => array(
				'label' => __('Link text', 'actionnetwork'),
				'type' => 'text',
				'advanced' => false,
				'classes' => 'widget-control-code',
				'description' => __( 'Template for text of link. Uses twig-like tokens for title and date. Leave blank to default to <code>{{ event.date }}: {{ event.title }}</code>', 'actionnetwork' ),
			),
			'link_format' => array(
				'label' => __('Link format', 'actionnetwork'),
				'type' => 'text',
				'advanced' => false,
				'classes' => 'widget-control-code',
				'description' => __( 'If left blank, will link to event page on Action Network. Otherwise, use <code>{{ event.id }}</code> token to link to a page that has the [actionnetwork_calendar] shortcode on it (i.e., something like <code>/calendar/{{ event.id }}</code>), which will then display the event. This is the only way to link to non-API-synced events.', 'actionnetwork' ),
			),
			'location' => array(
				'label' => __('Display location', 'actionnetwork'),
				'type' => 'checkbox',
				'advanced' => false,
			),
			'description' => array(
				'label' => __('Display description', 'actionnetwork'),
				'type' => 'checkbox',
				'advanced' => false,
			),
			'no_events' => array(
				'label' => __('No Events text', 'actionnetwork'),
				'type' => 'text',
				'advanced' => false,
				'description' => __( 'Text to display if there are no upcoming events. Leave blank to default to "No upcoming events." Accepts HTML.', 'actionnetwork' ),
			),
			'footer' => array(
				'label' => __('Footer', 'actionnetwork'),
				'type' => 'textarea',
				'advanced' => false,
				'description' => __( 'Text to display after list of events (most useful to link to a full calendar page that has the <code>[actionnetwork_calendar]</code> shortcode on it). <code>wpautop</code> will be applied. Accepts HTML.', 'actionnetwork' ),
			),
			'container_element' => array(
				'label' => __('Container Element', 'actionnetwork'),
				'type' => 'text',
				'advanced' => true,
				'classes' => 'widget-control-code',
				'description' => __( 'HTML element (without angle brackets) to contain the list. If left blank, will default to <code>ul</code>', 'actionnetwork' ),
			),
			'container_class' => array(
				'label' => __('Container Class', 'actionnetwork'),
				'type' => 'text',
				'advanced' => true,
				'classes' => 'widget-control-code',
				'description' => __( 'Class to be applied to list container element. If left blank, will default to <code>actionnetwork-calendar</code>', 'actionnetwork' ),
			),
			'item_element' => array(
				'label' => __('Item Element', 'actionnetwork'),
				'type' => 'text',
				'advanced' => true,
				'classes' => 'widget-control-code',
				'description' => __( 'HTML element (without angle brackets) for each item in the list. If left blank, will default to <code>li</code>', 'actionnetwork' ),
			),
			'item_class' => array(
				'label' => __('Item Class', 'actionnetwork'),
				'type' => 'text',
				'advanced' => true,
				'classes' => 'widget-control-code',
				'description' => __( 'Class to be applied to list item element. If left blank, will default to <code>actionnetwork-calendar-item</code>', 'actionnetwork' ),
			),
			'twig' => array(
				'label' => __('Twig template', 'actionnetwork'),
				'type' => 'textarea',
				'advanced' => true,
				'classes' => 'widget-control-code',
				'description' => __( "Twig-style template for widget. Overrides Link Text and all other advanced settings\n\nMust have control structure <code>{% for event in events %} {% else %} {% endfor %}</code>.\n\nAvailable tokens are <code>{{ event.title }}</code>, <code>{{ event.date }}</code>, <code>{{ event.link }}</code>, <code>{{ event.id }}</code>, <code>{{ event.location }}</code> and <code>{{ event.description }}</code>", 'actionnetwork' ),
			),
		);
		
		wp_enqueue_style( 'actionnetwork-widget-css', plugins_url('../widget-controls.css', __FILE__) );
		wp_register_script( 'actionnetwork-widget-js', plugins_url('../widget-controls.js', __FILE__) );
		$translation_array = array(
			'showAdvanced' => __( 'Show Advanced Controls', 'actionnetwork' ),
		);
		wp_localize_script( 'actionnetwork-widget-js', 'widgetcontrolText', $translation_array );
		wp_enqueue_script( 'actionnetwork-widget-js' );
		
		$output = '<ul class="widget-controls">';
		foreach ($args as $arg => $arg_attr) {
			$classes = array(
				'widget-control',	
			);
			if ( isset($arg_attr['advanced']) && $arg_attr['advanced'] ? ' class="widget-control-advanced"' : '' ) { $classes[] = 'widget-control-advanced'; }
			if ( isset($arg_attr['classes']) && $arg_attr['classes'] ) { $classes[] = $arg_attr['classes']; }
			$output .= '<li class="' . implode(' ', $classes) . '">';
			$id = esc_attr($this->get_field_id( $arg ));
			$name = esc_attr($this->get_field_name( $arg ));
			$type = isset($arg_attr['type']) ? $arg_attr['type'] : 'text';
			$label = isset($arg_attr['label']) ? '<label for="' . $id . '">' . $arg_attr['label'] . '</label>' : '';
			$value = isset( $instance[$arg] ) ? $instance[$arg] : (isset($arg_attr['default']) ? $arg_attr['default'] : '');
			switch ($arg_attr['type']) {
				case 'text':
					$output .= $label . ' <input class="widefat" id="'.$id.'" name="'.$name.'" type="text" value="'.$value.'">';
				break;
				
				case 'number':
					$value = (int) $value;
					$output .= $label . ' <input id="'.$id.'" name="'.$name.'" type="number" step="1" min="0" class="tiny-text" value="'.$value.'">';
				break;
				
				case 'checkbox':
					$checked = $value ? ' checked="checked"' : '';
					$output .= '<input type="checkbox" id="'.$id.'" name="'.$name.'" value="1"'.$checked.'> ' . $label;
				break;
				
				case 'textarea':
					$output .= $label . '<textarea class="widefat" id="'.$id.'" name="'.$name.'">'.$value.'</textarea>';
				break;
			}
			$output .= isset($arg_attr['description']) ? '<div class="widget-control-description">' . wpautop($arg_attr['description']) . '</div>' : '';
			$output .= '</li>';
		}
		$output .= '</ul>';
		
		echo $output;
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$sanitize_args = array(
			'title',
			'n',
			'location',
			'description',
			'date_format',
			'link_format',
			'container_element',
			'container_class',
			'item_element',
			'item_class',
		);
		foreach ($sanitize_args as $arg) {
			$instance[$arg] = isset($new_instance[$arg]) ? esc_html($new_instance[$arg]) : '';
		}
		$html_args = array(
			'no_events',
			'footer',
			'link_text',
			'twig',
		);
		foreach ($html_args as $arg) {
			$instance[$arg] = isset($new_instance[$arg]) ? $new_instance[$arg] : '';
		}
		return $instance;
	}
	
}