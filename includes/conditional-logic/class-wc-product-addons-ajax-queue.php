<?php
/**
 * WooCommerce Product Add-ons AJAX Queue Manager
 *
 * Handles AJAX request queuing, cancellation, and response ordering
 * to prevent race conditions in conditional logic evaluation.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Queue Manager Class
 *
 * @class   WC_Product_Addons_Ajax_Queue
 * @version 4.1.0
 */
class WC_Product_Addons_Ajax_Queue {

	/**
	 * Queue of pending requests
	 *
	 * @var array
	 */
	private static $queue = array();

	/**
	 * Active request tracking
	 *
	 * @var array
	 */
	private static $active_requests = array();

	/**
	 * Request sequence counter
	 *
	 * @var int
	 */
	private static $sequence_counter = 0;

	/**
	 * Maximum concurrent requests
	 *
	 * @var int
	 */
	private static $max_concurrent = 2;

	/**
	 * Request timeout in milliseconds
	 *
	 * @var int
	 */
	private static $request_timeout = 5000;

	/**
	 * Initialize AJAX queue handling
	 */
	public static function init() {
		// Add sequence number to all conditional logic AJAX requests
		add_filter( 'wp_ajax_wc_product_addons_evaluate_rules', array( __CLASS__, 'handle_queued_request' ), 5 );
		add_filter( 'wp_ajax_nopriv_wc_product_addons_evaluate_rules', array( __CLASS__, 'handle_queued_request' ), 5 );
		
		// Add queue management headers
		add_action( 'send_headers', array( __CLASS__, 'add_queue_headers' ) );
		
		// Clean up stale requests periodically
		add_action( 'wp_ajax_wc_product_addons_cleanup_queue', array( __CLASS__, 'cleanup_stale_requests' ) );
	}

	/**
	 * Handle queued AJAX request
	 */
	public static function handle_queued_request() {
		// Get request sequence number
		$sequence = isset( $_POST['_sequence'] ) ? absint( $_POST['_sequence'] ) : 0;
		$request_id = isset( $_POST['_request_id'] ) ? sanitize_text_field( $_POST['_request_id'] ) : '';
		
		// Check if this is a cancellation request
		if ( isset( $_POST['_cancel_previous'] ) && $_POST['_cancel_previous'] ) {
			self::cancel_previous_requests( $sequence );
		}
		
		// Validate sequence
		if ( ! self::is_valid_sequence( $sequence, $request_id ) ) {
			wp_send_json_error( array(
				'code' => 'outdated_request',
				'message' => __( 'This request has been superseded by a newer one.', 'woocommerce-product-addons-extra-digital' ),
				'sequence' => $sequence,
			) );
		}
		
		// Track active request
		self::track_request( $request_id, $sequence );
		
		// Add sequence to response
		add_filter( 'wp_ajax_response', array( __CLASS__, 'add_sequence_to_response' ), 10, 2 );
	}

	/**
	 * Check if sequence is valid
	 *
	 * @param int    $sequence    Request sequence number
	 * @param string $request_id  Request identifier
	 * @return bool
	 */
	private static function is_valid_sequence( $sequence, $request_id ) {
		// Get user/session specific data
		$user_key = self::get_user_key();
		
		// Check if there are newer requests for this user
		$newest_sequence = get_transient( 'wc_pao_newest_sequence_' . $user_key );
		
		if ( $newest_sequence && $sequence < $newest_sequence ) {
			return false;
		}
		
		// Update newest sequence
		set_transient( 'wc_pao_newest_sequence_' . $user_key, $sequence, 300 );
		
		return true;
	}

	/**
	 * Cancel previous requests
	 *
	 * @param int $current_sequence Current request sequence
	 */
	private static function cancel_previous_requests( $current_sequence ) {
		$user_key = self::get_user_key();
		
		// Mark all previous requests as cancelled
		$cancelled_count = 0;
		
		foreach ( self::$active_requests as $request_id => $data ) {
			if ( $data['user_key'] === $user_key && $data['sequence'] < $current_sequence ) {
				self::$active_requests[ $request_id ]['cancelled'] = true;
				$cancelled_count++;
			}
		}
		
		if ( $cancelled_count > 0 ) {
			error_log( sprintf( 'WC Product Addons: Cancelled %d previous requests', $cancelled_count ) );
		}
	}

	/**
	 * Track active request
	 *
	 * @param string $request_id Request identifier
	 * @param int    $sequence   Request sequence
	 */
	private static function track_request( $request_id, $sequence ) {
		self::$active_requests[ $request_id ] = array(
			'sequence'  => $sequence,
			'user_key'  => self::get_user_key(),
			'timestamp' => microtime( true ),
			'cancelled' => false,
		);
		
		// Clean up old requests
		self::cleanup_stale_requests();
	}

	/**
	 * Add sequence to AJAX response
	 *
	 * @param mixed $response Response data
	 * @param array $data     Original data
	 * @return mixed Modified response
	 */
	public static function add_sequence_to_response( $response, $data = array() ) {
		if ( is_array( $response ) ) {
			$response['_sequence'] = isset( $_POST['_sequence'] ) ? absint( $_POST['_sequence'] ) : 0;
			$response['_request_id'] = isset( $_POST['_request_id'] ) ? sanitize_text_field( $_POST['_request_id'] ) : '';
		}
		
		return $response;
	}

	/**
	 * Add queue management headers
	 */
	public static function add_queue_headers() {
		if ( wp_doing_ajax() ) {
			// Add cache control headers
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
			
			// Add custom queue headers
			header( 'X-WC-PAO-Queue-Enabled: 1' );
			header( 'X-WC-PAO-Max-Concurrent: ' . self::$max_concurrent );
		}
	}

	/**
	 * Clean up stale requests
	 */
	public static function cleanup_stale_requests() {
		$now = microtime( true );
		$timeout_seconds = self::$request_timeout / 1000;
		
		foreach ( self::$active_requests as $request_id => $data ) {
			if ( ( $now - $data['timestamp'] ) > $timeout_seconds ) {
				unset( self::$active_requests[ $request_id ] );
			}
		}
		
		// Clean up old transients
		global $wpdb;
		$wpdb->query( 
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_value < %d",
				'_transient_timeout_wc_pao_newest_sequence_%',
				time()
			)
		);
	}

	/**
	 * Get user key for request tracking
	 *
	 * @return string User key
	 */
	private static function get_user_key() {
		if ( is_user_logged_in() ) {
			return 'user_' . get_current_user_id();
		}
		
		// For guests, use session ID or IP
		if ( WC()->session ) {
			return 'session_' . WC()->session->get_customer_id();
		}
		
		return 'ip_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
	}

	/**
	 * Generate JavaScript queue manager
	 *
	 * @return string JavaScript code
	 */
	public static function get_queue_manager_js() {
		ob_start();
		?>
		<script type="text/javascript">
		(function($) {
			'use strict';
			
			window.WC_PAO_Ajax_Queue = {
				sequence: 0,
				activeRequests: new Map(),
				maxConcurrent: <?php echo esc_js( self::$max_concurrent ); ?>,
				requestTimeout: <?php echo esc_js( self::$request_timeout ); ?>,
				
				/**
				 * Create queued AJAX request
				 */
				request: function(options) {
					var self = this;
					
					// Generate request ID and sequence
					var requestId = 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
					var sequence = ++this.sequence;
					
					// Cancel previous requests if needed
					if (options.cancelPrevious !== false) {
						this.cancelPendingRequests();
					}
					
					// Add queue metadata
					options.data = options.data || {};
					options.data._sequence = sequence;
					options.data._request_id = requestId;
					options.data._cancel_previous = options.cancelPrevious !== false;
					
					// Wrap success callback
					var originalSuccess = options.success;
					options.success = function(response) {
						// Check if this request was cancelled
						var request = self.activeRequests.get(requestId);
						if (request && request.cancelled) {
							console.log('Request cancelled:', requestId);
							return;
						}
						
						// Check sequence validity
						if (response.data && response.data._sequence && response.data._sequence < self.sequence) {
							console.log('Outdated response ignored:', response.data._sequence);
							return;
						}
						
						// Call original success callback
						if (originalSuccess) {
							originalSuccess(response);
						}
						
						// Clean up
						self.activeRequests.delete(requestId);
					};
					
					// Wrap error callback
					var originalError = options.error;
					options.error = function(xhr, status, error) {
						// Clean up
						self.activeRequests.delete(requestId);
						
						// Call original error callback
						if (originalError) {
							originalError(xhr, status, error);
						}
					};
					
					// Set timeout
					options.timeout = options.timeout || this.requestTimeout;
					
					// Track request
					var jqXHR = $.ajax(options);
					this.activeRequests.set(requestId, {
						id: requestId,
						sequence: sequence,
						jqXHR: jqXHR,
						cancelled: false,
						timestamp: Date.now()
					});
					
					// Clean up old requests
					this.cleanupStaleRequests();
					
					return jqXHR;
				},
				
				/**
				 * Cancel pending requests
				 */
				cancelPendingRequests: function() {
					this.activeRequests.forEach(function(request, id) {
						if (!request.cancelled) {
							request.cancelled = true;
							request.jqXHR.abort();
							console.log('Cancelled request:', id);
						}
					});
				},
				
				/**
				 * Clean up stale requests
				 */
				cleanupStaleRequests: function() {
					var now = Date.now();
					var timeout = this.requestTimeout;
					
					this.activeRequests.forEach(function(request, id) {
						if (now - request.timestamp > timeout) {
							this.activeRequests.delete(id);
						}
					}, this);
				},
				
				/**
				 * Get active request count
				 */
				getActiveCount: function() {
					return this.activeRequests.size;
				},
				
				/**
				 * Check if queue is full
				 */
				isQueueFull: function() {
					return this.getActiveCount() >= this.maxConcurrent;
				}
			};
			
			// Expose to jQuery
			$.wcPaoAjaxQueue = window.WC_PAO_Ajax_Queue;
			
		})(jQuery);
		</script>
		<?php
		return ob_get_clean();
	}
}