<?php
/**
 * Text Helper Functions
 * Centralized text processing and sanitization
 */

/**
 * Clean text for display - removes unwanted escaping and formats properly
 * Only removes escaping that was added by PHP/WordPress, not intentional backslashes
 */
function sud_clean_text($text) {
    if (empty($text)) {
        return $text;
    }
    
    // Only remove backslashes that are escaping quotes, not all backslashes
    // This preserves intentional backslashes like "I said \"hello\" and she said \"hi\""
    $text = str_replace(['\\"', "\\'"], ['"', "'"], $text);
    
    // Normalize smart quotes to regular quotes
    $text = str_replace([
        "\u{201C}", "\u{201D}", // Left and right double quotes
        "\u{2018}", "\u{2019}"  // Left and right single quotes
    ], ['"', '"', "'", "'"], $text);
    
    // Remove HTML entities but preserve intentional backslashes
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
    // Trim whitespace
    $text = trim($text);
    
    return $text;
}

/**
 * Format notification message with proper text cleaning
 */
function sud_format_notification_message($message) {
    return sud_clean_text($message);
}

/**
 * Format email content with proper text cleaning
 */
function sud_format_email_content($content) {
    return sud_clean_text($content);
}

/**
 * Format display text for UI with proper escaping for output
 */
function sud_format_display_text($text) {
    // Clean the text first
    $cleaned = sud_clean_text($text);
    
    // Then escape for HTML output
    return esc_html($cleaned);
}

/**
 * Format reason text for notifications and emails
 */
function sud_format_reason_text($reason) {
    if (empty($reason)) {
        return '';
    }
    
    $cleaned = sud_clean_text($reason);
    
    // Add quotes around the reason if not already present
    if (substr($cleaned, 0, 1) !== '"' && substr($cleaned, -1) !== '"') {
        return '"' . $cleaned . '"';
    }
    
    return $cleaned;
}