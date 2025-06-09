<?php
/**
 * WooCommerce Product Add-ons Rule Conflict Resolver
 *
 * Handles conflict resolution when multiple rules affect the same addon
 * with potentially conflicting actions.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule Conflict Resolver Class
 *
 * @class   WC_Product_Addons_Rule_Conflict_Resolver
 * @version 4.1.0
 */
class WC_Product_Addons_Rule_Conflict_Resolver {

	/**
	 * Resolution strategies
	 */
	const STRATEGY_PRIORITY = 'priority';
	const STRATEGY_FIRST_WINS = 'first_wins';
	const STRATEGY_LAST_WINS = 'last_wins';
	const STRATEGY_MERGE = 'merge';
	const STRATEGY_MOST_RESTRICTIVE = 'most_restrictive';
	const STRATEGY_LEAST_RESTRICTIVE = 'least_restrictive';

	/**
	 * Current resolution strategy
	 *
	 * @var string
	 */
	private $strategy = self::STRATEGY_PRIORITY;

	/**
	 * Conflict log
	 *
	 * @var array
	 */
	private $conflict_log = array();

	/**
	 * Constructor
	 *
	 * @param string $strategy Resolution strategy
	 */
	public function __construct( $strategy = self::STRATEGY_PRIORITY ) {
		$this->strategy = $strategy;
	}

	/**
	 * Resolve conflicts between multiple rule actions
	 *
	 * @param array $actions Array of actions from different rules
	 * @return array Resolved actions
	 */
	public function resolve_conflicts( $actions ) {
		if ( empty( $actions ) ) {
			return array();
		}

		// Group actions by target and type
		$grouped_actions = $this->group_actions( $actions );
		$resolved_actions = array();

		foreach ( $grouped_actions as $target => $type_groups ) {
			foreach ( $type_groups as $type => $action_list ) {
				if ( count( $action_list ) === 1 ) {
					// No conflict
					$resolved_actions[] = reset( $action_list );
				} else {
					// Conflict detected
					$this->log_conflict( $target, $type, $action_list );
					$resolved = $this->resolve_action_conflict( $type, $action_list );
					if ( $resolved ) {
						$resolved_actions[] = $resolved;
					}
				}
			}
		}

		return $resolved_actions;
	}

	/**
	 * Group actions by target and type
	 *
	 * @param array $actions Actions to group
	 * @return array Grouped actions
	 */
	private function group_actions( $actions ) {
		$grouped = array();

		foreach ( $actions as $action ) {
			$target = $this->get_action_target( $action );
			$type = $this->get_action_type( $action );
			
			if ( ! isset( $grouped[ $target ] ) ) {
				$grouped[ $target ] = array();
			}
			if ( ! isset( $grouped[ $target ][ $type ] ) ) {
				$grouped[ $target ][ $type ] = array();
			}
			
			$grouped[ $target ][ $type ][] = $action;
		}

		return $grouped;
	}

	/**
	 * Get action target identifier
	 *
	 * @param array $action Action configuration
	 * @return string Target identifier
	 */
	private function get_action_target( $action ) {
		if ( isset( $action['target_addon'] ) ) {
			$target = $action['target_addon'];
			if ( isset( $action['target_option'] ) ) {
				$target .= '::' . $action['target_option'];
			}
			return $target;
		}
		
		if ( isset( $action['config']['action_addon'] ) ) {
			$target = $action['config']['action_addon'];
			if ( isset( $action['config']['action_option'] ) ) {
				$target .= '::' . $action['config']['action_option'];
			}
			return $target;
		}
		
		return 'unknown';
	}

	/**
	 * Get action type category
	 *
	 * @param array $action Action configuration
	 * @return string Action type category
	 */
	private function get_action_type( $action ) {
		$type = $action['type'] ?? '';
		
		// Group related action types
		$type_groups = array(
			'visibility' => array( 'show_addon', 'hide_addon', 'show_option', 'hide_option' ),
			'requirement' => array( 'make_required', 'make_optional' ),
			'price' => array( 'set_price', 'adjust_price', 'price_modifier' ),
			'content' => array( 'set_label', 'set_description' ),
		);
		
		foreach ( $type_groups as $group => $types ) {
			if ( in_array( $type, $types, true ) ) {
				return $group;
			}
		}
		
		return $type;
	}

	/**
	 * Resolve conflict between actions of the same type
	 *
	 * @param string $type        Action type
	 * @param array  $actions     Conflicting actions
	 * @return array|null Resolved action or null
	 */
	private function resolve_action_conflict( $type, $actions ) {
		switch ( $this->strategy ) {
			case self::STRATEGY_PRIORITY:
				return $this->resolve_by_priority( $actions );
				
			case self::STRATEGY_FIRST_WINS:
				return reset( $actions );
				
			case self::STRATEGY_LAST_WINS:
				return end( $actions );
				
			case self::STRATEGY_MERGE:
				return $this->resolve_by_merge( $type, $actions );
				
			case self::STRATEGY_MOST_RESTRICTIVE:
				return $this->resolve_by_restrictiveness( $type, $actions, true );
				
			case self::STRATEGY_LEAST_RESTRICTIVE:
				return $this->resolve_by_restrictiveness( $type, $actions, false );
				
			default:
				return $this->resolve_by_priority( $actions );
		}
	}

	/**
	 * Resolve by rule priority
	 *
	 * @param array $actions Actions to resolve
	 * @return array Highest priority action
	 */
	private function resolve_by_priority( $actions ) {
		usort( $actions, function( $a, $b ) {
			$priority_a = $a['rule_priority'] ?? 10;
			$priority_b = $b['rule_priority'] ?? 10;
			
			// Lower number = higher priority
			if ( $priority_a === $priority_b ) {
				// If same priority, use rule ID as tiebreaker
				$id_a = $a['rule_id'] ?? 0;
				$id_b = $b['rule_id'] ?? 0;
				return $id_a <=> $id_b;
			}
			
			return $priority_a <=> $priority_b;
		});
		
		return reset( $actions );
	}

	/**
	 * Resolve by merging compatible actions
	 *
	 * @param string $type    Action type
	 * @param array  $actions Actions to merge
	 * @return array|null Merged action or null if not mergeable
	 */
	private function resolve_by_merge( $type, $actions ) {
		switch ( $type ) {
			case 'price':
				return $this->merge_price_actions( $actions );
				
			case 'content':
				return $this->merge_content_actions( $actions );
				
			default:
				// For non-mergeable types, fall back to priority
				return $this->resolve_by_priority( $actions );
		}
	}

	/**
	 * Merge price actions
	 *
	 * @param array $actions Price actions to merge
	 * @return array Merged price action
	 */
	private function merge_price_actions( $actions ) {
		$base_action = reset( $actions );
		$total_adjustment = 0;
		$has_absolute = false;
		$absolute_price = 0;
		
		foreach ( $actions as $action ) {
			if ( $action['type'] === 'set_price' ) {
				$has_absolute = true;
				$absolute_price = $action['new_price'] ?? 0;
			} elseif ( $action['type'] === 'adjust_price' ) {
				$adjustment = $action['adjustment'] ?? array();
				if ( isset( $adjustment['amount'] ) ) {
					if ( $adjustment['type'] === 'percentage' ) {
						// Convert percentage to decimal for later application
						$total_adjustment += $adjustment['amount'] / 100;
					} else {
						$total_adjustment += $adjustment['amount'];
					}
				}
			}
		}
		
		// If we have an absolute price, apply adjustments to it
		if ( $has_absolute ) {
			$base_action['type'] = 'set_price';
			$base_action['new_price'] = $absolute_price + $total_adjustment;
		} else {
			$base_action['type'] = 'adjust_price';
			$base_action['adjustment'] = array(
				'type' => 'fixed',
				'amount' => $total_adjustment,
			);
		}
		
		$base_action['merged_from'] = count( $actions ) . ' rules';
		
		return $base_action;
	}

	/**
	 * Merge content actions
	 *
	 * @param array $actions Content actions to merge
	 * @return array Merged content action
	 */
	private function merge_content_actions( $actions ) {
		$base_action = reset( $actions );
		$contents = array();
		
		foreach ( $actions as $action ) {
			if ( $action['type'] === 'set_label' && isset( $action['new_label'] ) ) {
				$contents[] = $action['new_label'];
			} elseif ( $action['type'] === 'set_description' && isset( $action['new_description'] ) ) {
				$contents[] = $action['new_description'];
			}
		}
		
		// Concatenate with separator
		$base_action['new_' . ( strpos( $base_action['type'], 'label' ) !== false ? 'label' : 'description' )] = implode( ' | ', $contents );
		$base_action['merged_from'] = count( $actions ) . ' rules';
		
		return $base_action;
	}

	/**
	 * Resolve by restrictiveness
	 *
	 * @param string $type            Action type
	 * @param array  $actions         Actions to resolve
	 * @param bool   $most_restrictive Whether to choose most restrictive
	 * @return array Resolved action
	 */
	private function resolve_by_restrictiveness( $type, $actions, $most_restrictive = true ) {
		switch ( $type ) {
			case 'visibility':
				// Hide is more restrictive than show
				foreach ( $actions as $action ) {
					if ( in_array( $action['type'], array( 'hide_addon', 'hide_option' ), true ) ) {
						return $most_restrictive ? $action : null;
					}
				}
				return $most_restrictive ? null : reset( $actions );
				
			case 'requirement':
				// Required is more restrictive than optional
				foreach ( $actions as $action ) {
					if ( $action['type'] === 'make_required' ) {
						return $most_restrictive ? $action : null;
					}
				}
				return $most_restrictive ? null : reset( $actions );
				
			case 'price':
				// Higher price is more restrictive
				usort( $actions, function( $a, $b ) {
					$price_a = $this->get_effective_price( $a );
					$price_b = $this->get_effective_price( $b );
					return $price_b <=> $price_a;
				});
				return $most_restrictive ? reset( $actions ) : end( $actions );
				
			default:
				return $this->resolve_by_priority( $actions );
		}
	}

	/**
	 * Get effective price from action
	 *
	 * @param array $action Price action
	 * @return float Effective price
	 */
	private function get_effective_price( $action ) {
		if ( $action['type'] === 'set_price' ) {
			return $action['new_price'] ?? 0;
		} elseif ( $action['type'] === 'adjust_price' && isset( $action['adjustment']['amount'] ) ) {
			return $action['adjustment']['amount'];
		}
		return 0;
	}

	/**
	 * Log conflict for debugging
	 *
	 * @param string $target  Action target
	 * @param string $type    Action type
	 * @param array  $actions Conflicting actions
	 */
	private function log_conflict( $target, $type, $actions ) {
		$this->conflict_log[] = array(
			'target'     => $target,
			'type'       => $type,
			'actions'    => $actions,
			'timestamp'  => current_time( 'timestamp' ),
			'resolution' => $this->strategy,
		);
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'WC Product Addons: Conflict detected for %s (%s) - %d conflicting actions, using %s strategy',
				$target,
				$type,
				count( $actions ),
				$this->strategy
			) );
		}
	}

	/**
	 * Get conflict log
	 *
	 * @return array Conflict log entries
	 */
	public function get_conflict_log() {
		return $this->conflict_log;
	}

	/**
	 * Clear conflict log
	 */
	public function clear_conflict_log() {
		$this->conflict_log = array();
	}

	/**
	 * Set resolution strategy
	 *
	 * @param string $strategy New strategy
	 */
	public function set_strategy( $strategy ) {
		$valid_strategies = array(
			self::STRATEGY_PRIORITY,
			self::STRATEGY_FIRST_WINS,
			self::STRATEGY_LAST_WINS,
			self::STRATEGY_MERGE,
			self::STRATEGY_MOST_RESTRICTIVE,
			self::STRATEGY_LEAST_RESTRICTIVE,
		);
		
		if ( in_array( $strategy, $valid_strategies, true ) ) {
			$this->strategy = $strategy;
		}
	}

	/**
	 * Get available resolution strategies
	 *
	 * @return array Strategy options
	 */
	public static function get_available_strategies() {
		return array(
			self::STRATEGY_PRIORITY          => __( 'Priority (recommended)', 'woocommerce-product-addons-extra-digital' ),
			self::STRATEGY_FIRST_WINS        => __( 'First Rule Wins', 'woocommerce-product-addons-extra-digital' ),
			self::STRATEGY_LAST_WINS         => __( 'Last Rule Wins', 'woocommerce-product-addons-extra-digital' ),
			self::STRATEGY_MERGE             => __( 'Merge Compatible Actions', 'woocommerce-product-addons-extra-digital' ),
			self::STRATEGY_MOST_RESTRICTIVE  => __( 'Most Restrictive', 'woocommerce-product-addons-extra-digital' ),
			self::STRATEGY_LEAST_RESTRICTIVE => __( 'Least Restrictive', 'woocommerce-product-addons-extra-digital' ),
		);
	}
}