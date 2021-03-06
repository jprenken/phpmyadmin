<?php
/**
 * Holds the PhpMyAdmin\Controllers\Database\RoutinesController
 */

declare(strict_types=1);

namespace PhpMyAdmin\Controllers\Database;

use PhpMyAdmin\CheckUserPrivileges;
use PhpMyAdmin\Common;
use PhpMyAdmin\Core;
use PhpMyAdmin\Database\Routines;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Response;
use PhpMyAdmin\Template;
use PhpMyAdmin\Util;
use function in_array;
use function strlen;

/**
 * Routines management.
 */
class RoutinesController extends AbstractController
{
    /** @var CheckUserPrivileges */
    private $checkUserPrivileges;

    /** @var DatabaseInterface */
    private $dbi;

    /**
     * @param Response          $response
     * @param string            $db       Database name
     * @param DatabaseInterface $dbi
     */
    public function __construct($response, Template $template, $db, CheckUserPrivileges $checkUserPrivileges, $dbi)
    {
        parent::__construct($response, $template, $db);
        $this->checkUserPrivileges = $checkUserPrivileges;
        $this->dbi = $dbi;
    }

    public function index(): void
    {
        global $db, $table, $tables, $num_tables, $total_num_tables, $sub_part, $is_show_stats;
        global $db_is_system_schema, $tooltip_truename, $tooltip_aliasname, $pos;
        global $errors, $PMA_Theme, $text_dir;

        $type = $_REQUEST['type'] ?? null;

        $this->checkUserPrivileges->getPrivileges();

        if (! $this->response->isAjax()) {
            /**
             * Displays the header and tabs
             */
            if (! empty($table) && in_array($table, $this->dbi->getTables($db))) {
                Common::table();
            } else {
                $table = '';
                Common::database();

                [
                    $tables,
                    $num_tables,
                    $total_num_tables,
                    $sub_part,
                    $is_show_stats,
                    $db_is_system_schema,
                    $tooltip_truename,
                    $tooltip_aliasname,
                    $pos,
                ] = Util::getDbInfo($db, $sub_part ?? '');
            }
        } elseif (strlen($db) > 0) {
            $this->dbi->selectDb($db);
        }

        /**
         * Keep a list of errors that occurred while
         * processing an 'Add' or 'Edit' operation.
         */
        $errors = [];

        $routines = new Routines($this->dbi, $this->template, $this->response);

        $routines->handleEditor();
        $routines->handleExecute();
        $routines->export();

        if (! Core::isValid($type, ['FUNCTION', 'PROCEDURE'])) {
            $type = null;
        }

        $items = $this->dbi->getRoutines($db, $type);
        $isAjax = $this->response->isAjax() && empty($_REQUEST['ajax_page_request']);

        $rows = '';
        foreach ($items as $item) {
            $rows .= $routines->getRow($item, $isAjax ? 'ajaxInsert hide' : '');
        }

        $this->render('database/routines/index', [
            'db' => $db,
            'table' => $table,
            'items' => $items,
            'rows' => $rows,
            'select_all_arrow_src' => $PMA_Theme->getImgPath() . 'arrow_' . $text_dir . '.png',
            'has_privilege' => Util::currentUserHasPrivilege('CREATE ROUTINE', $db, $table),
        ]);
    }
}
