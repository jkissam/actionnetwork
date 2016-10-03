<?php
	
	/**
 * Create WP_List_Table for actions
 */
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class Actionnetwork_Action_List extends WP_List_Table {

	// Notices
	public $notices = array();
	public $action_types = array();
	public $action_type_plurals = array();

	// Class constructor
	function __construct() {

		parent::__construct( array(
			'singular' => __( 'Action', 'actionnetwork' ),
			'plural' => __( 'Actions', 'actionnetwork' ),
			'ajax' => false,
		) );

		$this->notices = array(
			'error' => array(),
			'updated' => array(),
		);

		$this->action_types = array(
			'petition' => __( 'Petition', 'actionnetwork' ),
			'advocacy_campaign' => __( 'Advocacy Campaign', 'actionnetwork' ),
			// 'letter' => __( 'Letter Campaign', 'actionnetwork' ),
			'event' => __( 'Event', 'actionnetwork' ),
			'ticketed_event' => __( 'Ticketed Event', 'actionnetwork' ),
			'fundraising_page' => __( 'Fundraising Page', 'actionnetwork' ),
			'form' => __( 'Form', 'actionnetwork' ),
		);
		$this->action_type_plurals = array(
			'petition' => __( 'Petitions', 'actionnetwork' ),
			'advocacy_campaign' => __( 'Advocacy Campaigns', 'actionnetwork' ),
			// 'letter' => __( 'Letter Campaigns', 'actionnetwork' ),
			'event' => __( 'Events', 'actionnetwork' ),
			'ticketed_event' => __( 'Ticketed Events', 'actionnetwork' ),
			'fundraising_page' => __( 'Fundraising Pages', 'actionnetwork' ),
			'form' => __( 'Forms', 'actionnetwork' ),
		);

	}

	function get_columns() {

		$columns = array(
			'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
			'title' => __( 'Title', 'actionnetwork' ),
			'type' => __( 'Type', 'actionnetwork' ),
			'modified_date' => __( 'Last Modified', 'actionnetwork' ),
			'shortcode' => __( 'Shortcode', 'actionnetwork' ),
		);

		if (get_option( 'actionnetwork_api_key', null )) {
			$columns['meta'] = __( 'Meta Information', 'actionnetwork' );
		}

		return $columns;
	}

    function get_sortable_columns() {
   	    $sortable_columns = array(
   	        'title'    => array('title',false),
   	        'type'     => array('type',false),
   	        'modified_date' => array('modified_date',true),
		);
        return $sortable_columns;
    }

	function single_row($item) {
		echo '<tr class="actionnetwork-action-' . ($item['enabled'] ? 'enabled' : 'hidden') . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

    function column_default($item, $column_name){
        switch($column_name){

			case 'type':
                return $this->action_types[$item[$column_name]];
			break;
			
			case 'modified_date':
				return $item[$column_name] ? date('n/j/Y g:ia', $item[$column_name]) : '';
			break;

            default:
                return print_r($item,true); //Show the whole array for troubleshooting purposes
			break;
        }
    }

    function column_cb($item){
		// we disable this for items which have an action network ID,
		// because they are controlled by the Action Network backend
		// and synced via API
        return sprintf(
            '<input type="checkbox" name="bulk-action[]" value="%1$s" %2$s/>',
            $item['wp_id'],
			$item['an_id'] ? 'disabled="disabled" title="'.__( 'Cannot perform bulk actions on actions synced via API', 'actionnetwork' ).'" ' : ''
        );
    }

	function column_title($item) {
		if ($item['name'] && ($item['name'] != $item['title'])) {
			$title = $item['name'] . ' <div class="public-title"><span>' . __('Public Title', 'actionnetwork') . ':</span><br />'.$item['title'].'</div>';
		} else {
			$title = $item['title'];
		}
		if (!$item['enabled']) {
			$title = '<span class="dashicons dashicons-hidden" title="'.__('This action is hidden', 'actionnetwork').'"></span> '.$title;
		}
		return $title;
	}

	function column_shortcode($item) {
		$shortcode = "[actionnetwork id={$item['wp_id']}]";
		$__copy = __( 'Copy', 'actionnetwork' );
		return <<<EOHTML
<div class="copy-wrapper">
<input type="text" class="copy-text" readonly="readonly" id="shortcode-{$item['wp_id']}" value="$shortcode" /><button data-copytarget="#shortcode-{$item['wp_id']}" class="copy">$__copy</button>
</div>
EOHTML;
	}

	function column_meta($item) {
		$info = '';
		if ($item['browser_url']) {
			$info .= '<div><a href="' . $item['browser_url'] . '" target="_blank"><span class="dashicons dashicons-external"></span> ' . __( 'View action', 'actionnetwork' ) . '</a></div>';
			$info .= '<div><a href="' . $item['browser_url'] . '/manage" target="_blank"><span class="dashicons dashicons-admin-tools"></span> ' . __( 'Manage action', 'actionnetwork' ) . '</a></div>';
		}
		if ($item['start_date']) {
			$info .= '<div><span class="dashicons dashicons-calendar"></span> '.date('n/j/Y g:ia', $item['start_date']).'</div>';
		}
		$source = $item['an_id'] ? 'API' : __( 'User', 'actionnetwork' );
		$source_icon = $item['an_id'] ? '<img class="icon" src="'.plugins_url('../icon-action-network.png', __FILE__).'" /> ' : '<span class="dashicons dashicons-admin-users"></span> ';
		$info .= '<div>' . $source_icon . __('Source', 'actionnetwork') . ': '. $source . '</div>';
/*
		if ($item['created_date']) {
			$info .= '<div>'.__('Created','actionnetwork').': '.date('n/j/Y', $item['created_date']).'</div>';
		}
*/
		return $info;
	}

	function filter_sql() {

		$filter_conditions = array();

		if ( !empty( $_REQUEST['type'] ) ) {
			$type = $_REQUEST['type'];
			
			if ( array_key_exists( $type, $this->action_types ) ) {
				$filter_conditions[] = "type = '".$type."'";
			}
		}

		if ( !empty( $_REQUEST['source'] ) ) {
			
			if ( $_REQUEST['source'] == 'user' ) {
				$filter_conditions[] = "an_id = ''";
			} elseif ( $_REQUEST['source'] == 'api' ) {
				$filter_conditions[] = "an_id != ''";
			}
		}

		if ( !empty( $_REQUEST['search'] ) ) {
			$search = esc_sql($_REQUEST['search']);
			$filter_conditions[] = "( (title LIKE '%$search%') OR (name LIKE '%$search%') )";
		}

		if ( empty( $_REQUEST['show_hidden']) ) {
			$filter_conditions[] = "enabled = 1";
		}

		$filter = '';
		if (count($filter_conditions)) {
			$filter .= " WHERE " . implode( " AND ", $filter_conditions );
		}

		return $filter;
	}

	function get_actions( $per_page = 20, $page_number = 1 ) {

		global $wpdb;
		$sql = "SELECT * FROM {$wpdb->prefix}actionnetwork";

		$sql .= self::filter_sql();

		if ( !empty( $_REQUEST['orderby'] ) ) {
			$sql .= " ORDER BY " . esc_sql( $_REQUEST['orderby'] );
			$sql .= !empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		} else {
			$sql .= " ORDER BY modified_date DESC";
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ($page_number - 1) * $per_page;

		$result = $wpdb->get_results( $sql, 'ARRAY_A' );
		return $result;
	}

	function record_count() {
		global $wpdb;
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}actionnetwork";
		$sql .= self::filter_sql();
		return $wpdb->get_var( $sql );
	}

	function delete_action( $wp_id ) {
		global $wpdb;
		$wpdb->delete(
			"{$wpdb->prefix}actionnetwork",
			array( 'wp_id' => $wp_id ),
			array( '%d' )
		);
	}

	function hide_action( $wp_id ) {
		global $wpdb;
		$wpdb->update(
			"{$wpdb->prefix}actionnetwork",
			array( 'enabled' => 0 ),
			array( 'wp_id' => $wp_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	function unhide_action( $wp_id ) {
		global $wpdb;
		$wpdb->update(
			"{$wpdb->prefix}actionnetwork",
			array( 'enabled' => 1 ),
			array( 'wp_id' => $wp_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	function no_items() {
		_e( 'No actions available', 'actionnetwork' );
	}

    function get_bulk_actions() {
        $actions = array(
            'delete'    => __( 'Delete', 'actionnetwork' ),
            'hide'    => __( 'Hide', 'actionnetwork' ),
            'unhide'    => __( 'Unhide', 'actionnetwork' ),
        );
        return $actions;
    }

    function process_bulk_action() {
        
        if( 'delete'===$this->current_action() ) {
			$delete_wp_ids = esc_sql( $_REQUEST['bulk-action'] );
			foreach ( $delete_wp_ids as $wp_id ) {
				self::delete_action( $wp_id );
			}
			$this->notices['updated'][] = __('Actions deleted', 'actionnetwork');
        }
        
        if( 'hide'===$this->current_action() ) {
			$hide_wp_ids = esc_sql( $_REQUEST['bulk-action'] );
			foreach ( $hide_wp_ids as $wp_id ) {
				self::hide_action( $wp_id );
			}
			$this->notices['updated'][] = __('Actions hidden', 'actionnetwork');
        }
        
        if( 'unhide'===$this->current_action() ) {
			$unhide_wp_ids = esc_sql( $_REQUEST['bulk-action'] );
			foreach ( $unhide_wp_ids as $wp_id ) {
				self::unhide_action( $wp_id );
			}
			$this->notices['updated'][] = __('Actions unhidden', 'actionnetwork');
        }
        
    }

	function extra_tablenav( $which ) {

		global $wpdb;

		if ( $which == 'top' ) {

			$type_options = '';
			$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
			foreach ($this->action_type_plurals as $key => $plural) {
				$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}actionnetwork WHERE type='$key'";
				$count = $wpdb->get_var( $sql );
				$type_options .= "<option value=\"$key\"";
				$type_options .= selected( $type, $key, false );
				$type_options .= disabled( ($count == 0), true, false );
				$type_options .= ">$plural</option>\n";
			}

			?>
			<div class="alignleft actions">
				<label for="actionnetwork-filter-by-type" class="screen-reader-text"><?php _e('Filter by type', 'actionnetwork'); ?></label>
				<select name="type" id="actionnetwork-filter-by-type">
					<option value=""><?php _e('All Types', 'actionnetwork'); ?></option>
					<?php echo $type_options; ?>
				</select>
				<?php if ( get_option( 'actionnetwork_api_key', null ) ):
					$source = isset($_REQUEST['source']) ? $_REQUEST['source'] : '';
				?>
				<label for="actionnetwork-filter-by-source" class="screen-reader-text"><?php _e('Filter by source', 'actionnetwork'); ?></label>
				<select name="source" id="actionnetwork-filter-by-source">
					<option value=""><?php _e('All Sources', 'actionnetwork'); ?></option>
					<option value="user"<?php selected( $source, 'user' ); ?>><?php _e('User', 'actionnetwork'); ?></option>
					<option value="api"<?php selected( $source, 'api' ); ?>>API</option>
				</select>
				<?php endif; ?>
				
				<label for="actionnetwork-show-hidden" class="checkbox-label">
					<input type="checkbox" id="actionnetwork-show-hidden" name="show_hidden" value="1" <?php checked( isset($_REQUEST['show_hidden']) ? $_REQUEST['show_hidden'] : 0, 1 ); ?> />
					<?php _e( 'Show hidden', 'actionnetwork' ); ?>
				</label>

				<input type="submit" name="filter_action" id="actionnetwork-filter-submit" class="button" value="<?php _e('Filter', 'actionnetwork'); ?>">
			</div>
			<?php

		}

	}

	function prepare_items() {

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

		// process bulk action
		$this->process_bulk_action();

		$per_page = $this->get_items_per_page( 'actions_per_page', 20 );
		$current_page = $this->get_pagenum();
		$total_items = self::record_count();

		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page' => $per_page,
		));

		$this->items = self::get_actions( $per_page, $current_page );
	}

}
