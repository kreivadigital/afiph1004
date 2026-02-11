<?php
/**
 * Pagina de logs del plugin (Base de datos)
 *
 * Muestra los logs de acciones de facturacion almacenados en la base de datos.
 *
 * @package ServicesAPIHotel
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function logs_list() {
    // Get filters from query string
    $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $level_filter = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
    $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Get logs using Logger class
    $result = SAH_Logger::get_db_logs(array(
        'page' => $current_page,
        'per_page' => 25,
        'level' => $level_filter,
        'invoice_type' => $type_filter,
        'date_from' => $date_from,
        'date_to' => $date_to,
        'search' => $search,
    ));

    $logs = $result['logs'];
    $total_pages = $result['pages'];
    $total_items = $result['total'];
?>
    <style>
        .sah-logs-wrap {
            max-width: 1400px;
        }
        .log-level-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .log-level-badge.error { background: #dc3232; color: #fff; }
        .log-level-badge.warning { background: #ffb900; color: #1d2327; }
        .log-level-badge.info { background: #0073aa; color: #fff; }

        .invoice-type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
        }
        .invoice-type-badge.fb { background: #e1f5fe; color: #01579b; }
        .invoice-type-badge.ft { background: #fce4ec; color: #880e4f; }
        .invoice-type-badge.sys { background: #f3e5f5; color: #4a148c; }

        .logs-filters {
            background: #fff;
            padding: 15px 20px;
            border: 1px solid #ccd0d4;
            margin-bottom: 20px;
        }
        .logs-filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        .logs-filters .filter-group label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            color: #1d2327;
            margin-bottom: 5px;
        }
        .logs-filters-actions {
            display: flex;
            gap: 10px;
        }
        .log-response-cell {
            max-width: 300px;
        }
        .log-response {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .log-response:hover {
            white-space: normal;
            word-break: break-word;
        }
        .logs-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .logs-summary .count {
            font-weight: 600;
        }
        .logs-link-dev {
            color: #0073aa;
            text-decoration: none;
        }
        .logs-link-dev:hover {
            text-decoration: underline;
        }
        .tablenav {
            margin-top: 15px;
        }
        .tablenav-pages {
            float: right;
        }
        .tablenav-pages a,
        .tablenav-pages span {
            padding: 5px 10px;
            margin: 0 2px;
            border: 1px solid #ccd0d4;
            background: #fff;
            text-decoration: none;
        }
        .tablenav-pages .current {
            background: #0073aa;
            color: #fff;
            border-color: #0073aa;
        }
        .empty-logs {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>

    <div class="wrap sah-logs-wrap">
        <h1>Logs de Facturacion</h1>
        <p>Registro de acciones de facturacion AFIP almacenados en base de datos.</p>

        <!-- Filters -->
        <form method="get" action="">
            <input type="hidden" name="page" value="logs_list">
            <div class="logs-filters">
                <div class="logs-filters-row">
                    <div class="filter-group">
                        <label for="level">Nivel</label>
                        <select name="level" id="level">
                            <option value="">Todos</option>
                            <option value="ERROR" <?php selected($level_filter, 'ERROR'); ?>>Error</option>
                            <option value="WARNING" <?php selected($level_filter, 'WARNING'); ?>>Warning</option>
                            <option value="INFO" <?php selected($level_filter, 'INFO'); ?>>Info</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="type">Tipo Factura</label>
                        <select name="type" id="type">
                            <option value="">Todos</option>
                            <option value="FB" <?php selected($type_filter, 'FB'); ?>>Factura B</option>
                            <option value="FT" <?php selected($type_filter, 'FT'); ?>>Factura T</option>
                            <option value="SYS" <?php selected($type_filter, 'SYS'); ?>>Sistema</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date_from">Desde</label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">Hasta</label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="s">Buscar</label>
                        <input type="text" name="s" id="s" value="<?php echo esc_attr($search); ?>" placeholder="Transaccion, accion...">
                    </div>
                </div>
                <div class="logs-filters-actions">
                    <button type="submit" class="button button-primary">Filtrar</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=logs_list')); ?>" class="button">Limpiar</a>
                </div>
            </div>
        </form>

        <!-- Summary -->
        <div class="logs-summary">
            <span class="count">Total: <?php echo number_format($total_items); ?> registros</span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=logs_dev')); ?>" class="logs-link-dev">
                Ver Logs de Desarrollo (archivos) &rarr;
            </a>
        </div>

        <!-- Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 145px;">Fecha</th>
                    <th style="width: 70px;">Nivel</th>
                    <th style="width: 55px;">Tipo</th>
                    <th style="width: 90px;">Usuario</th>
                    <th style="width: 160px;">Accion</th>
                    <th class="log-response-cell">Respuesta</th>
                    <th style="width: 110px;">Transaccion</th>
                    <th style="width: 100px;">CAE</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" class="empty-logs">
                            No se encontraron logs con los filtros seleccionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->log_date); ?></td>
                            <td>
                                <span class="log-level-badge <?php echo esc_attr(strtolower($log->log_level)); ?>">
                                    <?php echo esc_html($log->log_level); ?>
                                </span>
                            </td>
                            <td>
                                <span class="invoice-type-badge <?php echo esc_attr(strtolower($log->invoice_type)); ?>">
                                    <?php echo esc_html($log->invoice_type); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->username); ?></td>
                            <td><?php echo esc_html($log->action); ?></td>
                            <td class="log-response-cell">
                                <span class="log-response" title="<?php echo esc_attr($log->response); ?>">
                                    <?php echo esc_html($log->response); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->transaction_id); ?></td>
                            <td><?php echo esc_html($log->cae); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo; Anterior',
                        'next_text' => 'Siguiente &raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'type' => 'array',
                    ));

                    if ($page_links) {
                        echo implode('', $page_links);
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php
}
