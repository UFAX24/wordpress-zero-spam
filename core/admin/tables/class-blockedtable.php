<?php
/**
 * Blocked table class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core\Admin\Tables;

use ZeroSpam;
use WP_List_Table;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Log table.
 *
 * @since 5.0.0
 */
class BlockedTable extends WP_List_Table {

	/**
	 * Log table constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		global $status, $page;

		$args = array(
			'singular' => __( 'WordPress Zero Spam Blocked IP', 'zerospam' ),
			'plural'   => __( 'WordPress Zero Spam Blocked IPs', 'zerospam' ),
		);
		parent::__construct( $args );
	}

	/**
	 * Column values.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'user_ip':
				return '<a href="https://www.zerospam.org/ip-lookup/' . urlencode( $item[ $column_name ] ) .'" target="_blank" rel="noopener noreferrer">' . $item[ $column_name ] . '</a>';
				break;
			case 'date_added':
			case 'start_block':
			case 'end_block':
				if (
					! empty( $item['blocked_type'] ) &&
					'permanent' ===  $item['blocked_type'] &&
					'end_block' === $column_name
				) {
					return 'N/A';
				}

				if ( empty( $item[ $column_name ] ) || '0000-00-00 00:00:00' === $item[ $column_name ] ) {
					return 'N/A';
				} else {
					return gmdate( 'M j, Y g:ia' , strtotime( $item[ $column_name ] ) );
				}
				break;
			case 'actions':
				ob_start();
					?>
					<button
						class="button zerospam-block-trigger"
						data-ip="<?php echo esc_attr( $item['user_ip'] ); ?>"
						data-reason="<?php echo esc_attr( $item['reason'] ); ?>"
						data-start="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( $item['start_block'] ) ) ); ?>T<?php echo esc_attr( gmdate( 'H:i', strtotime( $item['start_block'] ) ) ); ?>"
						data-end="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( $item['end_block'] ) ) ); ?>T<?php echo esc_attr( gmdate( 'H:i', strtotime( $item['end_block'] ) ) ); ?>"
						data-type="<?php echo esc_attr( $item['blocked_type'] ); ?>"
					>
						<?php _e( 'Update Block', 'zerospam' ); ?>
					</button>
					<?php
				return ob_get_clean();
				break;
			default:
				if ( empty( $item[ $column_name ] ) ) {
					return 'N/A';
				} else {
					return $item[ $column_name ];
				}
		}
	}

	/**
	 * Bulk actions.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_bulk_actions() {
		$actions = array(
			'delete'     => __( 'Delete Selected', 'zerospam' ),
			//'delete_all' => __( 'Delete All IPs', 'zerospam' ),
		);

		return $actions;
	}

	/**
	 * Hidable columns.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_hidden_columns() {
		return array();
	}

	/**
	 * Prepare log items.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function prepare_items( $args = array() ) {
		$this->process_bulk_action();

		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$per_page     = 50;
		$current_page = $this->get_pagenum();
		$offset       = $per_page * ( $current_page - 1 );
		$order        = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( $_REQUEST['order'] ) : 'desc';
		$orderby      = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'date_added';

		$log_type   = ! empty( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : false;
		$user_ip    = ! empty( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : false;

		$query_args = array(
			'limit'   => $per_page,
			'offset'  => $offset,
			'order'   => $order,
			'orderby' => $orderby,
			'where'   => array(
				'key_type' => array(
					'value' => 'ip',
				),
			),
		);

		if ( $log_type ) {
			$query_args['where']['blocked_type'] = array(
				'value' => $log_type,
			);
		}

		if ( $user_ip ) {
			$query_args['where']['user_ip'] = array(
				'value' => $user_ip,
			);
		}

		$data = ZeroSpam\Includes\DB::query( 'blocked', $query_args );
		if ( ! $data ) {
			return false;
		}

		$this->items = $data;

		unset( $query_args['limit'] );
		unset( $query_args['offset'] );
		$data = ZeroSpam\Includes\DB::query( 'blocked', $query_args );
		$total_items = count( $data );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages'	=> ceil( $total_items / $per_page ),
				'orderby'	    => $orderby,
				'order'		    => $order,
			)
		);

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$paging_options = array();
		if ( ! empty( $query_args['where'] ) ) {
			foreach ( $query_args['where'] as $key => $value ) {
				switch( $key ) {
					case 'blocked_type':
						$paging_options['type'] = $value['value'];
						break;
					case 'user_ip':
						$paging_options['s'] = $value['value'];
						break;
				}
			}
		}

		$_SERVER['REQUEST_URI'] = add_query_arg( $paging_options, $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Add more filters.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php
			/*
			echo '<label class="screen-reader-text" for="filter-by-type">' . __( 'Filter by type', 'zerospam' ) . '</label>';
			$options      = apply_filters( 'zerospam_types', array() );
			$current_type = ! empty( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : false;
			?>
			<select name="type" id="filter-by-type">
				<option value=""><?php _e( 'All types', 'zerospam' ); ?></option>
				<?php foreach ( $options as $key => $value ) : ?>
					<option<?php if ( $current_type === $key ) : ?> selected="selected"<?php endif; ?> value="<?php echo esc_attr( $key ); ?>"><?php echo $value; ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			submit_button( __( 'Filter', 'zerospam' ), '', 'filter_action', false );
			*/
			?>
			<button class="button zerospam-block-trigger"><?php echo __( 'Add Blocked IP Address', 'zerospam' ); ?></button>
		</div>
		<?php
	 }

	/**
	 * Define table columns.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_columns() {
		$columns = array(
			'cb'            => '<input type="checkbox" />',
			'date_added'   => __( 'Date', 'zerospam' ),
			'blocked_type' => __( 'Type', 'zerospam' ),
			'user_ip'      => __( 'IP Address', 'zerospam' ),
			'start_block'  => __( 'Starts', 'zerospam' ),
			'end_block'    => __( 'Ends', 'zerospam' ),
			'reason'       => __( 'Reason', 'zerospam' ),
			'actions'      => __( 'Actions', 'zerospam' ),
		);

		return $columns;
	}

	/**
	 * Sortable columns.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'date_added'   => array( 'date_added', false ),
			'blocked_type' => array( 'blocked_type', false ),
			'user_ip'      => array( 'user_ip', false ),
			'start_block'  => array( 'start_block', false ),
			'end_block'    => array( 'end_block', false ),
		);

		return $sortable_columns;
	}

	/**
	 * Column contact.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/ 'ids',
			/*$2%s*/ $item['blocked_id']
		);
	}

	/**
	 * Process bulk actions.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function process_bulk_action() {
		global $wpdb;

		$ids = ( isset( $_REQUEST['ids'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['ids'] ) ) : '';

		switch( $this->current_action() ) {
			case 'delete':
				$nonce = ( isset( $_REQUEST['zerospam_nonce'] ) ) ? sanitize_text_field( $_REQUEST['zerospam_nonce'] ) : '';
				if ( ! wp_verify_nonce( $nonce, 'zerospam_nonce' ) ) {
					return false;
				}

				if ( ! empty ( $ids ) && is_array( $ids ) ) {
					foreach ( $ids as $k => $blocked_id ) {
						ZeroSpam\Includes\DB::delete( 'blocked', 'blocked_id', $blocked_id );
					}
				}
				break;
			case 'delete_all':
				//ZeroSpam\Includes\DB::delete_all( 'blocked' );
				break;
		}
	}
}
