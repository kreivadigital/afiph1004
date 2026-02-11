<?php
/**
 * Clase de Logger del Plugin
 *
 * Sistema de logging con soporte para base de datos y archivos.
 * Formato: [fecha] [tipo factura] usuario::accion=>response/descripcion
 *
 * @package ServicesAPIHotel
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAH_Logger {

    // Log levels
    const ERROR = 'ERROR';
    const INFO = 'INFO';
    const WARNING = 'WARNING';

    // Invoice types
    const FACTURA_B = 'FB';
    const FACTURA_T = 'FT';
    const SYSTEM = 'SYS';

    /**
     * Log directory path
     * @var string
     */
    private static $log_dir;

    /**
     * Current log file path
     * @var string
     */
    private static $log_file;

    /**
     * Initialize logger paths
     */
    public static function init() {
        self::$log_dir = SAH_PLUGIN_DIR . 'log/';
        self::$log_file = self::$log_dir . 'debug_' . date('Y-m-d') . '.log';
        self::ensure_log_directory();
    }

    /**
     * Ensure log directory exists with proper security
     */
    private static function ensure_log_directory() {
        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
            // Create .htaccess to prevent direct access
            file_put_contents(self::$log_dir . '.htaccess', "Order deny,allow\nDeny from all");
            // Create index.php for extra security
            file_put_contents(self::$log_dir . 'index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Log to database (concise action logs)
     *
     * @param string $level Log level (ERROR, INFO, WARNING)
     * @param string $invoice_type Type of invoice (FB, FT, SYS)
     * @param string $action Action performed
     * @param string $response AFIP response or result
     * @param array $extra_data Additional data (transaction_id, amount, etc.)
     */
    public static function db($level, $invoice_type, $action, $response = '', $extra_data = array()) {
        global $wpdb;

        $current_user = wp_get_current_user();
        $username = $current_user->user_login ?? 'system';

        $table_name = $wpdb->prefix . 'hotels_logs';

        $wpdb->insert(
            $table_name,
            array(
                'log_date' => current_time('mysql'),
                'log_level' => $level,
                'invoice_type' => $invoice_type,
                'username' => $username,
                'action' => $action,
                'response' => $response,
                'transaction_id' => isset($extra_data['transaction_id']) ? $extra_data['transaction_id'] : null,
                'amount' => isset($extra_data['amount']) ? $extra_data['amount'] : null,
                'cae' => isset($extra_data['cae']) ? $extra_data['cae'] : null,
                'error_code' => isset($extra_data['error_code']) ? $extra_data['error_code'] : null,
                'ip_address' => self::get_client_ip(),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Log to file (detailed debug logs)
     *
     * @param string $level Log level (ERROR, INFO, WARNING)
     * @param string $invoice_type Type of invoice (FB, FT, SYS)
     * @param string $action Action performed
     * @param mixed $input_data Input data (will be JSON encoded)
     * @param mixed $output_data Output data (will be JSON encoded)
     * @param string $description Additional description
     * @param string $api_response Raw API/XML response (optional)
     * @param string $api_request Raw API/XML request (optional)
     */
    public static function file($level, $invoice_type, $action, $input_data = null, $output_data = null, $description = '', $api_response = null, $api_request = null) {
        self::init();

        $current_user = wp_get_current_user();
        $username = $current_user->user_login ?? 'system';

        // Format: [fecha] [tipo factura] usuario::accion=>response/descripcion
        $log_entry = sprintf(
            "[%s] [%s] [%s] %s::%s=>%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $invoice_type,
            $username,
            $action,
            $description
        );

        // Add detailed data for debug
        if ($input_data !== null) {
            $log_entry .= "  INPUT: " . json_encode($input_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        if ($output_data !== null) {
            $log_entry .= "  OUTPUT: " . json_encode($output_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        if ($api_request !== null) {
            // Formatear XML si es posible
            $formatted_request = self::formatXml($api_request);
            $log_entry .= "  REQUEST_API:\n" . $formatted_request . "\n";
        }
        if ($api_response !== null) {
            // Formatear XML si es posible
            $formatted_response = self::formatXml($api_response);
            $log_entry .= "  RESPONSE_API:\n" . $formatted_response . "\n";
        }
        $log_entry .= "  ---\n";

        // Intentar escribir, ignorar si falla (permisos)
        @file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Format XML string for readable output
     *
     * @param string $xml Raw XML string
     * @return string Formatted XML with indentation
     */
    private static function formatXml($xml) {
        if (empty($xml)) {
            return '  (empty)';
        }

        // Intentar formatear como XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Suprimir errores de XML malformado
        if (@$dom->loadXML($xml)) {
            $formatted = $dom->saveXML();
            // Indentar cada línea con espacios
            $lines = explode("\n", $formatted);
            $indented = array_map(function($line) {
                return '    ' . $line;
            }, $lines);
            return implode("\n", $indented);
        }

        // Si no es XML válido, devolver como string con indentación
        return '    ' . str_replace("\n", "\n    ", $xml);
    }

    /**
     * Combined logging (both DB and file)
     *
     * @param string $level Log level
     * @param string $invoice_type Invoice type
     * @param string $action Action name
     * @param string $response Response description
     * @param array $extra_data Extra data for DB
     * @param mixed $input_data Input data for file log
     * @param mixed $output_data Output data for file log
     * @param string $api_response Raw API/XML response for file log
     * @param string $api_request Raw API/XML request for file log
     */
    public static function log($level, $invoice_type, $action, $response, $extra_data = array(), $input_data = null, $output_data = null, $api_response = null, $api_request = null) {
        // Log to database (concise)
        self::db($level, $invoice_type, $action, $response, $extra_data);

        // Log to file (detailed)
        self::file($level, $invoice_type, $action, $input_data, $output_data, $response, $api_response, $api_request);
    }

    /**
     * Log INFO level to database
     */
    public static function info($invoice_type, $action, $response = '', $extra_data = array()) {
        self::db(self::INFO, $invoice_type, $action, $response, $extra_data);
    }

    /**
     * Log ERROR level to database
     */
    public static function error($invoice_type, $action, $response = '', $extra_data = array()) {
        self::db(self::ERROR, $invoice_type, $action, $response, $extra_data);
    }

    /**
     * Log WARNING level to database
     */
    public static function warning($invoice_type, $action, $response = '', $extra_data = array()) {
        self::db(self::WARNING, $invoice_type, $action, $response, $extra_data);
    }

    /**
     * Log Factura B operation (combined DB + file)
     */
    public static function facturaB($level, $action, $response, $extra_data = array(), $input = null, $output = null, $api_response = null, $api_request = null) {
        self::log($level, self::FACTURA_B, $action, $response, $extra_data, $input, $output, $api_response, $api_request);
    }

    /**
     * Log Factura T operation (combined DB + file)
     */
    public static function facturaT($level, $action, $response, $extra_data = array(), $input = null, $output = null, $api_response = null, $api_request = null) {
        self::log($level, self::FACTURA_T, $action, $response, $extra_data, $input, $output, $api_response, $api_request);
    }

    /**
     * Log System operation (combined DB + file)
     */
    public static function system($level, $action, $response, $extra_data = array(), $input = null, $output = null, $api_response = null, $api_request = null) {
        self::log($level, self::SYSTEM, $action, $response, $extra_data, $input, $output, $api_response, $api_request);
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                return sanitize_text_field($_SERVER[$key]);
            }
        }
        return 'unknown';
    }

    /**
     * Get logs from database with pagination and filters
     *
     * @param array $args Query arguments
     * @return array Array with logs, total count and pages
     */
    public static function get_db_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'per_page' => 25,
            'page' => 1,
            'level' => '',
            'invoice_type' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
        );

        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . 'hotels_logs';
        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = array('1=1');
        $prepare_values = array();

        if (!empty($args['level'])) {
            $where[] = 'log_level = %s';
            $prepare_values[] = $args['level'];
        }

        if (!empty($args['invoice_type'])) {
            $where[] = 'invoice_type = %s';
            $prepare_values[] = $args['invoice_type'];
        }

        if (!empty($args['date_from'])) {
            $where[] = 'DATE(log_date) >= %s';
            $prepare_values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where[] = 'DATE(log_date) <= %s';
            $prepare_values[] = $args['date_to'];
        }

        if (!empty($args['search'])) {
            $where[] = '(action LIKE %s OR response LIKE %s OR transaction_id LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        // Get total count
        if (!empty($prepare_values)) {
            $count_sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}",
                $prepare_values
            );
        } else {
            $count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        }
        $total = $wpdb->get_var($count_sql);

        // Get logs with pagination
        if (!empty($prepare_values)) {
            // Con filtros: usar prepare con todos los valores
            $prepare_values[] = $args['per_page'];
            $prepare_values[] = $offset;
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY log_date DESC LIMIT %d OFFSET %d",
                $prepare_values
            );
        } else {
            // Sin filtros: usar prepare solo para LIMIT/OFFSET
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE 1=1 ORDER BY log_date DESC LIMIT %d OFFSET %d",
                $args['per_page'],
                $offset
            );
        }
        $logs = $wpdb->get_results($sql);

        return array(
            'logs' => $logs,
            'total' => (int) $total,
            'pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Get file logs content
     *
     * @param string|null $date Date in Y-m-d format, null for today
     * @param int $lines Number of lines to read from end
     * @return array Log data
     */
    public static function get_file_logs($date = null, $lines = 500) {
        self::init();

        $file = $date
            ? self::$log_dir . 'debug_' . $date . '.log'
            : self::$log_file;

        if (!file_exists($file)) {
            return array('content' => '', 'exists' => false, 'file' => '', 'size' => 0);
        }

        // Read last N lines efficiently
        $content = self::tail($file, $lines);

        return array(
            'content' => $content,
            'exists' => true,
            'file' => basename($file),
            'size' => filesize($file),
        );
    }

    /**
     * Get list of available log files
     *
     * @return array List of log files with metadata
     */
    public static function get_available_log_files() {
        self::init();

        $files = glob(self::$log_dir . 'debug_*.log');
        $result = array();

        if ($files) {
            foreach ($files as $file) {
                $result[] = array(
                    'name' => basename($file),
                    'date' => str_replace(array('debug_', '.log'), '', basename($file)),
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                );
            }
        }

        // Sort by date descending
        usort($result, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return $result;
    }

    /**
     * Read last N lines from a file efficiently
     *
     * @param string $file File path
     * @param int $lines Number of lines
     * @return string File content
     */
    private static function tail($file, $lines = 500) {
        $f = @fopen($file, 'rb');
        if (!$f) {
            return '';
        }

        $buffer = 8192;
        $output = '';

        fseek($f, 0, SEEK_END);
        $pos = ftell($f);

        while ($pos > 0 && substr_count($output, "\n") < $lines) {
            $read = min($buffer, $pos);
            $pos -= $read;
            fseek($f, $pos, SEEK_SET);
            $chunk = fread($f, $read);
            $output = $chunk . $output;
        }

        fclose($f);

        $lines_array = explode("\n", $output);
        return implode("\n", array_slice($lines_array, -$lines));
    }

    /**
     * Clean up old logs
     *
     * @param int $days Days to keep logs
     */
    public static function cleanup($days = 30) {
        global $wpdb;

        // Clean database logs
        $table_name = $wpdb->prefix . 'hotels_logs';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE log_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Clean file logs
        self::init();
        $threshold = strtotime("-{$days} days");
        $files = glob(self::$log_dir . 'debug_*.log');

        if ($files) {
            foreach ($files as $file) {
                if (filemtime($file) < $threshold) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Get log directory path
     *
     * @return string
     */
    public static function get_log_dir() {
        self::init();
        return self::$log_dir;
    }
}
