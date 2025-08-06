<?php
/**
 * Site Name and Branding Helper Functions
 */

defined('ABSPATH') or die('Cannot access this file directly.');

/**
 * Get site name with proper fallback
 * 
 * @return string Site name
 */
function sud_get_site_name() {
    return defined('SUD_SITE_NAME') ? SUD_SITE_NAME : 'Swipe Up Daddy';
}

/**
 * Format page title with site name
 * 
 * @param string $page_title Page title without site name
 * @param string $separator Separator between page title and site name (default: " - ")
 * @return string Formatted page title with site name
 */
function sud_get_formatted_page_title($page_title, $separator = ' - ') {
    return esc_html($page_title . $separator . sud_get_site_name());
}

/**
 * Admin utility to replace old site name with new site name across files
 * Only available to administrators
 *
 * @param string $directory Directory to scan
 * @param string $old_name Old site name to replace
 * @param bool $dry_run Whether to actually make changes or just report them
 * @return array Results of the operation
 */
function sud_admin_replace_site_name($directory = null, $old_name = 'Loyalty Meets Royalty', $dry_run = true) {
    if (!current_user_can('administrator')) {
        return ['error' => 'Unauthorized access'];
    }
    
    if (empty($directory)) {
        $directory = dirname(__FILE__, 2);
    }
    
    $new_name = sud_get_site_name();
    $results = [
        'files_checked' => 0,
        'files_modified' => 0,
        'replacements' => 0,
        'modified_files' => [],
        'errors' => []
    ];
    
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'html', 'css', 'js'])) {
                $results['files_checked']++;
                $path = $file->getPathname();
                
                try {
                    $content = file_get_contents($path);
                    $new_content = str_replace($old_name, $new_name, $content, $count);
                    
                    if ($count > 0) {
                        $results['replacements'] += $count;
                        
                        if (!$dry_run) {
                            file_put_contents($path, $new_content);
                        }
                        
                        $results['files_modified']++;
                        $results['modified_files'][] = str_replace($directory, '', $path) . " ({$count} replacements)";
                    }
                } catch (Exception $e) {
                    $results['errors'][] = "Error processing {$path}: " . $e->getMessage();
                }
            }
        }
    } catch (Exception $e) {
        $results['errors'][] = "Directory scan error: " . $e->getMessage();
    }
    
    return $results;
} 