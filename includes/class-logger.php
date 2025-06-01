<?php
namespace UniversalSystemeSync;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    
    const LOG_OPTION = 'universal_systeme_logs';
    const MAX_LOGS = 100;
    
    /**
     * Log a message
     */
    public function log($message) {
        $logs = get_option(self::LOG_OPTION, array());
        
        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'message' => $message
        );
        
        // Keep only last MAX_LOGS entries
        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, -self::MAX_LOGS);
        }
        
        update_option(self::LOG_OPTION, $logs);
    }
    
    /**
     * Get all logs
     */
    public function get_logs() {
        return get_option(self::LOG_OPTION, array());
    }
    
    /**
     * Clear all logs
     */
    public function clear_logs() {
        delete_option(self::LOG_OPTION);
    }
    
    /**
     * Display logs in HTML format
     */
    public function display_logs() {
        $logs = $this->get_logs();
        
        if (empty($logs)) {
            echo '<p>No synchronization logs yet.</p>';
            return;
        }
        
        echo '<div style="background: #f9f9f9; padding: 10px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd;">';
        
        // Display in reverse order (newest first)
        foreach (array_reverse($logs) as $log) {
            $class = '';
            if (strpos($log['message'], 'Error:') === 0) {
                $class = 'color: #d63638;';
            } elseif (strpos($log['message'], 'Success:') === 0) {
                $class = 'color: #00a32a;';
            } elseif (strpos($log['message'], 'Warning:') === 0) {
                $class = 'color: #dba617;';
            } elseif (strpos($log['message'], 'Debug:') === 0) {
                $class = 'color: #72777c;';
            }
            
            echo '<div style="margin-bottom: 5px; ' . $class . '">';
            echo '<strong>' . esc_html($log['timestamp']) . '</strong>: ' . esc_html($log['message']);
            echo '</div>';
        }
        
        echo '</div>';
    }
}