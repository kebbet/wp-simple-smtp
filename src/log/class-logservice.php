<?php
/**
 * Simple email configuration within WordPress.
 *
 * @package sb-simple-smtp
 * @author soup-bowl <code@soupbowl.io>
 * @license MIT
 */

namespace wpsimplesmtp;

use wpsimplesmtp\Log;
use wpsimplesmtp\LogAttachment;

use WP_Query;

/**
 * Handles the processing and display of the email log.
 */
class LogService {
	/**
	 * Name of the custom post type used for storing logs.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->post_type = 'sbss_email_log';
	}

	/**
	 * Register the log storage CPT within WordPress.
	 */
	public function register_log_storage() {
		register_post_type( $this->post_type );
	}

	/**
	 * Creates a new log entry.
	 *
	 * @param Log $log The log object.
	 * @return integer ID of the newly-inserted entry.
	 */
	public function new_log_entry( $log ) {
		$post_id = wp_insert_post(
			[
				'post_title'   => $log->get_subject(),
				'post_content' => $log->get_body(),
				'post_status'  => 'publish',
				'post_type'    => $this->post_type,
				'meta_input'   => [
					'recipients'  => wp_json_encode( $log->get_recipients() ),
					'headers'     => wp_json_encode( $log->get_headers() ),
					'attachments' => $log->get_attachments(),
					'timestamp'   => $log->get_timestamp(),
					'error'       => $log->get_error(),
				],
			]
		);

		return $post_id;
	}

	/**
	 * Updates the provided ID with an error message.
	 *
	 * @param integer $id    ID of the email log entry.
	 * @param string  $error Error message to be stored.
	 * @return void
	 */
	public function log_entry_error( $id, $error ) {
		update_post_meta( $id, 'error', $error );
	}

	/**
	 * Gets a single log entry based upon the ID.
	 *
	 * @param integer $id Log ID to retrieve details of.
	 * @return Log
	 */
	public function get_log_entry_by_id( $id ) {
		$post = get_post( $id );

		return $this->wp_to_obj( $post );
	}

	/**
	 * Gets the log entries stored. Pagination can be optionally specified.
	 *
	 * @param integer $page  What page to show. Automatically calculated with limit.
	 * @param integer $limit How many to retrieve in this call.
	 * @return Log[]
	 */
	public function get_log_entries( $page = 0, $limit = 0 ) {
		$get_posts = new WP_Query();
		$get_posts->query(
			[
				'post_type'      => $this->post_type,
				'posts_per_page' => $limit,
				'paged'          => $page,
			]
		);

		$coll  = [];
		$posts = $get_posts->get_posts();
		foreach ( $posts as $post ) {
			$coll[] = $this->wp_to_obj( $post );
		}

		return $coll;
	}

	/**
	 * Gets the log pagination.
	 *
	 * @param integer $limit How many were retrieved in the call.
	 * @return integer
	 */
	public function get_log_entry_pages( $limit ) {
		$count = (int) wp_count_posts( $this->post_type )->publish;

		if ( false !== $count ) {
			$count = $count - intval( 1 );
			return floor( $count / $limit );
		} else {
			return 1;
		}
	}

	/**
	 * Gets an object collection of attachments, if the entry had them.
	 *
	 * @param integer $id ID of the email log entry.
	 * @return LogAttachment[]|null
	 */
	public function get_log_entry_attachments( $id ) {
		$attachments = get_post_meta( $id, 'attachments', true );

		if ( ! empty( $attachments ) ) {
			$file_collection = [];
			foreach ( $attachments as $attachment ) {
				$file_collection[] = ( new LogAttachment() )->unpack( $attachment );
			}

			return $file_collection;
		} else {
			return null;
		}
	}

	/**
	 * Deletes a log entry.
	 *
	 * @param integer $id WordPress post ID.
	 * @return boolean
	 */
	public function delete_log_entry( $id ) {
		$post = get_post( $id );

		if ( $this->post_type === $post->post_type ) {
			$r = wp_delete_post( $id );
			if ( ! empty( $r ) || false !== $r ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Deletes all log entries.
	 *
	 * @return boolean
	 */
	public function delete_all_logs() {
		$all = get_posts(
			array(
				'post_type'   => $this->post_type,
				'numberposts' => -1,
			)
		);

		foreach ( $all as $log ) {
			wp_delete_post( $log->ID );
		}

		return true;
	}

	/**
	 * Converts the WordPress post object to the WP SMTP Log object.
	 *
	 * @param WP_Post $post The object.
	 * @return Log
	 */
	private function wp_to_obj( $post ) {
		if ( empty( $post ) ) {
			return null;
		}

		$log = new Log();
		$log->set_id( $post->ID );
		$log->set_subject( $post->post_title );
		$log->set_body( $post->post_content );
		$log->set_recipients( json_decode( get_post_meta( $post->ID, 'recipients', true ) ) );
		$log->set_headers( json_decode( get_post_meta( $post->ID, 'headers', true ) ) );
		$log->set_headers_unified( get_post_meta( $post->ID, 'headers', true ) );
		$log->set_error( get_post_meta( $post->ID, 'error', true ) );
		$log->set_attachments( $this->get_log_entry_attachments( $post->ID ) );
		$log->set_timestamp( get_post_meta( $post->ID, 'timestamp', true ) );

		return $log;
	}

}
