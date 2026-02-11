<?php
/**
 * Pagina de logs de desarrollo (Archivos)
 *
 * Muestra los logs detallados para debugging almacenados en archivos.
 *
 * @package ServicesAPIHotel
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function logs_dev() {
    $selected_date = isset($_GET['log_date']) ? sanitize_text_field($_GET['log_date']) : null;
    $available_files = SAH_Logger::get_available_log_files();

    // If no date selected and files exist, select the most recent one
    if (!$selected_date && !empty($available_files)) {
        $selected_date = $available_files[0]['date'];
    }

    $log_data = SAH_Logger::get_file_logs($selected_date);

    // Handle download action
    if (isset($_GET['action']) && $_GET['action'] === 'download' && $selected_date) {
        $file_path = SAH_Logger::get_log_dir() . 'debug_' . $selected_date . '.log';
        if (file_exists($file_path)) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="debug_' . $selected_date . '.log"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        }
    }
?>
    <style>
        .logs-dev-container {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 20px;
            max-width: 1600px;
        }
        .log-files-sidebar {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .log-files-sidebar h3 {
            margin: 0;
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            background: #f9f9f9;
        }
        .log-files-list {
            max-height: 500px;
            overflow-y: auto;
        }
        .log-file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none;
            color: #1d2327;
            transition: background 0.2s;
        }
        .log-file-item:hover {
            background: #f0f6fc;
        }
        .log-file-item.active {
            background: #0073aa;
            color: #fff;
        }
        .log-file-item.active:hover {
            background: #006799;
        }
        .log-file-date {
            font-weight: 500;
        }
        .log-file-size {
            font-size: 11px;
            color: #666;
        }
        .log-file-item.active .log-file-size {
            color: rgba(255,255,255,0.8);
        }

        .log-content-wrapper {
            min-width: 0;
        }
        .log-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .log-info-text {
            font-size: 13px;
            color: #666;
        }
        .log-info-text strong {
            color: #1d2327;
        }
        .log-toolbar-actions {
            display: flex;
            gap: 8px;
        }

        .log-content-area {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 5px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.7;
            max-height: 650px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .log-content-area::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        .log-content-area::-webkit-scrollbar-track {
            background: #2d2d2d;
        }
        .log-content-area::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 5px;
        }
        .log-content-area::-webkit-scrollbar-thumb:hover {
            background: #666;
        }

        /* Syntax highlighting */
        .log-content-area .hl-date { color: #9e9e9e; }
        .log-content-area .hl-level-error { color: #f44336; font-weight: bold; }
        .log-content-area .hl-level-warning { color: #ffb300; font-weight: bold; }
        .log-content-area .hl-level-info { color: #4fc3f7; font-weight: bold; }
        .log-content-area .hl-type { color: #ce93d8; }
        .log-content-area .hl-user { color: #81c784; }
        .log-content-area .hl-action { color: #fff; }
        .log-content-area .hl-separator { color: #888; }
        .log-content-area .hl-key { color: #9cdcfe; }
        .log-content-area .hl-string { color: #ce9178; }
        .log-content-area .hl-number { color: #b5cea8; }

        .no-logs {
            text-align: center;
            padding: 80px 40px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            color: #666;
        }
        .no-logs h3 {
            margin: 0 0 10px 0;
            color: #1d2327;
        }
        .logs-back-link {
            margin-bottom: 15px;
            display: block;
        }
    </style>

    <div class="wrap">
        <h1>Logs de Desarrollo</h1>
        <p>Logs detallados con datos de entrada/salida para debugging.</p>

        <a href="<?php echo esc_url(admin_url('admin.php?page=logs_list')); ?>" class="logs-back-link">
            &larr; Volver a Logs de Facturacion
        </a>

        <div class="logs-dev-container">
            <!-- Sidebar with file list -->
            <div class="log-files-sidebar">
                <h3>Archivos de Log</h3>
                <div class="log-files-list">
                    <?php if (empty($available_files)): ?>
                        <div style="padding: 20px; text-align: center; color: #666;">
                            No hay archivos de log disponibles.
                        </div>
                    <?php else: ?>
                        <?php foreach ($available_files as $file): ?>
                            <a href="<?php echo esc_url(add_query_arg('log_date', $file['date'], admin_url('admin.php?page=logs_dev'))); ?>"
                               class="log-file-item <?php echo $selected_date === $file['date'] ? 'active' : ''; ?>">
                                <span class="log-file-date"><?php echo esc_html($file['date']); ?></span>
                                <span class="log-file-size"><?php echo size_format($file['size']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Log content area -->
            <div class="log-content-wrapper">
                <?php if ($log_data['exists']): ?>
                    <div class="log-toolbar">
                        <span class="log-info-text">
                            Archivo: <strong><?php echo esc_html($log_data['file']); ?></strong>
                            &nbsp;|&nbsp; Tamano: <strong><?php echo size_format($log_data['size']); ?></strong>
                        </span>
                        <div class="log-toolbar-actions">
                            <a href="<?php echo esc_url(add_query_arg(array('action' => 'download', 'log_date' => $selected_date), admin_url('admin.php?page=logs_dev'))); ?>"
                               class="button button-secondary">
                                Descargar
                            </a>
                            <button type="button" class="button" onclick="location.reload();">
                                Actualizar
                            </button>
                        </div>
                    </div>
                    <div class="log-content-area" id="log-content"><?php
                        echo logs_dev_highlight_content($log_data['content']);
                    ?></div>
                <?php else: ?>
                    <div class="no-logs">
                        <h3>Sin contenido</h3>
                        <p>Selecciona un archivo de log del panel izquierdo para ver su contenido,<br>
                        o genera una factura para crear logs de depuracion.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom on load
        document.addEventListener('DOMContentLoaded', function() {
            var logContent = document.getElementById('log-content');
            if (logContent) {
                logContent.scrollTop = logContent.scrollHeight;
            }
        });
    </script>
<?php
}

/**
 * Apply syntax highlighting to log content
 *
 * @param string $content Raw log content
 * @return string Highlighted HTML content
 */
function logs_dev_highlight_content($content) {
    $content = esc_html($content);

    // Highlight date/time: [2026-01-22 14:30:45]
    $content = preg_replace(
        '/\[([\d]{4}-[\d]{2}-[\d]{2} [\d]{2}:[\d]{2}:[\d]{2})\]/',
        '<span class="hl-date">[$1]</span>',
        $content
    );

    // Highlight log levels
    $content = preg_replace('/\[ERROR\]/', '<span class="hl-level-error">[ERROR]</span>', $content);
    $content = preg_replace('/\[WARNING\]/', '<span class="hl-level-warning">[WARNING]</span>', $content);
    $content = preg_replace('/\[INFO\]/', '<span class="hl-level-info">[INFO]</span>', $content);

    // Highlight invoice types: [FB], [FT], [SYS]
    $content = preg_replace('/\[(FB|FT|SYS)\]/', '<span class="hl-type">[$1]</span>', $content);

    // Highlight user::action=>response pattern
    $content = preg_replace(
        '/([a-zA-Z0-9_]+)::([a-zA-Z0-9_]+)=&gt;/',
        '<span class="hl-user">$1</span><span class="hl-separator">::</span><span class="hl-action">$2</span><span class="hl-separator">=&gt;</span>',
        $content
    );

    // Highlight INPUT: and OUTPUT: labels
    $content = preg_replace('/(INPUT|OUTPUT):/', '<span class="hl-key">$1:</span>', $content);

    // Highlight JSON keys (simple pattern)
    $content = preg_replace('/&quot;([^&]+)&quot;:/', '<span class="hl-key">&quot;$1&quot;</span>:', $content);

    // Highlight string values in JSON
    $content = preg_replace('/: &quot;([^&]*)&quot;/', ': <span class="hl-string">&quot;$1&quot;</span>', $content);

    // Highlight numbers
    $content = preg_replace('/: ([\d.]+)([,\n\r}])/', ': <span class="hl-number">$1</span>$2', $content);

    return $content;
}
