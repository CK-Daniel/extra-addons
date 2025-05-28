<?php
/**
 * Date Condition Class
 *
 * Handles conditions based on date and time.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date Condition Class
 *
 * @class    WC_Product_Addons_Condition_Date
 * @extends  WC_Product_Addons_Condition
 * @version  4.0.0
 */
class WC_Product_Addons_Condition_Date extends WC_Product_Addons_Condition {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->type = 'date';
		$this->supported_operators = array(
			'equals',
			'not_equals',
			'greater_than',
			'less_than',
			'greater_than_equals',
			'less_than_equals',
			'between',
			'not_between',
			'in',
			'not_in',
		);
	}

	/**
	 * Evaluate the condition
	 *
	 * @param array $condition Condition configuration
	 * @param array $context   Evaluation context
	 * @return bool
	 */
	public function evaluate( $condition, $context ) {
		$value = $this->get_value( $condition, $context );
		$compare_value = isset( $condition['value'] ) ? $condition['value'] : '';
		$operator = isset( $condition['operator'] ) ? $condition['operator'] : 'equals';

		// Special handling for between operator
		if ( in_array( $operator, array( 'between', 'not_between' ) ) ) {
			if ( isset( $condition['start_value'] ) && isset( $condition['end_value'] ) ) {
				$compare_value = array( $condition['start_value'], $condition['end_value'] );
			}
		}

		return $this->compare( $value, $compare_value, $operator );
	}

	/**
	 * Get the value to compare
	 *
	 * @param array $condition Condition configuration
	 * @param array $context   Evaluation context
	 * @return mixed
	 */
	protected function get_value( $condition, $context ) {
		$property = isset( $condition['property'] ) ? $condition['property'] : 'current_date';
		$timezone = isset( $condition['timezone'] ) ? $condition['timezone'] : wp_timezone();

		// Get current time in specified timezone
		$now = new DateTime( 'now', $timezone );

		switch ( $property ) {
			case 'current_date':
				return $now->format( 'Y-m-d' );

			case 'current_time':
				return $now->format( 'H:i:s' );

			case 'current_datetime':
				return $now->format( 'Y-m-d H:i:s' );

			case 'day_of_week':
				return intval( $now->format( 'w' ) ); // 0 = Sunday, 6 = Saturday

			case 'day_of_month':
				return intval( $now->format( 'j' ) );

			case 'month':
				return intval( $now->format( 'n' ) );

			case 'year':
				return intval( $now->format( 'Y' ) );

			case 'hour':
				return intval( $now->format( 'G' ) );

			case 'minute':
				return intval( $now->format( 'i' ) );

			case 'is_weekday':
				$day = intval( $now->format( 'w' ) );
				return $day >= 1 && $day <= 5;

			case 'is_weekend':
				$day = intval( $now->format( 'w' ) );
				return $day === 0 || $day === 6;

			case 'is_business_hours':
				return $this->is_business_hours( $now, $condition );

			case 'is_holiday':
				return $this->is_holiday( $now, $condition );

			case 'days_until':
				if ( isset( $condition['target_date'] ) ) {
					$target = new DateTime( $condition['target_date'], $timezone );
					$interval = $now->diff( $target );
					return $interval->invert ? -$interval->days : $interval->days;
				}
				return null;

			case 'days_since':
				if ( isset( $condition['target_date'] ) ) {
					$target = new DateTime( $condition['target_date'], $timezone );
					$interval = $target->diff( $now );
					return $interval->invert ? -$interval->days : $interval->days;
				}
				return null;

			case 'time_range':
				return $now->format( 'H:i' );

			case 'date_range':
				return $now->format( 'Y-m-d' );

			case 'season':
				return $this->get_season( $now );

			case 'quarter':
				return ceil( intval( $now->format( 'n' ) ) / 3 );

			default:
				return null;
		}
	}

	/**
	 * Check if current time is within business hours
	 *
	 * @param DateTime $now       Current time
	 * @param array    $condition Condition configuration
	 * @return bool
	 */
	private function is_business_hours( $now, $condition ) {
		$start = isset( $condition['business_hours_start'] ) ? $condition['business_hours_start'] : '09:00';
		$end = isset( $condition['business_hours_end'] ) ? $condition['business_hours_end'] : '17:00';

		$current_time = $now->format( 'H:i' );
		return $current_time >= $start && $current_time <= $end;
	}

	/**
	 * Check if current date is a holiday
	 *
	 * @param DateTime $now       Current time
	 * @param array    $condition Condition configuration
	 * @return bool
	 */
	private function is_holiday( $now, $condition ) {
		if ( ! isset( $condition['holidays'] ) || ! is_array( $condition['holidays'] ) ) {
			return false;
		}

		$current_date = $now->format( 'Y-m-d' );
		$current_month_day = $now->format( 'm-d' );

		foreach ( $condition['holidays'] as $holiday ) {
			// Check exact date match
			if ( $holiday === $current_date ) {
				return true;
			}
			// Check recurring holiday (month-day format)
			if ( $holiday === $current_month_day ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get current season
	 *
	 * @param DateTime $now Current time
	 * @return string
	 */
	private function get_season( $now ) {
		$month = intval( $now->format( 'n' ) );

		if ( $month >= 3 && $month <= 5 ) {
			return 'spring';
		} elseif ( $month >= 6 && $month <= 8 ) {
			return 'summer';
		} elseif ( $month >= 9 && $month <= 11 ) {
			return 'fall';
		} else {
			return 'winter';
		}
	}

	/**
	 * Compare values with special date handling
	 *
	 * @param mixed  $value         Value to compare
	 * @param mixed  $compare_value Value to compare against
	 * @param string $operator      Comparison operator
	 * @return bool
	 */
	protected function compare( $value, $compare_value, $operator ) {
		// Handle date/time comparisons
		if ( in_array( $operator, array( 'greater_than', 'less_than', 'greater_than_equals', 'less_than_equals' ) ) ) {
			// Try to parse as dates
			$value_time = strtotime( $value );
			$compare_time = strtotime( $compare_value );

			if ( $value_time !== false && $compare_time !== false ) {
				$value = $value_time;
				$compare_value = $compare_time;
			}
		}

		// Handle day of week comparisons
		if ( is_numeric( $value ) && in_array( $operator, array( 'in', 'not_in' ) ) && ! is_array( $compare_value ) ) {
			// Convert comma-separated string to array
			if ( is_string( $compare_value ) && strpos( $compare_value, ',' ) !== false ) {
				$compare_value = array_map( 'trim', explode( ',', $compare_value ) );
			}
		}

		return parent::compare( $value, $compare_value, $operator );
	}

	/**
	 * Get configuration fields for admin UI
	 *
	 * @return array
	 */
	public function get_config_fields() {
		$fields = array(
			'property' => array(
				'type'    => 'select',
				'label'   => __( 'Property', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'current_date'     => __( 'Current Date', 'woocommerce-product-addons-extra-digital' ),
					'current_time'     => __( 'Current Time', 'woocommerce-product-addons-extra-digital' ),
					'current_datetime' => __( 'Current Date & Time', 'woocommerce-product-addons-extra-digital' ),
					'day_of_week'      => __( 'Day of Week', 'woocommerce-product-addons-extra-digital' ),
					'day_of_month'     => __( 'Day of Month', 'woocommerce-product-addons-extra-digital' ),
					'month'            => __( 'Month', 'woocommerce-product-addons-extra-digital' ),
					'year'             => __( 'Year', 'woocommerce-product-addons-extra-digital' ),
					'hour'             => __( 'Hour', 'woocommerce-product-addons-extra-digital' ),
					'minute'           => __( 'Minute', 'woocommerce-product-addons-extra-digital' ),
					'is_weekday'       => __( 'Is Weekday', 'woocommerce-product-addons-extra-digital' ),
					'is_weekend'       => __( 'Is Weekend', 'woocommerce-product-addons-extra-digital' ),
					'is_business_hours'=> __( 'Is Business Hours', 'woocommerce-product-addons-extra-digital' ),
					'is_holiday'       => __( 'Is Holiday', 'woocommerce-product-addons-extra-digital' ),
					'days_until'       => __( 'Days Until Date', 'woocommerce-product-addons-extra-digital' ),
					'days_since'       => __( 'Days Since Date', 'woocommerce-product-addons-extra-digital' ),
					'time_range'       => __( 'Time Range', 'woocommerce-product-addons-extra-digital' ),
					'date_range'       => __( 'Date Range', 'woocommerce-product-addons-extra-digital' ),
					'season'           => __( 'Season', 'woocommerce-product-addons-extra-digital' ),
					'quarter'          => __( 'Quarter', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'operator' => array(
				'type'    => 'select',
				'label'   => __( 'Operator', 'woocommerce-product-addons-extra-digital' ),
				'options' => $this->get_operator_options(),
			),
			'timezone' => array(
				'type'    => 'select',
				'label'   => __( 'Timezone', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'store' => __( 'Store Timezone', 'woocommerce-product-addons-extra-digital' ),
					'user'  => __( 'User Timezone', 'woocommerce-product-addons-extra-digital' ),
				),
				'default' => 'store',
			),
		);

		// Add value field with dynamic configuration
		$fields['value'] = array(
			'type'        => 'dynamic',
			'label'       => __( 'Value', 'woocommerce-product-addons-extra-digital' ),
			'placeholder' => __( 'Enter value to compare', 'woocommerce-product-addons-extra-digital' ),
			'config'      => array(
				'current_date' => array(
					'type' => 'date',
				),
				'current_time' => array(
					'type' => 'time',
				),
				'current_datetime' => array(
					'type' => 'datetime-local',
				),
				'day_of_week' => array(
					'type'    => 'multiselect',
					'options' => array(
						'0' => __( 'Sunday', 'woocommerce-product-addons-extra-digital' ),
						'1' => __( 'Monday', 'woocommerce-product-addons-extra-digital' ),
						'2' => __( 'Tuesday', 'woocommerce-product-addons-extra-digital' ),
						'3' => __( 'Wednesday', 'woocommerce-product-addons-extra-digital' ),
						'4' => __( 'Thursday', 'woocommerce-product-addons-extra-digital' ),
						'5' => __( 'Friday', 'woocommerce-product-addons-extra-digital' ),
						'6' => __( 'Saturday', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'month' => array(
					'type'    => 'multiselect',
					'options' => array(
						'1'  => __( 'January', 'woocommerce-product-addons-extra-digital' ),
						'2'  => __( 'February', 'woocommerce-product-addons-extra-digital' ),
						'3'  => __( 'March', 'woocommerce-product-addons-extra-digital' ),
						'4'  => __( 'April', 'woocommerce-product-addons-extra-digital' ),
						'5'  => __( 'May', 'woocommerce-product-addons-extra-digital' ),
						'6'  => __( 'June', 'woocommerce-product-addons-extra-digital' ),
						'7'  => __( 'July', 'woocommerce-product-addons-extra-digital' ),
						'8'  => __( 'August', 'woocommerce-product-addons-extra-digital' ),
						'9'  => __( 'September', 'woocommerce-product-addons-extra-digital' ),
						'10' => __( 'October', 'woocommerce-product-addons-extra-digital' ),
						'11' => __( 'November', 'woocommerce-product-addons-extra-digital' ),
						'12' => __( 'December', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'is_weekday' => array(
					'type'    => 'select',
					'options' => array(
						'1' => __( 'Yes', 'woocommerce-product-addons-extra-digital' ),
						'0' => __( 'No', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'is_weekend' => array(
					'type'    => 'select',
					'options' => array(
						'1' => __( 'Yes', 'woocommerce-product-addons-extra-digital' ),
						'0' => __( 'No', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'season' => array(
					'type'    => 'multiselect',
					'options' => array(
						'spring' => __( 'Spring', 'woocommerce-product-addons-extra-digital' ),
						'summer' => __( 'Summer', 'woocommerce-product-addons-extra-digital' ),
						'fall'   => __( 'Fall', 'woocommerce-product-addons-extra-digital' ),
						'winter' => __( 'Winter', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'quarter' => array(
					'type'    => 'multiselect',
					'options' => array(
						'1' => __( 'Q1 (Jan-Mar)', 'woocommerce-product-addons-extra-digital' ),
						'2' => __( 'Q2 (Apr-Jun)', 'woocommerce-product-addons-extra-digital' ),
						'3' => __( 'Q3 (Jul-Sep)', 'woocommerce-product-addons-extra-digital' ),
						'4' => __( 'Q4 (Oct-Dec)', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'default' => array(
					'type' => 'text',
				),
			),
		);

		// Additional fields for specific properties
		$fields['target_date'] = array(
			'type'        => 'date',
			'label'       => __( 'Target Date', 'woocommerce-product-addons-extra-digital' ),
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'days_until', 'days_since' ),
				'action' => 'show',
			),
		);

		$fields['business_hours_start'] = array(
			'type'        => 'time',
			'label'       => __( 'Business Hours Start', 'woocommerce-product-addons-extra-digital' ),
			'default'     => '09:00',
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'is_business_hours' ),
				'action' => 'show',
			),
		);

		$fields['business_hours_end'] = array(
			'type'        => 'time',
			'label'       => __( 'Business Hours End', 'woocommerce-product-addons-extra-digital' ),
			'default'     => '17:00',
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'is_business_hours' ),
				'action' => 'show',
			),
		);

		$fields['holidays'] = array(
			'type'        => 'textarea',
			'label'       => __( 'Holiday Dates', 'woocommerce-product-addons-extra-digital' ),
			'placeholder' => __( 'Enter dates (YYYY-MM-DD) or recurring dates (MM-DD), one per line', 'woocommerce-product-addons-extra-digital' ),
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'is_holiday' ),
				'action' => 'show',
			),
		);

		// Add fields for between operator
		$fields['start_value'] = array(
			'type'        => 'text',
			'label'       => __( 'Start Value', 'woocommerce-product-addons-extra-digital' ),
			'dependency'  => array(
				'field'  => 'operator',
				'values' => array( 'between', 'not_between' ),
				'action' => 'show',
			),
		);

		$fields['end_value'] = array(
			'type'        => 'text',
			'label'       => __( 'End Value', 'woocommerce-product-addons-extra-digital' ),
			'dependency'  => array(
				'field'  => 'operator',
				'values' => array( 'between', 'not_between' ),
				'action' => 'show',
			),
		);

		return $fields;
	}

	/**
	 * Get display label for the condition
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Date & Time', 'woocommerce-product-addons-extra-digital' );
	}

	/**
	 * Validate condition configuration
	 *
	 * @param array $condition Condition configuration
	 * @return bool
	 */
	protected function validate_specific( $condition ) {
		// Must have property
		if ( ! isset( $condition['property'] ) ) {
			return false;
		}

		// Validate specific properties
		$property = $condition['property'];

		if ( in_array( $property, array( 'days_until', 'days_since' ) ) && ! isset( $condition['target_date'] ) ) {
			return false;
		}

		// Validate between operator
		if ( isset( $condition['operator'] ) && in_array( $condition['operator'], array( 'between', 'not_between' ) ) ) {
			if ( ! isset( $condition['start_value'] ) || ! isset( $condition['end_value'] ) ) {
				return false;
			}
		}

		return true;
	}
}