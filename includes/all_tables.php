<?php

/**
 * Admin list table (WordPress-standard UI) for Ultimate Product Table for WooCommerce.
 *
 * @package Ultimate Product Table for WooCommerce
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('WP_List_Table', false)) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for wcproducttab_tables (custom DB table) using WP_List_Table.
 */
class WCProductTab_Tables_List_Table extends WP_List_Table
{

	/**
	 * DB table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		global $wpdb;

		parent::__construct(
			array(
				'singular' => 'wcproducttab_table',
				'plural'   => 'wcproducttab_tables',
				'ajax'     => false,
			)
		);

		$this->table_name = $wpdb->prefix . 'wcproducttab_tables';
	}

	/**
	 * Table columns.
	 *
	 * @return array
	 */
	public function get_columns()
	{
		return array(
			'cb'           => '<input type="checkbox" />',
			'title'        => __('Title', 'ultimate-product-table-for-woocommerce'),
			'shortcode'    => __('Shortcode', 'ultimate-product-table-for-woocommerce'),
			'created_by'   => __('Created By', 'ultimate-product-table-for-woocommerce'),
			'created_at'   => __('Created On', 'ultimate-product-table-for-woocommerce'),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns()
	{
		return array(
			'title'      => array('title', true),
			'created_at' => array('created_at', true),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions()
	{
		return array(
			'bulk-delete' => __('Delete', 'ultimate-product-table-for-woocommerce'),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row object.
	 * @return string
	 */
	protected function column_cb($item)
	{
		return sprintf(
			'<input type="checkbox" name="ids[]" value="%d" />',
			absint($item->id)
		);
	}

	/**
	 * Title column with row actions.
	 *
	 * @param object $item Row object.
	 * @return string
	 */
	protected function column_title($item)
	{
		$nonce_edit   = wp_create_nonce('edit_table');
		$nonce_delete = wp_create_nonce('wcpt_delete_' . absint($item->id));

		$title = sprintf('<strong>%s</strong>', esc_html($item->title));

		$actions = array(
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'page'  => 'plugincy-add-table',
							'edit'  => absint($item->id),
							'nonce' => $nonce_edit,
						),
						admin_url('admin.php')
					)
				),
				esc_html__('Edit', 'ultimate-product-table-for-woocommerce')
			),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(%s);">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'page'   => 'plugincy-tables',
								'action' => 'delete',
								'id'     => absint($item->id),
							),
							admin_url('admin.php')
						),
						'wcpt_delete_' . absint($item->id)
					)
				),
				wp_json_encode(__('Are you sure you want to delete this table?', 'ultimate-product-table-for-woocommerce')),
				esc_html__('Delete', 'ultimate-product-table-for-woocommerce')
			),
		);

		return $title . $this->row_actions($actions);
	}

	/**
	 * Default column renderer.
	 *
	 * @param object $item  Row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default($item, $column_name)
	{
		switch ($column_name) {
			case 'shortcode':
				$shortcode = '[wcproducttab_table id="' . absint($item->id) . '"]';
				return sprintf(
					'<code>%1$s</code> <button type="button" class="button button-small wcpt-copy" data-shortcode="%2$s">%3$s</button>',
					esc_html($shortcode),
					esc_attr($shortcode),
					esc_html__('Copy', 'ultimate-product-table-for-woocommerce')
				);

			case 'created_by':
				$user = get_userdata((int) $item->created_by);
				return $user ? esc_html($user->display_name) : esc_html__('(Unknown)', 'ultimate-product-table-for-woocommerce');

			case 'created_at':
				$ts = strtotime((string) $item->created_at);
				return esc_html(gmdate('M j, Y g:i a', $ts ? $ts : time()));
		}

		return '';
	}

	/**
	 * Screen options: per-page.
	 *
	 * @param string $status Status.
	 * @param int    $page   Page.
	 * @return array
	 */
	public function get_items_per_page($option, $default = 20)
	{ // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Wrapper to meet coding style expectations; core calls parent::get_items_per_page().
		return parent::get_items_per_page('wcpt_tables_per_page', $default);
	}

	/**
	 * Prepare items: search, sort, pagination.
	 */
	public function prepare_items()
	{
		global $wpdb;

		$per_page     = $this->get_items_per_page('wcpt_tables_per_page', 20);
		$current_page = $this->get_pagenum();

		$search    = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby   = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : 'created_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order     = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash($_REQUEST['order']))) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order     = in_array($order, array('ASC', 'DESC'), true) ? $order : 'DESC';

		$valid_orderby = array('title', 'created_at');
		if (! in_array($orderby, $valid_orderby, true)) {
			$orderby = 'created_at';
		}

		// Where clause (search).
		$where  = 'WHERE 1=1';
		$params = array();

		if ('' !== $search) {
			$where   .= ' AND (title LIKE %s)';
			$params[] = '%' . $wpdb->esc_like($search) . '%';
		}

		// Count total items.
		$sql_count = "SELECT COUNT(*) FROM {$this->table_name} {$where}";
		$total     = (int) $wpdb->get_var($wpdb->prepare($sql_count, $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Pagination.
		$offset = ($current_page - 1) * $per_page;

		// Fetch items.
		$sql_items = "
			SELECT id, title, created_by, created_at
			FROM {$this->table_name}
			{$where}
			ORDER BY {$orderby} {$order}
			LIMIT %d OFFSET %d
		";

		$query_params = $params;
		$query_params[] = $per_page;
		$query_params[] = $offset;

		$items = $wpdb->get_results($wpdb->prepare($sql_items, $query_params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->items = is_array($items) ? $items : array();

		$this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns(), 'title');

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil($total / $per_page),
			)
		);
	}

	/**
	 * Extra controls above/below the table.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav($which)
	{
		if ('top' === $which) {
			echo '<div class="alignleft actions">';
			// Room for future dropdown filters (e.g., author).
			echo '</div>';
		}
	}

	/**
	 * Process bulk and single actions.
	 */
	public function process_actions()
	{
		if ('delete' === $this->current_action()) {
			$this->handle_delete_action();
		}

		if ('bulk-delete' === $this->current_action()) {
			$this->handle_bulk_delete_action();
		}
	}

	/**
	 * Handle single delete.
	 */
	private function handle_delete_action()
	{
		global $wpdb;

		$id = isset($_GET['id']) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ($id <= 0) {
			return;
		}

		check_admin_referer('wcpt_delete_' . $id);

		$wpdb->delete($this->table_name, array('id' => $id), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		add_settings_error(
			'wcpt_messages',
			'wcpt_deleted',
			__('Table deleted successfully.', 'ultimate-product-table-for-woocommerce'),
			'updated'
		);

		// Redirect to avoid re-submission on refresh.
		wp_safe_redirect(
			remove_query_arg(array('action', 'id', '_wpnonce'))
		);
		exit;
	}

	/**
	 * Handle bulk delete.
	 */
	private function handle_bulk_delete_action()
	{
		global $wpdb;

		check_admin_referer('bulk-' . $this->_args['plural']);

		$ids = isset($_REQUEST['ids']) ? (array) $_REQUEST['ids'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ids = array_map('absint', $ids);
		$ids = array_filter($ids);

		if (empty($ids)) {
			return;
		}

		$placeholders = implode(',', array_fill(0, count($ids), '%d'));

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
				$ids
			)
		);

		add_settings_error(
			'wcpt_messages',
			'wcpt_bulk_deleted',
			sprintf(
				/* translators: %d: number of deleted items */
				_n('%d table deleted.', '%d tables deleted.', count($ids), 'ultimate-product-table-for-woocommerce'),
				count($ids)
			),
			'updated'
		);

		// Redirect to clean POST.
		wp_safe_redirect(
			remove_query_arg(array('action', 'ids', '_wpnonce'))
		);
		exit;
	}
}

/**
 * Admin controller: menu, screen options, rendering.
 */
class WCProductTab_AllTablesAdmin
{

	/**
	 * DB table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Screen hook suffix for our page.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * List table instance.
	 *
	 * @var WCProductTab_Tables_List_Table
	 */
	private $list_table;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wcproducttab_tables';

		add_action('load-toplevel_page_plugincy-tables', array($this, 'load_screen'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	/**
	 * Setup screen options and instantiate list table.
	 */
	public function load_screen()
	{
		$this->list_table = new WCProductTab_Tables_List_Table();

		// Screen options: Per page.
		add_screen_option(
			'per_page',
			array(
				'label'   => __('Tables per page', 'ultimate-product-table-for-woocommerce'),
				'default' => 20,
				'option'  => 'wcpt_tables_per_page',
			)
		);

		// Process actions (delete / bulk-delete) before prepare_items().
		$this->list_table->process_actions();
	}

	/**
	 * Enqueue small inline script for copy buttons on our page only.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets($hook)
	{
		if ('toplevel_page_plugincy-tables' !== $hook) {
			return;
		}

		$handle = 'wcpt-admin-list';
		wp_register_script($handle, '', array(), '1.0.0', true);
		wp_enqueue_script($handle);

		wp_add_inline_script(
			$handle,
			"(function(){
		function speak(msg){ if(window.wp && wp.a11y && wp.a11y.speak){ wp.a11y.speak(msg); } }

		function showTooltip(el, msg){
			// Remove old tooltip if present
			var oldTip = el.parentNode.querySelector('.wcpt-tooltip');
			if(oldTip){ oldTip.remove(); }

			// Tooltip wrapper
			var tip = document.createElement('div');
			tip.className = 'wcpt-tooltip';
			tip.textContent = msg;
			el.parentNode.style.position = 'relative';
			tip.style.position = 'absolute';
			tip.style.top = (el.offsetTop + el.offsetHeight + 8) + 'px'; // 8px below button
			tip.style.left = el.offsetLeft + 'px';
			tip.style.background = '#28a745'; // green background
			tip.style.color = '#fff';
			tip.style.padding = '4px 8px';
			tip.style.borderRadius = '4px';
			tip.style.fontSize = '12px';
			tip.style.whiteSpace = 'nowrap';
			tip.style.zIndex = 1000;

			// Arrow
			var arrow = document.createElement('span');
			arrow.style.position = 'absolute';
			arrow.style.top = '-6px';
			arrow.style.left = '10px';
			arrow.style.width = '0';
			arrow.style.height = '0';
			arrow.style.borderLeft = '6px solid transparent';
			arrow.style.borderRight = '6px solid transparent';
			arrow.style.borderBottom = '6px solid #28a745';
			tip.appendChild(arrow);

			el.parentNode.appendChild(tip);

			setTimeout(function(){
				tip.style.opacity = '0';
				tip.style.transition = 'opacity 0.3s ease';
				setTimeout(function(){ if(tip.parentNode){ tip.remove(); } }, 300);
			}, 1500);
		}

		document.addEventListener('click', function(e){
			if(e.target && e.target.classList.contains('wcpt-copy')){
				e.preventDefault();
				var sc = e.target.getAttribute('data-shortcode');
				if(!sc){return;}
				if(navigator.clipboard && navigator.clipboard.writeText){
					navigator.clipboard.writeText(sc).then(function(){
						speak('" . esc_js(__('Shortcode copied to clipboard.', 'ultimate-product-table-for-woocommerce')) . "');
						showTooltip(e.target, '" . esc_js(__('Shortcode copied!', 'ultimate-product-table-for-woocommerce')) . "');
					});
				}else{
					var t = document.createElement('textarea'); t.value = sc; document.body.appendChild(t); t.select();
					try{ document.execCommand('copy'); }catch(err){}
					document.body.removeChild(t);
					showTooltip(e.target, '" . esc_js(__('Shortcode copied!', 'ultimate-product-table-for-woocommerce')) . "');
				}
			}
		});
	})();",
			'after'
		);
	}

	/**
	 * Render the admin page with the standard WP table.
	 */
	public function render_page()
	{
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__('Ultimate Product Table', 'ultimate-product-table-for-woocommerce') . '</h1> ';
		echo ' <a href="' . esc_url(admin_url('admin.php?page=plugincy-add-table')) . '" class="page-title-action">' . esc_html__('Add New', 'ultimate-product-table-for-woocommerce') . '</a>';

		// Notices (from actions).
		settings_errors('wcpt_messages');

		echo '<hr class="wp-header-end" />';

		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="plugincy-tables" />';

		$this->list_table->prepare_items();

		// Search box.
		$this->list_table->search_box(__('Search tables', 'ultimate-product-table-for-woocommerce'), 'wcpt-search');

		// Display the table.
		$this->list_table->display();

		echo '</form>';
		echo '</div>';
	}
}
