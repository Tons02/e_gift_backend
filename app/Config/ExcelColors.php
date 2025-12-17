<?php

namespace App\Config;

/**
 * Global color configuration for Excel exports
 * Uses your application's primary and secondary color scheme
 */
class ExcelColors
{
    // Primary Colors (Red theme)
    const PRIMARY_MAIN = 'C41E3A';        // Main red
    const PRIMARY_LIGHT = 'E63946';       // Light red
    const PRIMARY_DARK = '8B0000';        // Dark red
    const PRIMARY_CONTRAST = 'FFFFFF';    // White

    // Secondary Colors (Gold theme)
    const SECONDARY_MAIN = 'F4C430';      // Main gold
    const SECONDARY_LIGHT = 'DAA520';     // Light gold
    const SECONDARY_DARK = '8B6914';      // Dark gold
    const SECONDARY_CONTRAST = '1A0000';  // Dark brown

    // Header styling (using primary colors)
    const HEADER_BG = self::PRIMARY_MAIN;         // Red background
    const HEADER_TEXT = self::PRIMARY_CONTRAST;   // White text

    // Passed date colors (using primary light for warning)
    const PASSED_DATE_BG = 'FFEBEE';              // Light red background
    const PASSED_DATE_TEXT = self::PRIMARY_DARK;  // Dark red text

    // Active/Available status (using secondary colors)
    const STATUS_AVAILABLE_BG = 'FFFACD';         // Light yellow
    const STATUS_AVAILABLE_TEXT = self::SECONDARY_DARK; // Dark gold

    // Redeemed status (using primary colors)
    const STATUS_REDEEMED_BG = 'FFE5E5';          // Very light red
    const STATUS_REDEEMED_TEXT = self::PRIMARY_MAIN; // Red

    // Alternate row colors
    const ROW_EVEN_BG = 'F9F9F9';         // Very light gray
    const ROW_ODD_BG = 'FFFFFF';          // White

    // Border colors
    const BORDER_COLOR = 'E0E0E0';        // Light gray

    // Accent colors (using secondary)
    const ACCENT_BG = self::SECONDARY_LIGHT;      // Gold
    const ACCENT_TEXT = self::SECONDARY_CONTRAST; // Dark brown

    // Neutral colors
    const NEUTRAL_LIGHT = 'F5F5F5';       // Light gray
    const NEUTRAL_DARK = '424242';        // Dark gray
    const NEUTRAL_TEXT = '212121';        // Almost black
}