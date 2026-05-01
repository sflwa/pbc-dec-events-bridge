<?php
/**
 * PBC DEC Events Bridge - Sync Engine
 * Handles API Ingestion from Solidarity and Mobilize
 *
 * @package    PBC_DEC_Events_Bridge
 * @author     South Florida Web Advisors
 * @version    1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PBC_DEC_Sync {

	/**
	 * Route the sync request based on platform.
	 */
	public function sync_source( $source ) {
		if ( 'solidarity' === $source->platform ) {
			return $this->sync_solidarity( $source );
		} elseif ( 'mobilize' === $source->platform ) {
			return $this->sync_mobilize( $source );
		}
		return 0;
	}

	/**
	 * Pull from Solidarity Tech API.
	 * Maps start_time from the JSON to the database event_date.
	 */
	private function sync_solidarity( $source ) {
		$response = wp_remote_get( "https://api.solidarity.tech/v1/events", array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $source->api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json'
			),
			'timeout' => 30
		) );

		if ( is_wp_error( $response ) ) {
			return 0;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$count = 0;

		if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {
			foreach ( $body['data'] as $event ) {
				if ( empty( $event['event_sessions'] ) ) {
					continue;
				}
				
				foreach ( $event['event_sessions'] as $session ) {
					// Map Solidarity "start_time" to the database "event_date" field
					$session_start = isset( $session['start_time'] ) ? $session['start_time'] : '';
					
					if ( ! empty( $session_start ) ) {
						// Convert ISO8601 to MySQL format: YYYY-MM-DD HH:MM:SS
						$formatted_date = date( 'Y-m-d H:i:s', strtotime( $session_start ) );
						
						if ( $this->stage_event( $source->id, $event['id'], $session['id'], $formatted_date, $event ) ) {
							$count++;
						}
					}
				}
			}
		}
		return $count;
	}

	/**
	 * Pull from Mobilize America API.
	 */
	private function sync_mobilize( $source ) {
		// Mobilize URL structure
		$url = "https://api.mobilize.us/v1/organizations/" . intval( $source->org_id ) . "/events?limit=50&timeslot_start=gte_now";
		$response = wp_remote_get( $url, array( 
			'timeout' => 30,
			'headers' => array( 'Accept' => 'application/json' )
		) );

		if ( is_wp_error( $response ) ) {
			return 0;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$count = 0;

		if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {
			foreach ( $body['data'] as $event ) {
				if ( empty( $event['timeslots'] ) ) {
					continue;
				}
				
				foreach ( $event['timeslots'] as $timeslot ) {
					// Mobilize uses Unix timestamps for start_date
					$date = date( 'Y-m-d H:i:s', intval( $timeslot['start_date'] ) );
					
					if ( $this->stage_event( $source->id, $event['id'], $timeslot['id'], $date, $event ) ) {
						$count++;
					}
				}
			}
		}
		return $count;
	}

	/**
	 * Insert session into the bridge table.
	 * Uses UNIQUE constraint to prevent duplicates.
	 */
	private function stage_event( $source_id, $event_id, $session_id, $date, $raw_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pbc_dec_event_bridge';

		return $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO $table (source_id, external_event_id, external_session_id, event_date, raw_json) 
			 VALUES (%d, %s, %s, %s, %s)",
			$source_id, 
			$event_id, 
			$session_id, 
			$date, 
			json_encode( $raw_data )
		) );
	}
}
