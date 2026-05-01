<?php
/**
 * Admin Logic and Settings UI
 *
 * @package    PBC_DEC_Events_Bridge
 * @author     South Florida Web Advisors
 * @version    1.1.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PBC_DEC_Admin {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_source_actions' ) );
	}

	public function add_plugin_admin_menu() {
		add_menu_page(
			'PBC DEC Bridge',
			'Events Bridge',
			'manage_options',
			'pbc-dec-bridge',
			array( $this, 'display_sources_page' ),
			'dashicons-cloud-share',
			25
		);

		add_submenu_page(
			'pbc-dec-bridge',
			'Bridge Table',
			'Bridge Table',
			'manage_options',
			'pbc-dec-bridge-table',
			array( $this, 'display_bridge_table' )
		);
	}

	public function handle_source_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'pbc_dec_sources';

		// --- 1. HANDLE ADD SOURCE ---
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

		// --- 2. HANDLE DELETE SOURCE ---
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['source'] ) ) {
			check_admin_referer( 'delete_source_' . $_GET['source'] );
			$source_id = absint( $_GET['source'] );
			$wpdb->delete( $table_name, array( 'id' => $source_id ) );
			$wpdb->delete( $wpdb->prefix . 'pbc_dec_event_bridge', array( 'source_id' => $source_id ) );
			wp_redirect( admin_url( 'admin.php?page=pbc-dec-bridge&settings-updated=true' ) );
			exit;
		}

		// --- 3. HANDLE MANUAL SYNC ---
		if ( isset( $_GET['action'] ) && 'sync' === $_GET['action'] && isset( $_GET['source'] ) ) {
			check_admin_referer( 'sync_source_' . $_GET['source'] );
			
			require_once PBC_DEC_BRIDGE_PATH . 'includes/class-pbc-dec-sync.php';
			$source = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", absint( $_GET['source'] ) ) );
			
			if ( $source ) {
				$sync = new PBC_DEC_Sync();
				$added = $sync->sync_source( $source );
				add_settings_error( 'pbc_bridge_msgs', 'synced', "Sync Complete: $added new sessions staged in the bridge.", 'updated' );
			}
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
			<h1>PBC DEC Events Bridge - API Sources</h1>
			
			<div class="card" style="max-width: 600px; margin-top: 20px; padding: 20px;">
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
								$sync_url = wp_nonce_url( admin_url( 'admin.php?page=pbc-dec-bridge&action=sync&source=' . $source->id ), 'sync_source_' . $source->id );
								$del_url  = wp_nonce_url( admin_url( 'admin.php?page=pbc-dec-bridge&action=delete&source=' . $source->id ), 'delete_source_' . $source->id );
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
		$items = $wpdb->get_results( "
			SELECT b.*, s.source_name 
			FROM {$wpdb->prefix}pbc_dec_event_bridge b 
			JOIN {$wpdb->prefix}pbc_dec_sources s ON b.source_id = s.id 
			ORDER BY b.event_date DESC
		" );
		?>
		<div class="wrap">
			<h1>Bridge Table</h1>
			<p>View ingested events from all sources. Events promoted to the calendar will show a status of "Promoted".</p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Source</th>
						<th>Title</th>
						<th>Event Date</th>
						<th>Status</th>
						<th style="width: 150px;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $items ) : foreach ( $items as $i ) : 
						$data = json_decode( $i->raw_json, true );
						$title = $data['title'] ?? 'Untitled Event';
						
						// Format date for display
						$display_date = date( 'M j, Y g:i A', strtotime( $i->event_date ) );
					?>
						<tr>
							<td><strong><?php echo esc_html( $i->source_name ); ?></strong></td>
							<td><?php echo esc_html( $title ); ?></td>
							<td><?php echo esc_html( $display_date ); ?></td>
							<td>
								<?php if ( $i->wp_post_id ) : ?>
									<span class="badge badge-success" style="color: green; font-weight: bold;">Promoted</span>
								<?php else : ?>
									<span class="badge badge-warning" style="color: #999;">Pending</span>
								<?php endif; ?>
							</td>
							<td>
								<button class="button button-primary" <?php echo $i->wp_post_id ? 'disabled' : ''; ?>>
									<?php echo $i->wp_post_id ? 'Promoted' : 'Promote to Calendar'; ?>
								</button>
							</td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="5">No events currently in the bridge. Use "Sync Now" in API Sources to ingest data.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
