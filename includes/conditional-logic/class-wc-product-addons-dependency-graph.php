<?php
/**
 * WooCommerce Product Add-ons Dependency Graph
 *
 * Handles dependency graph construction and circular dependency detection
 * for conditional logic rules.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dependency Graph Class
 *
 * @class   WC_Product_Addons_Dependency_Graph
 * @version 4.1.0
 */
class WC_Product_Addons_Dependency_Graph {

	/**
	 * Graph adjacency list
	 *
	 * @var array
	 */
	private $graph = array();

	/**
	 * Visited nodes for DFS
	 *
	 * @var array
	 */
	private $visited = array();

	/**
	 * Recursion stack for cycle detection
	 *
	 * @var array
	 */
	private $rec_stack = array();

	/**
	 * Detected cycles
	 *
	 * @var array
	 */
	private $cycles = array();

	/**
	 * Topological order of nodes
	 *
	 * @var array
	 */
	private $topological_order = array();

	/**
	 * Add an edge to the graph
	 *
	 * @param string $from Source node
	 * @param string $to   Destination node
	 */
	public function add_edge( $from, $to ) {
		if ( ! isset( $this->graph[ $from ] ) ) {
			$this->graph[ $from ] = array();
		}
		if ( ! in_array( $to, $this->graph[ $from ], true ) ) {
			$this->graph[ $from ][] = $to;
		}
		
		// Ensure destination node exists in graph
		if ( ! isset( $this->graph[ $to ] ) ) {
			$this->graph[ $to ] = array();
		}
	}

	/**
	 * Build dependency graph from rules
	 *
	 * @param array $rules All conditional rules
	 */
	public function build_from_rules( $rules ) {
		$this->graph = array();
		
		foreach ( $rules as $rule_id => $rule ) {
			$target_addon = $this->get_rule_target( $rule );
			
			// Find dependencies (conditions that depend on other addons)
			$dependencies = $this->extract_dependencies( $rule );
			
			foreach ( $dependencies as $dependency ) {
				// Edge from dependency to target (dependency must be evaluated first)
				$this->add_edge( $dependency, $target_addon );
			}
		}
	}

	/**
	 * Get the target addon for a rule
	 *
	 * @param array $rule Rule configuration
	 * @return string Target addon identifier
	 */
	private function get_rule_target( $rule ) {
		if ( isset( $rule['target_addon'] ) ) {
			return $rule['target_addon'];
		}
		
		// Extract from actions
		if ( isset( $rule['actions'] ) && is_array( $rule['actions'] ) ) {
			foreach ( $rule['actions'] as $action ) {
				if ( isset( $action['config']['action_addon'] ) ) {
					return $action['config']['action_addon'];
				}
				if ( isset( $action['target_addon'] ) ) {
					return $action['target_addon'];
				}
			}
		}
		
		return 'unknown_' . uniqid();
	}

	/**
	 * Extract addon dependencies from rule conditions
	 *
	 * @param array $rule Rule configuration
	 * @return array List of addon dependencies
	 */
	private function extract_dependencies( $rule ) {
		$dependencies = array();
		
		if ( ! isset( $rule['conditions'] ) || ! is_array( $rule['conditions'] ) ) {
			return $dependencies;
		}
		
		foreach ( $rule['conditions'] as $condition ) {
			$type = $condition['type'] ?? '';
			
			switch ( $type ) {
				case 'addon_selected':
				case 'addon_field':
					if ( isset( $condition['config']['condition_addon'] ) ) {
						$dependencies[] = $condition['config']['condition_addon'];
					}
					break;
					
				case 'rule':
					if ( isset( $condition['target_addon'] ) ) {
						$dependencies[] = $condition['target_addon'];
					}
					break;
			}
		}
		
		return array_unique( $dependencies );
	}

	/**
	 * Detect all cycles in the graph
	 *
	 * @return array Array of detected cycles
	 */
	public function detect_cycles() {
		$this->cycles = array();
		$this->visited = array();
		$this->rec_stack = array();
		
		foreach ( array_keys( $this->graph ) as $node ) {
			if ( ! isset( $this->visited[ $node ] ) ) {
				$path = array();
				$this->dfs_cycle_detect( $node, $path );
			}
		}
		
		return $this->cycles;
	}

	/**
	 * DFS helper for cycle detection
	 *
	 * @param string $node Current node
	 * @param array  $path Current path
	 */
	private function dfs_cycle_detect( $node, &$path ) {
		$this->visited[ $node ] = true;
		$this->rec_stack[ $node ] = true;
		$path[] = $node;
		
		if ( isset( $this->graph[ $node ] ) ) {
			foreach ( $this->graph[ $node ] as $neighbor ) {
				if ( ! isset( $this->visited[ $neighbor ] ) ) {
					$this->dfs_cycle_detect( $neighbor, $path );
				} elseif ( isset( $this->rec_stack[ $neighbor ] ) && $this->rec_stack[ $neighbor ] ) {
					// Found a cycle
					$cycle_start = array_search( $neighbor, $path );
					if ( $cycle_start !== false ) {
						$cycle = array_slice( $path, $cycle_start );
						$cycle[] = $neighbor; // Complete the cycle
						$this->cycles[] = $cycle;
					}
				}
			}
		}
		
		array_pop( $path );
		$this->rec_stack[ $node ] = false;
	}

	/**
	 * Get topological sort of the graph
	 *
	 * @return array|false Topologically sorted nodes or false if cycles exist
	 */
	public function topological_sort() {
		if ( ! empty( $this->detect_cycles() ) ) {
			return false; // Cannot sort if cycles exist
		}
		
		$this->topological_order = array();
		$this->visited = array();
		
		foreach ( array_keys( $this->graph ) as $node ) {
			if ( ! isset( $this->visited[ $node ] ) ) {
				$this->dfs_topological( $node );
			}
		}
		
		return array_reverse( $this->topological_order );
	}

	/**
	 * DFS helper for topological sort
	 *
	 * @param string $node Current node
	 */
	private function dfs_topological( $node ) {
		$this->visited[ $node ] = true;
		
		if ( isset( $this->graph[ $node ] ) ) {
			foreach ( $this->graph[ $node ] as $neighbor ) {
				if ( ! isset( $this->visited[ $neighbor ] ) ) {
					$this->dfs_topological( $neighbor );
				}
			}
		}
		
		$this->topological_order[] = $node;
	}

	/**
	 * Get evaluation layers (nodes that can be evaluated in parallel)
	 *
	 * @return array Array of layers, each containing nodes that can be evaluated together
	 */
	public function get_evaluation_layers() {
		$layers = array();
		$in_degree = array();
		$queue = array();
		
		// Calculate in-degrees
		foreach ( $this->graph as $node => $neighbors ) {
			if ( ! isset( $in_degree[ $node ] ) ) {
				$in_degree[ $node ] = 0;
			}
			foreach ( $neighbors as $neighbor ) {
				if ( ! isset( $in_degree[ $neighbor ] ) ) {
					$in_degree[ $neighbor ] = 0;
				}
				$in_degree[ $neighbor ]++;
			}
		}
		
		// Find nodes with no dependencies
		foreach ( $in_degree as $node => $degree ) {
			if ( $degree === 0 ) {
				$queue[] = $node;
			}
		}
		
		// Process layers
		while ( ! empty( $queue ) ) {
			$current_layer = $queue;
			$layers[] = $current_layer;
			$queue = array();
			
			foreach ( $current_layer as $node ) {
				if ( isset( $this->graph[ $node ] ) ) {
					foreach ( $this->graph[ $node ] as $neighbor ) {
						$in_degree[ $neighbor ]--;
						if ( $in_degree[ $neighbor ] === 0 ) {
							$queue[] = $neighbor;
						}
					}
				}
			}
		}
		
		return $layers;
	}

	/**
	 * Get all dependencies for a node
	 *
	 * @param string $node Node to get dependencies for
	 * @return array List of all direct and indirect dependencies
	 */
	public function get_all_dependencies( $node ) {
		$dependencies = array();
		$visited = array();
		
		$this->collect_dependencies( $node, $dependencies, $visited );
		
		return array_unique( $dependencies );
	}

	/**
	 * Recursively collect dependencies
	 *
	 * @param string $node         Current node
	 * @param array  $dependencies Collected dependencies
	 * @param array  $visited      Visited nodes
	 */
	private function collect_dependencies( $node, &$dependencies, &$visited ) {
		if ( isset( $visited[ $node ] ) ) {
			return;
		}
		
		$visited[ $node ] = true;
		
		// In reverse graph, neighbors are dependencies
		foreach ( $this->graph as $dep_node => $neighbors ) {
			if ( in_array( $node, $neighbors, true ) ) {
				$dependencies[] = $dep_node;
				$this->collect_dependencies( $dep_node, $dependencies, $visited );
			}
		}
	}

	/**
	 * Validate graph integrity
	 *
	 * @return array Validation results with warnings and errors
	 */
	public function validate() {
		$results = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
		);
		
		// Check for cycles
		$cycles = $this->detect_cycles();
		if ( ! empty( $cycles ) ) {
			$results['valid'] = false;
			foreach ( $cycles as $cycle ) {
				$results['errors'][] = sprintf(
					__( 'Circular dependency detected: %s', 'woocommerce-product-addons-extra-digital' ),
					implode( ' → ', $cycle )
				);
			}
		}
		
		// Check for isolated nodes
		$all_nodes = array_keys( $this->graph );
		$connected_nodes = array();
		
		foreach ( $this->graph as $node => $neighbors ) {
			$connected_nodes[] = $node;
			$connected_nodes = array_merge( $connected_nodes, $neighbors );
		}
		
		$isolated = array_diff( $all_nodes, array_unique( $connected_nodes ) );
		if ( ! empty( $isolated ) ) {
			$results['warnings'][] = sprintf(
				__( 'Isolated rules detected (no dependencies): %s', 'woocommerce-product-addons-extra-digital' ),
				implode( ', ', $isolated )
			);
		}
		
		// Check for long dependency chains
		$max_chain_length = 5;
		foreach ( $all_nodes as $node ) {
			$chain_length = $this->get_longest_path_from( $node );
			if ( $chain_length > $max_chain_length ) {
				$results['warnings'][] = sprintf(
					__( 'Long dependency chain detected from %s (length: %d)', 'woocommerce-product-addons-extra-digital' ),
					$node,
					$chain_length
				);
			}
		}
		
		return $results;
	}

	/**
	 * Get longest path from a node
	 *
	 * @param string $node Starting node
	 * @return int Length of longest path
	 */
	private function get_longest_path_from( $node ) {
		$memo = array();
		return $this->dfs_longest_path( $node, $memo );
	}

	/**
	 * DFS helper for longest path calculation
	 *
	 * @param string $node Current node
	 * @param array  $memo Memoization array
	 * @return int Longest path length
	 */
	private function dfs_longest_path( $node, &$memo ) {
		if ( isset( $memo[ $node ] ) ) {
			return $memo[ $node ];
		}
		
		$max_length = 0;
		
		if ( isset( $this->graph[ $node ] ) ) {
			foreach ( $this->graph[ $node ] as $neighbor ) {
				$length = 1 + $this->dfs_longest_path( $neighbor, $memo );
				$max_length = max( $max_length, $length );
			}
		}
		
		$memo[ $node ] = $max_length;
		return $max_length;
	}

	/**
	 * Get graph visualization data
	 *
	 * @return array Graph data for visualization
	 */
	public function get_visualization_data() {
		$nodes = array();
		$edges = array();
		
		foreach ( $this->graph as $node => $neighbors ) {
			$nodes[] = array(
				'id'    => $node,
				'label' => $node,
			);
			
			foreach ( $neighbors as $neighbor ) {
				$edges[] = array(
					'from' => $node,
					'to'   => $neighbor,
				);
			}
		}
		
		return array(
			'nodes' => $nodes,
			'edges' => $edges,
		);
	}
}