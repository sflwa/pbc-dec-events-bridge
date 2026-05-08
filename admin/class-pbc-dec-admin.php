<?php
/**
 * Admin Logic and Settings UI
 *
 * @package    PBC_DEC_Events_Bridge
 * @author     South Florida Web Advisors
 * @version    1.2.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PBC_DEC_Admin {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_source_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_event_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
		// AJAX Handlers
		add_action( 'wp_ajax_pbc_hide_event', array( $this, 'ajax_hide_event' ) );
		add_action( 'wp_ajax_pbc_unhide_event', array( $this, 'ajax_unhide_event' ) );
		add_action( 'wp_ajax_pbc_add_to_calendar', array( $this, 'ajax_add_to_calendar' ) );
		add_action( 'wp_ajax_pbc_manual_sync', array( $this, 'ajax_manual_sync' ) );
		
		// Scripts for AJAX
		add_action( 'admin_footer', array( $this, 'print_admin_scripts' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'pbc-dec-bridge' ) !== false ) {
			add_thickbox();
		}
	}

	public function add_plugin_admin_menu() {
		add_menu_page(
			'PBC DEC Bridge',
			'Events Bridge',
			'manage_options',
			'pbc-dec-bridge-table',
			array( $this, 'display_bridge_table' ),
			'dashicons-calendar-alt',
			25
		);

		add_submenu_page(
			'pbc-dec-bridge-table',
			'Imported Events',
			'Imported Events',
			'manage_options',
			'pbc-dec-bridge-table',
			array( $this, 'display_bridge_table' )
		);

		add_submenu_page(
			'pbc-dec-bridge-table',
			'Settings',
			'Settings',
			'manage_options',
			'pbc-dec-bridge',
			array( $this, 'display_sources_page' )
		);
	}

	public function handle_event_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['action'] ) && 'delete_event' === $_GET['action'] && isset( $_GET['event_id'] ) ) {
			check_admin_referer( 'delete_event_' . $_GET['event_id'] );
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . 'pbc_dec_event_bridge', array( 'id' => absint( $_GET['event_id'] ) ) );
			
			add_settings_error( 'pbc_bridge_msgs', 'event_deleted', 'Event permanently removed from the bridge.', 'updated' );
		}
	}

	/**
	 * AJAX Manual Sync
	 */
	public function ajax_manual_sync() {
		check_ajax_referer( 'pbc_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		require_once PBC_DEC_BRIDGE_PATH . 'includes/class-pbc-dec-sync.php';
		$sync = new PBC_DEC_Sync();
		$new_events = $sync->run_full_sync();

		wp_send_json_success( array(
			'message' => sprintf( 'Sync complete! %d new event sessions found.', $new_events ),
			'last_sync' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) )
		) );
	}

	/**
	 * AJAX Handler to Map/Add to Calendar
	 */
	public function ajax_add_to_calendar() {
		check_ajax_referer( 'pbc_add_event_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		global $wpdb;
		
		$row = $wpdb->get_row( $wpdb->prepare( 
			"SELECT b.*, s.source_name FROM {$wpdb->prefix}pbc_dec_event_bridge b 
			 JOIN {$wpdb->prefix}pbc_dec_sources s ON b.source_id = s.id 
			 WHERE b.id = %d", 
			$event_id 
		) );
		
		if ( ! $row ) {
			wp_send_json_error( 'Event not found' );
		}

		$data = json_decode( $row->raw_json, true );
		$is_debug = get_option( 'pbc_debug_mapping', '0' ) === '1';

		// Identify the specific session
		$session = null;
		if ( ! empty( $data['event_sessions'] ) ) {
			foreach ( $data['event_sessions'] as $s ) {
				if ( (string)$s['id'] === (string)$row->external_session_id ) {
					$session = $s;
					break;
				}
			}
		}

		// Local Timezone Conversion
		$tz = wp_timezone();
		$dt_start = new DateTime( $row->event_date, new DateTimeZone('UTC') );
		$dt_start->setTimezone( $tz );
		
		// End time logic
		$end_date_raw = $session['end_time'] ?? $row->event_date;
		$dt_end = new DateTime( $end_date_raw, new DateTimeZone('UTC') );
		$dt_end->setTimezone( $tz );

		// Content Formatting
		$description = $data['description'] ?? '';
		if ( ($session['event_type'] ?? '') === 'virtual' && ! empty( $session['location_address'] ) ) {
			$description = "<strong>Join Virtual Meeting:</strong> <a href='{$session['location_address']}'>{$session['location_address']}</a><br><br>" . $description;
		}
		$description = wpautop( $description );

		// Venue Mapping Details
		$v_name = $session['location_name'] ?? 'Virtual / Online';
		$v_addr = $session['location_data']['address_line_1'] ?? ( ($session['event_type'] === 'virtual') ? 'Online' : '' );
		$v_city = $session['location_data']['address_city'] ?? '';
		$v_stat = $session['location_data']['address_state'] ?? '';
		$v_zip  = $session['location_data']['address_postal_code'] ?? '';

		if ( $is_debug ) {
			$html = '<h3>Debug Mapping Preview</h3>';
			$mapping = array(
				array( 's' => 'title', 't' => 'post_title', 'v' => $data['title'] ),
				array( 's' => 'description', 't' => 'post_content', 'v' => wp_trim_words( $description, 50 ) ),
				array( 's' => 'start_time', 't' => 'EventStartDate', 'v' => $dt_start->format('Y-m-d H:i:s') ),
				array( 's' => 'location_name', 't' => 'VenueName', 'v' => $v_name ),
				array( 's' => 'address_line_1', 't' => '_VenueAddress', 'v' => $v_addr ),
				array( 's' => 'source_name', 't' => 'OrganizerName', 'v' => $row->source_name )
			);
			$html .= '<table class="widefat striped"><thead><tr><th>Source Key</th><th>TEC Field</th><th>Value</th></tr></thead><tbody>';
			foreach ( $mapping as $m ) {
				$html .= "<tr><td><code>{$m['s']}</code></td><td><strong>{$m['t']}</strong></td><td>" . esc_html( $m['v'] ) . "</td></tr>";
			}
			$html .= '</tbody></table>';
			wp_send_json_success( array( 'debug' => true, 'html' => $html ) );
		}

		// --- ACTUAL CREATION ENGINE ---
		
		// 1. Manage Venue (Lookup by Address)
		$venue_id = 0;
		if ( ! empty( $v_addr ) ) {
			$existing_venue = get_posts( array(
				'post_type'  => 'tribe_venue',
				'meta_query' => array( array( 'key' => '_VenueAddress', 'value' => $v_addr ) ),
				'posts_per_page' => 1
			) );
			if ( ! empty( $existing_venue ) ) {
				$venue_id = $existing_venue[0]->ID;
			} else {
				$venue_id = wp_insert_post( array(
					'post_title'  => $v_name,
					'post_type'   => 'tribe_venue',
					'post_status' => 'publish'
				) );
				update_post_meta( $venue_id, '_VenueAddress', $v_addr );
				update_post_meta( $venue_id, '_VenueCity', $v_city );
				update_post_meta( $venue_id, '_VenueState', $v_stat );
				update_post_meta( $venue_id, '_VenueZip', $v_zip );
			}
		}

		// 2. Manage Organizer (Lookup by Name)
		$org_id = 0;
		$existing_org = get_posts( array(
			'post_type'  => 'tribe_organizer',
			'title'      => $row->source_name,
			'posts_per_page' => 1
		) );
		if ( ! empty( $existing_org ) ) {
			$org_id = $existing_org[0]->ID;
		} else {
			$org_id = wp_insert_post( array(
				'post_title'  => $row->source_name,
				'post_type'   => 'tribe_organizer',
				'post_status' => 'publish'
			) );
		}

		// 3. Create Event Post
		$new_event_id = wp_insert_post( array(
			'post_title'   => $data['title'],
			'post_content' => $description,
			'post_status'  => 'draft',
			'post_type'    => 'tribe_events',
		) );

		if ( $new_event_id ) {
			update_post_meta( $new_event_id, '_EventStartDate', $dt_start->format('Y-m-d H:i:s') );
			update_post_meta( $new_event_id, '_EventEndDate', $dt_end->format('Y-m-d H:i:s') );
			update_post_meta( $new_event_id, '_EventVenueID', $venue_id );
			update_post_meta( $new_event_id, '_EventOrganizerID', $org_id );
			update_post_meta( $new_event_id, '_EventURL', $data['event_page_url'] ?? '' );
			
			// 4. Handle Featured Image
			if ( ! empty( $data['image_url'] ) ) {
				$this->sideload_featured_image( $new_event_id, $data['image_url'] );
			}

			// 5. Update Bridge Row
			$wpdb->update( 
				$wpdb->prefix . 'pbc_dec_event_bridge', 
				array( 'wp_post_id' => $new_event_id, 'event_status' => 'added' ), 
				array( 'id' => $event_id ) 
			);

			wp_send_json_success( array(
				'debug'   => false,
				'post_id' => $new_event_id,
				'view_url' => get_permalink( $new_event_id ),
				'edit_url' => get_edit_post_link( $new_event_id, 'url' )
			) );
		}

		wp_send_json_error( 'Failed to create event post.' );
	}

	/**
	 * Sideload image and set as featured
	 */
	private function sideload_featured_image( $post_id, $url ) {
		// Check for existing by source URL meta
		$existing = get_posts( array(
			'post_type'  => 'attachment',
			'meta_query' => array( array( 'key' => '_solidarity_source_url', 'value' => $url ) ),
			'posts_per_page' => 1
		) );

		if ( ! empty( $existing ) ) {
			set_post_thumbnail( $post_id, $existing[0]->ID );
			return;
		}

		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$att_id = media_sideload_image( $url, $post_id, null, 'id' );
		if ( ! is_wp_error( $att_id ) ) {
			set_post_thumbnail( $post_id, $att_id );
			update_post_meta( $att_id, '_solidarity_source_url', $url );
		}
	}

	public function ajax_hide_event() {
		check_ajax_referer( 'pbc_hide_event_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'pbc_dec_event_bridge', array( 'event_status' => 'hidden' ), array( 'id' => absint( $_POST['event_id'] ) ) );
		wp_send_json_success();
	}

	public function ajax_unhide_event() {
		check_ajax_referer( 'pbc_unhide_event_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'pbc_dec_event_bridge', array( 'event_status' => 'pending' ), array( 'id' => absint( $_POST['event_id'] ) ) );
		wp_send_json_success();
	}

	public function handle_source_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'pbc_dec_sources';

		if ( isset( $_POST['pbc_save_settings'] ) ) {
			update_option( 'pbc_debug_mapping', isset( $_POST['pbc_debug_mapping'] ) ? '1' : '0' );
			add_settings_error( 'pbc_bridge_msgs', 'settings_saved', 'Settings saved.', 'updated' );
		}

		if ( isset( $_POST['pbc_submit_source'] ) && check_admin_referer( 'pbc_add_source_action', 'pbc_add_source_nonce' ) ) {
			$name     = sanitize_text_field( $_POST['source_name'] );
			$platform = sanitize_text_field( $_POST['platform'] );
			$api_key  = sanitize_text_field( $_POST['api_key'] );
			$org_id   = $api_key; 

			if ( 'solidarity' === $platform ) {
				$discovery_id = $this->discover_solidarity_org_id( $api_key );
				if ( $discovery_id ) {
					$org_id = $discovery_id;
				}
			}

			$wpdb->insert(
				$table_name,
				array(
					'source_name' => $name,
					'platform'    => $platform,
					'api_key'     => $api_key,
					'org_id'      => $org_id,
					'is_active'   => 1,
				)
			);
			add_settings_error( 'pbc_bridge_msgs', 'source_added', 'Source added successfully.', 'updated' );
		}

		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['source'] ) ) {
			check_admin_referer( 'delete_source_' . $_GET['source'] );
			$source_id = absint( $_GET['source'] );
			$wpdb->delete( $table_name, array( 'id' => $source_id ) );
			$wpdb->delete( $wpdb->prefix . 'pbc_dec_event_bridge', array( 'source_id' => $source_id ) );
			wp_redirect( admin_url( 'admin.php?page=pbc-dec-bridge-table&tab=settings' ) );
			exit;
		}
	}

	private function discover_solidarity_org_id( $api_key ) {
		$response = wp_remote_get( 'https://api.solidarity.tech/v1/organizations', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $api_key )
		) );
		if ( is_wp_error( $response ) ) return false;
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['data'][0]['id'] ?? false;
	}

	public function display_sources_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'pbc_dec_sources';
		$sources    = $wpdb->get_results( "SELECT * FROM $table_name" );
		settings_errors( 'pbc_bridge_msgs' );
		?>
		<div class="wrap">
			<h1>Bridge Settings</h1>
			
			<div class="card" style="max-width: 600px; margin-top: 20px; padding: 20px;">
				<form method="POST">
					<h2>Global Options</h2>
					<table class="form-table">
						<tr>
							<th>Mapping Debug Mode</th>
							<td>
								<label>
									<input type="checkbox" name="pbc_debug_mapping" value="1" <?php checked( get_option('pbc_debug_mapping'), '1' ); ?>>
									Enable Debug Mapping Modal
								</label>
								<p class="description">When enabled, "Add to Calendar" will show a mapping preview table instead of creating a real event.</p>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Save Options', 'secondary', 'pbc_save_settings' ); ?>
				</form>

				<hr />

				<h2>Add New API Source</h2>
				<form method="POST">
					<?php wp_nonce_field( 'pbc_add_source_action', 'pbc_add_source_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label>Friendly Name</label></th>
							<td><input name="source_name" type="text" class="regular-text" placeholder="e.g. PBC Main Office" required></td>
						</tr>
						<tr>
							<th><label>Platform</label></th>
							<td>
								<select name="platform" id="pbc_platform">
									<option value="solidarity">Solidarity Tech</option>
									<option value="mobilize">Mobilize</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label id="pbc_key_label">Bearer Token</label></th>
							<td><input name="api_key" type="text" class="regular-text" required></td>
						</tr>
					</table>
					<?php submit_button( 'Connect Source', 'primary', 'pbc_submit_source' ); ?>
				</form>
			</div>

			<hr />

			<h2>Connected Sources</h2>
			<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
				<thead>
					<tr>
						<th>Name</th>
						<th>Platform</th>
						<th>Org ID</th>
						<th style="width: 220px;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $sources ) : foreach ( $sources as $source ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $source->source_name ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( $source->platform ) ); ?></td>
							<td><code><?php echo esc_html( $source->org_id ); ?></code></td>
							<td>
								<?php
								$sync_url = wp_nonce_url( admin_url( 'admin.php?page=pbc-dec-bridge-table&action=sync&source=' . $source->id ), 'sync_source_' . $source->id );
								$del_url  = wp_nonce_url( admin_url( 'admin.php?page=pbc-dec-bridge-table&action=delete&source=' . $source->id ), 'delete_source_' . $source->id );
								?>
								<a href="<?php echo esc_url( $sync_url ); ?>" class="button button-secondary">Sync Now</a>
								<a href="<?php echo esc_url( $del_url ); ?>" class="button button-link-delete" onclick="return confirm('Really delete this source?');">Delete</a>
							</td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="4">No API sources configured yet.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<script type="text/javascript">
			(function() {
				const select = document.getElementById('pbc_platform');
				const label = document.getElementById('pbc_key_label');
				if (select && label) {
					const updateLabel = () => {
						label.textContent = (select.value === 'solidarity') ? 'Bearer Token' : 'Organization ID';
					};
					select.addEventListener('change', updateLabel);
					updateLabel();
				}
			})();
		</script>
		<?php
	}

	public function display_bridge_table() {
		global $wpdb;
		settings_errors( 'pbc_bridge_msgs' );

		// Tab Logic
		$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'upcoming';
		$current_time = current_time( 'mysql' );
		$tz = wp_timezone();
		$sort_order = "ASC";

		if ( $active_tab === 'hidden' ) {
			$where_clause = "WHERE b.event_status = 'hidden'";
		} elseif ( $active_tab === 'past' ) {
			$where_clause = "WHERE b.event_date < '$current_time'";
			$sort_order = "DESC"; 
		} else {
			$where_clause = "WHERE b.event_date >= '$current_time' AND b.event_status != 'hidden'";
		}

		$items = $wpdb->get_results( "
			SELECT b.*, s.source_name, s.platform
			FROM {$wpdb->prefix}pbc_dec_event_bridge b 
			JOIN {$wpdb->prefix}pbc_dec_sources s ON b.source_id = s.id 
			$where_clause
			ORDER BY b.event_date $sort_order
		" );

		$last_sync_raw = get_option( 'pbc_dec_last_sync_time' );
		$last_sync_display = $last_sync_raw ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_sync_raw ) ) : 'Never';
		$sync_nonce = wp_create_nonce( 'pbc_sync_nonce' );
		?>
		<div class="wrap">
			<h1>Imported Events</h1>
			
			<div style="margin: 20px 0; display: flex; align-items: center; gap: 15px;">
				<button class="button button-primary" id="pbc-trigger-sync" data-nonce="<?php echo $sync_nonce; ?>">Trigger Manual Sync</button>
				<span style="font-size: 13px; color: #64748b;">Last Ingestion: <strong id="last-sync-label"><?php echo $last_sync_display; ?></strong></span>
			</div>

			<nav class="nav-tab-wrapper">
				<a href="?page=pbc-dec-bridge-table&tab=upcoming" class="nav-tab <?php echo $active_tab === 'upcoming' ? 'nav-tab-active' : ''; ?>">Upcoming Events</a>
				<a href="?page=pbc-dec-bridge-table&tab=hidden" class="nav-tab <?php echo $active_tab === 'hidden' ? 'nav-tab-active' : ''; ?>">Hidden Events</a>
				<a href="?page=pbc-dec-bridge-table&tab=past" class="nav-tab <?php echo $active_tab === 'past' ? 'nav-tab-active' : ''; ?>">Past Events</a>
			</nav>

			<p>View ingested events from all sources. Events added to the calendar will show a status of "Added".</p>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Source</th>
						<th>Owner</th>
						<th>Title</th>
						<th>Event Date (Local)</th>
						<th>Location</th>
						<th>Status</th>
						<th style="width: 280px;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $items ) : foreach ( $items as $i ) : 
						$data = json_decode( $i->raw_json, true );
						$title = $data['title'] ?? 'Untitled Event';
						$owner = $i->platform === 'solidarity' ? ($data['scope_id'] ?? 'N/A') : ($data['organization']['name'] ?? 'N/A');
						
						// Timezone Conversion
						$dt = new DateTime( $i->event_date, new DateTimeZone('UTC') );
						$dt->setTimezone( $tz );
						$display_date = $dt->format('M j, Y g:i A');

						// Detailed Session Identification
						$session = null;
						if ( ! empty( $data['event_sessions'] ) ) {
							foreach ( $data['event_sessions'] as $s ) {
								if ( (string)$s['id'] === (string)$i->external_session_id ) {
									$session = $s;
									break;
								}
							}
						}

						// Location Logic
						$loc = $session['location_name'] ?? 'Virtual / Online';
						
						$hide_nonce = wp_create_nonce( 'pbc_hide_event_nonce' );
						$unhide_nonce = wp_create_nonce( 'pbc_unhide_event_nonce' );
						$add_nonce = wp_create_nonce( 'pbc_add_event_nonce' );
					?>
						<tr id="event-row-<?php echo $i->id; ?>">
							<td><strong><?php echo esc_html( $i->source_name ); ?></strong></td>
							<td><code><?php echo esc_html( $owner ); ?></code></td>
							<td><?php echo esc_html( $title ); ?></td>
							<td><?php echo esc_html( $display_date ); ?></td>
							<td><em><?php echo esc_html( $loc ); ?></em></td>
							<td>
								<?php if ( $i->wp_post_id ) : ?>
									<span class="badge badge-success" style="color: green; font-weight: bold;">Added</span>
								<?php else : ?>
									<span class="badge badge-warning" style="color: #999;">Pending</span>
								<?php endif; ?>
							</td>
							<td id="actions-cell-<?php echo $i->id; ?>">
								<?php if ( $active_tab === 'past' ) : ?>
									<?php $del_url = wp_nonce_url( admin_url( 'admin.php?page=pbc-dec-bridge-table&tab=past&action=delete_event&event_id=' . $i->id ), 'delete_event_' . $i->id ); ?>
									<a href="<?php echo esc_url( $del_url ); ?>" class="button button-link-delete" onclick="return confirm('Permanently delete this event from the bridge?');">Delete Forever</a>
								<?php else : ?>
									
									<?php if ( $i->wp_post_id ) : ?>
										<a href="<?php echo get_permalink($i->wp_post_id); ?>" class="button button-secondary" target="_blank">View</a>
										<a href="<?php echo get_edit_post_link($i->wp_post_id); ?>" class="button button-secondary" target="_blank" style="margin-left:5px;">Edit</a>
									<?php else : ?>
										<button class="button button-primary pbc-add-event" 
												data-id="<?php echo $i->id; ?>" 
												data-nonce="<?php echo $add_nonce; ?>">
											Add Event to Calendar
										</button>
										
										<?php if ( $active_tab === 'hidden' ) : ?>
											<button class="button button-secondary pbc-unhide-event" style="margin-left: 10px;" 
													data-id="<?php echo $i->id; ?>" 
													data-nonce="<?php echo $unhide_nonce; ?>">Unhide Event</button>
										<?php else : ?>
											<button class="button button-link-delete pbc-hide-event" style="margin-left: 10px;" 
													data-id="<?php echo $i->id; ?>" 
													data-nonce="<?php echo $hide_nonce; ?>">Hide Event</button>
										<?php endif; ?>
									<?php endif; ?>

								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="7">No events found in this category.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
			<div id="pbc-debug-modal-content" style="display:none;"></div>
		</div>
		<?php
	}

	public function print_admin_scripts() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'pbc-dec-bridge-table' ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Handle Manual Sync
			$('#pbc-trigger-sync').on('click', function(e) {
				e.preventDefault();
				const btn = $(this);
				const nonce = btn.data('nonce');

				btn.prop('disabled', true).text('Syncing...');

				$.post(ajaxurl, {
					action: 'pbc_manual_sync',
					nonce: nonce
				}, function(response) {
					if (response.success) {
						alert(response.data.message);
						$('#last-sync-label').text(response.data.last_sync);
						location.reload(); // Reload to show new items
					} else {
						alert('Sync Failed: ' + response.data);
					}
					btn.prop('disabled', false).text('Trigger Manual Sync');
				});
			});

			// Handle Hide/Unhide AJAX
			$('.pbc-hide-event, .pbc-unhide-event').on('click', function(e) {
				e.preventDefault();
				const btn = $(this);
				const eventId = btn.data('id');
				const nonce = btn.data('nonce');
				const action = btn.hasClass('pbc-hide-event') ? 'pbc_hide_event' : 'pbc_unhide_event';

				btn.prop('disabled', true);

				$.post(ajaxurl, {
					action: action,
					event_id: eventId,
					nonce: nonce
				}, function(response) {
					if (response.success) {
						$('#event-row-' + eventId).fadeOut(400, function() {
							$(this).remove();
						});
					} else {
						alert('Error: ' + response.data);
						btn.prop('disabled', false);
					}
				});
			});

			// Handle Add to Calendar
			$(document).on('click', '.pbc-add-event', function(e) {
				e.preventDefault();
				const btn = $(this);
				const eventId = btn.data('id');
				const nonce = btn.data('nonce');

				btn.prop('disabled', true).text('Processing...');

				$.post(ajaxurl, {
					action: 'pbc_add_to_calendar',
					event_id: eventId,
					nonce: nonce
				}, function(response) {
					if (response.success) {
						if (response.data.debug) {
							$('#pbc-debug-modal-content').html(response.data.html);
							tb_show('Mapping Preview', '#TB_inline?width=600&height=550&inlineId=pbc-debug-modal-content');
							btn.prop('disabled', false).text('Add Event to Calendar');
						} else {
							// Successfully added
							const cell = $('#actions-cell-' + eventId);
							cell.html('<a href="'+response.data.view_url+'" class="button button-secondary" target="_blank">View</a>' +
									  '<a href="'+response.data.edit_url+'" class="button button-secondary" target="_blank" style="margin-left:5px;">Edit</a>');
							
							// Update status badge in the same row
							$('#event-row-' + eventId + ' .badge-warning').removeClass('badge-warning').addClass('badge-success').css('color', 'green').text('Added');
						}
					} else {
						alert('Error: ' + (response.data || 'Unknown error'));
						btn.prop('disabled', false).text('Add Event to Calendar');
					}
				});
			});
		});
		</script>
		<?php
	}
}
