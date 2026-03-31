<?php
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";
/**
 * Currency helper functions - Zambian Kwacha (ZMW) as default
 */

/**
 * Format currency with proper symbols and formatting
 * @param float $amount The amount to format
 * @param string $currency Currency code (default: ZMW)
 * @return string Formatted currency string
 */
function format_currency($amount, $currency = 'ZMW') {
    $amount = floatval($amount);
    
    $currency_symbols = [
        'ZMW' => 'K',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'KES' => 'KSh ',
        'INR' => '₹',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'JPY' => '¥',
        'CNY' => '¥',
        'ZAR' => 'R',
        'NGN' => '₦',
        'GHS' => 'GH₵'
    ];
    
    $symbol = $currency_symbols[$currency] ?? 'K';
    
    // Format number with thousands separator and 2 decimal places
    $formatted_amount = number_format($amount, 2, '.', ',');
    
    // For Zambian Kwacha, symbol comes before the amount: K1,000.00
    return $symbol . $formatted_amount;
}

/**
 * Format currency without symbol (just number formatting)
 * @param float $amount The amount to format
 * @return string Formatted amount string
 */
function format_amount($amount) {
    $amount = floatval($amount);
    return number_format($amount, 2, '.', ',');
}

/**
 * Convert amount to different currency (basic conversion rates)
 * @param float $amount The amount to convert
 * @param string $from_currency Original currency
 * @param string $to_currency Target currency
 * @return float Converted amount
 */
function convert_currency($amount, $from_currency, $to_currency) {
    // Conversion rates (approximate values - you should use real-time API for accurate rates)
    $conversion_rates = [
        'ZMW' => 1.0,      // Zambian Kwacha (base)
        'USD' => 0.038,    // 1 ZMW = 0.038 USD
        'EUR' => 0.035,    // 1 ZMW = 0.035 EUR
        'GBP' => 0.030,    // 1 ZMW = 0.030 GBP
        'KES' => 4.50,     // 1 ZMW = 4.50 KES
        'INR' => 3.15,     // 1 ZMW = 3.15 INR
        'CAD' => 0.051,    // 1 ZMW = 0.051 CAD
        'AUD' => 0.058,    // 1 ZMW = 0.058 AUD
        'ZAR' => 0.70,     // 1 ZMW = 0.70 ZAR
        'NGN' => 35.0,     // 1 ZMW = 35.0 NGN
        'GHS' => 0.45      // 1 ZMW = 0.45 GHS
    ];
    
    // Convert to base currency (ZMW) first, then to target currency
    $amount_base = $amount / ($conversion_rates[$from_currency] ?? 1.0);
    return $amount_base * ($conversion_rates[$to_currency] ?? 1.0);
}

/**
 * Get currency symbol for a given currency code
 * @param string $currency Currency code (default: ZMW)
 * @return string Currency symbol
 */
function get_currency_symbol($currency = 'ZMW') {
    $symbols = [
        'ZMW' => 'K',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'KES' => 'KSh ',
        'INR' => '₹',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'JPY' => '¥',
        'CNY' => '¥',
        'ZAR' => 'R',
        'NGN' => '₦',
        'GHS' => 'GH₵'
    ];
    
    return $symbols[$currency] ?? 'K';
}

/**
 * Get currency name for a given currency code
 * @param string $currency Currency code (default: ZMW)
 * @return string Currency name
 */
function get_currency_name($currency = 'ZMW') {
    $names = [
        'ZMW' => 'Zambian Kwacha',
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'KES' => 'Kenyan Shilling',
        'INR' => 'Indian Rupee',
        'CAD' => 'Canadian Dollar',
        'AUD' => 'Australian Dollar',
        'JPY' => 'Japanese Yen',
        'CNY' => 'Chinese Yuan',
        'ZAR' => 'South African Rand',
        'NGN' => 'Nigerian Naira',
        'GHS' => 'Ghanaian Cedi'
    ];
    
    return $names[$currency] ?? 'Zambian Kwacha';
}

/**
 * Validate if amount is a valid monetary value
 * @param mixed $amount The amount to validate
 * @return bool True if valid, false otherwise
 */
function is_valid_amount($amount) {
    if (!is_numeric($amount)) {
        return false;
    }
    
    $amount = floatval($amount);
    return $amount >= 0 && $amount <= 10000000; // Reasonable range for hotel bookings
}

// /**
//  * Calculate tax amount (Zambian VAT rate is 16%)
//  * @param float $amount The base amount
//  * @param float $tax_rate Tax rate as percentage (default: 16% for Zambia)
//  * @return float Tax amount
//  */
// function calculate_tax($amount, $tax_rate = 16) {
//     $amount = floatval($amount);
//     $tax_rate = floatval($tax_rate);
    
//     return ($amount * $tax_rate) / 100;
// }

// /**
//  * Calculate total amount including tax (Zambian VAT)
//  * @param float $amount The base amount
//  * @param float $tax_rate Tax rate as percentage (default: 16% for Zambia)
//  * @return float Total amount including tax
//  */
// function calculate_total_with_tax($amount, $tax_rate = 16) {
//     $amount = floatval($amount);
//     $tax = calculate_tax($amount, $tax_rate);
    
//     return $amount + $tax;
// }

/**
 * Format percentage
 * @param float $percentage The percentage value
 * @return string Formatted percentage
 */
function format_percentage($percentage) {
    $percentage = floatval($percentage);
    return number_format($percentage, 1) . '%';
}

/**
 * Format Zambian Kwacha specifically (convenience function)
 * @param float $amount The amount to format
 * @return string Formatted Zambian Kwacha
 */
function format_zmw($amount) {
    return format_currency($amount, 'ZMW');
}

/**
 * Format room price for display (Zambian Kwacha by default)
 * @param float $price The room price
 * @param string $currency Currency code
 * @return string Formatted price per night
 */
function format_room_price($price, $currency = 'ZMW') {
    return format_currency($price, $currency) . '/night';
}

/**
 * Calculate booking total including tax
 * @param float $price_per_night Room price per night
 * @param int $nights Number of nights
 * @param float $tax_rate Tax rate percentage
 * @return array Array with subtotal, tax, and total
 */
// function calculate_booking_total($price_per_night, $nights, $tax_rate = 16) {
//     $subtotal = $price_per_night * $nights;
//     $tax = calculate_tax($subtotal, $tax_rate);
//     $total = $subtotal + $tax;
    
//     return [
//         'subtotal' => $subtotal,
//         'tax' => $tax,
//         'total' => $total,
//         'nights' => $nights,
//         'price_per_night' => $price_per_night
//     ];
// }

/**
 * Get all supported currencies
 * @return array Array of supported currency codes and names
 */
function get_supported_currencies() {
    return [
        'ZMW' => 'Zambian Kwacha (K)',
        'USD' => 'US Dollar ($)',
        'EUR' => 'Euro (€)',
        'GBP' => 'British Pound (£)',
        'KES' => 'Kenyan Shilling (KSh)',
        'ZAR' => 'South African Rand (R)'
    ];
}
?>