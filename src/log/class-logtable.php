<?php
/**
 * Simple email configuration within WordPress.
 *
 * @package sb-simple-smtp
 * @author soup-bowl <code@soupbowl.io>
 * @license MIT
 */

namespace wpsimplesmtp;

use wpsimplesmtp\LogService;

/**
 * Handles the creation and display of the email log table.
 */
class LogTable {
	/**
	 * SMTP logging.
	 *
	 * @var LogService
	 */
	protected $log_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->log_service = new LogService();
	}

	/**
	 * Displays the log table.
	 *
	 * @param integer $page             The page to be displayed.
	 * @param integer $maximum_per_page Limits the table display.
	 */
	public function display( $page, $maximum_per_page = 5 ) {
		$page    = ( $page < 0 ) ? 0 : $page;
		$entries = $this->log_service->get_log_entries( ( $page + 1 ), $maximum_per_page );
		$pages   = $this->log_service->get_log_entry_pages( $maximum_per_page );

		$labels = [
			__( 'Recipient(s)', 'simple-smtp' ),
			__( 'Subject', 'simple-smtp' ),
			__( 'Date', 'simple-smtp' ),
			__( 'Error', 'simple-smtp' ),
		];

		echo wp_kses(
			"<table class=\"wp-list-table widefat fixed striped\">
			<thead>
			<th scope=\"col\" class=\"manage-column column-primary\">{$labels[0]}</th>
			<th scope=\"col\" class=\"manage-column\">{$labels[1]}</th>
			<th scope=\"col\" class=\"manage-column\">{$labels[2]}</th>
			<th scope=\"col\" class=\"manage-column\">{$labels[3]}</th>
			</thead>
			<tbody>",
			$this->allowed_table_html()
		);

		if ( ! empty( $entries ) ) {
			foreach ( $entries as $entry ) {
				$error      = get_post_meta( $entry->get_id(), 'error', true );
				$error      = ( 'WPSS_MAIL_OFF' === $error ) ? __( 'Email was disabled at this time.', 'simple-smtp' ) : $error;
				$recipients = implode( ', ', $entry->get_recipients() );
				$actions    = $this->render_log_entry_buttons( $entry );
				$date       = gmdate( get_option( 'time_format' ) . ', ' . get_option( 'date_format' ), strtotime( $entry->get_timestamp() ) );
				$fail_atr   = ( ! empty( $error ) ) ? 'class="site-archived"' : '';
				echo wp_kses(
					"<tr {$fail_atr}>
					<td class=\"has-row-actions\">{$recipients}{$actions}</td>
					<td>" . $entry->get_subject() . '</td>
					<td><abbr title="' . $entry->get_timestamp() . "\">{$date}</abbr></td>
					<td>{$error}</td>
					</tr>",
					$this->allowed_table_html()
				);
			}
		} else {
			echo wp_kses(
				sprintf(
					'<tr><td colspan="4">%s</td></tr>',
					__( 'Nothing to display.', 'simple-smtp' )
				),
				$this->allowed_table_html()
			);
		}

		echo wp_kses(
			"</tbody>
			<tfoot>
			<th scope=\"col\" class=\"manage-column\">{$labels[0]}</th>
			<th scope=\"col\" class=\"manage-column\">{$labels[1]}</th>
			<th scope=\"col\" class=\"manage-column\">{$labels[2]}</th>
			<th scope=\"col\" class=\"manage-column\">{$labels[3]}</th>
			</tfoot>
			</table>",
			$this->allowed_table_html()
		);

		$page_cu = ( $page + 1 );
		$page_co = ( $pages + 1 );
		// translators: %1$s refers to the current page, %2$s is the amount of pages the table has.
		$message     = sprintf( __( 'Showing page %1$s of %2$s.', 'simple-smtp' ), $page_cu, $page_co );
		$nav_buttons = $this->generate_table_buttons( $page, $pages );

		if ( floatval( 0 ) === $page_co ) {
			// Do not display navigation if 0 pages/entries.
			return;
		}
		echo wp_kses(
			"<p><i>{$message}</i> {$nav_buttons->back} {$nav_buttons->next} {$nav_buttons->delete}</p>",
			[
				'p' => [],
				'i' => [],
				'a' => [
					'href'     => [],
					'class'    => [],
					'disabled' => [],
				],
			]
		);
	}

	/**
	 * Postback navigations, and other functions for the table.
	 *
	 * @param integer $current_page The current page (system, not pretty).
	 * @param integer $max_pages    How many pages the table has to show.
	 * @return stdClass 'next', 'back', and 'delete' HTML buttons.
	 */
	private function generate_table_buttons( $current_page, $max_pages ) {
		$nonce      = [ 'ssnonce' => wp_create_nonce( 'wpss_logtable' ) ];
		$next_label = __( 'Next', 'simple-smtp' );
		$back_label = __( 'Previous', 'simple-smtp' );
		$current    = admin_url( 'options-general.php?page=wpsimplesmtp' );
		$next_url   = add_query_arg(
			[
				'wpss_page' => ( $current_page + 1 ),
				$nonce,
			],
			$current
		);
		$back_url   = add_query_arg(
			[
				'wpss_page' => ( $current_page - 1 ),
				$nonce,
			],
			$current
		);
		$next_allow = ( $current_page >= $max_pages ) ? 'disabled' : '';
		$back_allow = ( $current_page <= 0 ) ? 'disabled' : '';

		$purge_all_label = __( 'Purge Log', 'simple-smtp' );
		$purge_all_url   = esc_html(
			add_query_arg(
				array( 'ssnonce' => wp_create_nonce( 'wpss_purgelog' ) ),
				menu_page_url( 'wpsimplesmtp', false )
			)
		) . '&delete_all';

		return (object) [
			'next'   => "<a href='{$next_url}' class='button' {$next_allow}>{$next_label}</a>",
			'back'   => "<a href='{$back_url}' class='button' {$back_allow}>{$back_label}</a>",
			'delete' => "<a href='{$purge_all_url}' class='button'>{$purge_all_label}</a>",
		];
	}

	/**
	 * Renders actionable event buttons underneath the log entry.
	 *
	 * @param Log $entry Object from the Log DB.
	 * @return string row-action html.
	 */
	private function render_log_entry_buttons( $entry ) {
		$recents      = get_option( 'wpss_resent', [] );
		$resend_param = [
			'eid'     => $entry->get_id(),
			'ssnonce' => wp_create_nonce( 'wpss_action' ),
		];

		$view_label   = __( 'View', 'simple-smtp' );
		$resend_label = __( 'Resend', 'simple-smtp' );
		$delete_label = __( 'Delete', 'simple-smtp' );

		$view_url   = esc_html( add_query_arg( 'eid', $entry->get_id(), menu_page_url( 'wpsimplesmtp', false ) ) );
		$resend_url = esc_html( add_query_arg( $resend_param, menu_page_url( 'wpsimplesmtp', false ) ) ) . '&resend';
		$delete_url = esc_html( add_query_arg( $resend_param, menu_page_url( 'wpsimplesmtp', false ) ) ) . '&delete';

		$view   = "<span class=\"view\"><a href=\"{$view_url}\" aria-label=\"View\">{$view_label}</a></span>";
		$delete = "<span class=\"delete\"><a href=\"{$delete_url}\" aria-label=\"View\">{$delete_label}</a></span>";
		$resend = '';
		if ( ! in_array( (int) $entry->get_id(), $recents, true ) ) {
			$resend = "<span class=\"view\"><a href=\"{$resend_url}\" aria-label=\"View\">{$resend_label}</a></span>";
		} else {
			$resend = '<span class="view">Resent</span>';
		}

		$row_actions = "<div class=\"row-actions\">{$view} | {$resend} | {$delete}</div>";

		return $row_actions;
	}

	/**
	 * Array for kses that allows table-related HTML only.
	 *
	 * @return array
	 */
	private function allowed_table_html() {
		return [
			'table' => [
				'class' => [],
			],
			'thead' => [],
			'tfoot' => [],
			'tbody' => [],
			'th'    => [
				'scope' => [],
				'class' => [],
			],
			'tr'    => [
				'class' => [],
			],
			'td'    => [
				'class'   => [],
				'colspan' => [],
			],
			'div'   => [
				'class' => [],
			],
			'span'  => [
				'class' => [],
			],
			'a'     => [
				'href' => [],
			],
			'abbr'  => [
				'title' => [],
			],
		];
	}
}
