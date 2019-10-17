<?php
/**
 * Simple email configuration within WordPress.
 *
 * @package sb-simple-smtp
 * @author soup-bowl <code@soupbowl.io>
 * @license MIT
 */

namespace wpsimplesmtp;

use wpsimplesmtp\Options;
use wpsimplesmtp\Log;

/**
 * Configures PHPMailer to use our settings rather than the default.
 */
class Mail {
	/**
	 * SMTP mailer options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * SMTP logging.
	 *
	 * @var Log
	 */
	protected $log;

	/**
	 * Registers the relevant WordPress hooks upon creation.
	 */
	public function __construct() {
		$this->options = new Options();
		$this->log     = new Log();

		add_action( 'phpmailer_init', [ &$this, 'process_mail' ] );

		$log_status = $this->options->get( 'log' );
		if ( ! empty( $log_status ) && '1' === $log_status->value ) {
			add_action( 'wp_mail_failed', [ &$this, 'process_error' ] );
		}
	}

	/**
	 * Hooks into the WordPress mail routine to re-configure PHP Mailer.
	 *
	 * @param PHPMailer $phpmailer The configuration object.
	 */
	public function process_mail( $phpmailer ) {
		$config = get_option( 'wpssmtp_smtp' );

		if ( ! empty( $config ) ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->Host     = $this->options->get( 'host' )->value;
			$phpmailer->Port     = $this->options->get( 'port' )->value;
			$phpmailer->Username = $this->options->get( 'user' )->value;
			$phpmailer->Password = $this->options->get( 'pass' )->value;
			$phpmailer->SMTPAuth = $this->options->get( 'auth' )->value;
			$phpmailer->addCustomHeader( 'X-Wpm-Guid', 'custom-value' );

			$phpmailer->IsSMTP();

			if ( $this->options->get( 'log' )->value === '1' ) {
				$recipients      = $phpmailer->getAllRecipientAddresses();
				$recipient_array = [];
				foreach ( $recipients as $recipient => $junk ) {
					$recipient_array[] = $recipient;
				}

				$this->log->create_log_table();

				$this->log->new_log_entry(
					serialize( $recipient_array ),
					$phpmailer->Body,
					current_time( 'mysql' )
				);
			}
			// phpcs:enable
		}

		return $phpmailer;
	}

	/**
	 * Handles an error response from the WordPress system.
	 *
	 * @param WP_Error $error The error thrown by the mailer.
	 */
	public function process_error( $error ) {
		$this->log->new_log_entry(
			serialize( $error->get_error_data( 'wp_mail_failed' )['to'] ),
			$error->get_error_data( 'wp_mail_failed' )['message'],
			current_time( 'mysql' ),
			$error->get_error_message( 'wp_mail_failed' )
		);
	}
}
