<?php

declare(strict_types=1);

/**
 * yform.
 *
 * @author jan.kristinus[at]redaxo[dot]org Jan Kristinus
 * @author <a href="http://www.yakamara.de">www.yakamara.de</a>
 */
class rex_yform_action_db extends rex_yform_action_abstract
{
    public function executeAction()
    {
        $sql = rex_sql::factory();
        $sql->setDebug($this->params['debug']);

        if (!$main_table = $this->getElement(2)) {
            $main_table = $this->params['main_table'];
        }

        $main_table = str_replace('%TABLE_PREFIX%', rex::getTablePrefix(), $main_table);

        if ('' == $main_table) {
            $this->params['form_show'] = true;
            $this->params['hasWarnings'] = true;
            $this->params['warning_messages'][] = $this->params['Error-Code-InsertQueryError'];

            return false;
        }

        $sql->setTable($main_table);

        if ($where = $this->getElement(3)) {
            if ('main_where' == $where) {
                $where = $this->params['main_where'];
            }
        }

        try {

            foreach ($this->params['value_pool']['sql'] as $key => $value) {
                $sql->setValue($key, $value);
                if ('' != $where) {
                    $where = str_replace('###'.$key.'###', addslashes((string) $value), $where);
                }
            }

            if ($this->params['value_pool']['raw_sql']) {
                foreach ($this->params['value_pool']['raw_sql'] as $key => $value) {
                    $sql->setRawValue($key, $value);
                    if ($where != '') {
                        $where = str_replace('###' . $key . '###', addslashes($value), $where);
                    }
                }
            }
            $action = null;

            if ('' != $where) {
                $sql->setWhere($where);
                $sql->update();
                $action = 'update';

                if ($this->params['main_id'] <= 0) {
                    $sql_id = rex_sql::factory();
                    $sql_id->setTable($main_table);
                    $sql_id->setWhere($where);
                    $sql_id->select('id');
                    $this->params['main_id'] = $sql_id->getValue('id');
                    $this->params['value_pool']['email']['ID'] = $this->params['main_id'];
                }
            } else {
                $sql->insert();
                $action = 'insert';
                $id = $sql->getLastId();
                $this->params['main_id'] = $id;
                $this->params['value_pool']['email']['ID'] = $id;
                // $this->params["value_pool"]["sql"]["ID"] = $id;
            }
        } catch (Exception $e) {
            $this->params['form_show'] = true;
            $this->params['hasWarnings'] = true;
            $this->params['warning_messages'][] = $e->getMessage();
        }

        if (0 < count($this->params['warning_messages'])) {
            if ($this->params['debug']) {
                dump($this->params['warning_messages']);
            }
        } else {
            rex_extension::registerPoint(new rex_extension_point('REX_YFORM_SAVED', $sql,
                [
                    'form' => $this,
                    'sql' => $sql,
                    'table' => $main_table,
                    'action' => $action,
                    'id' => $this->params['main_id'],
                    'yform' => true,
                ]
            ));
        }
    }

    public function getDescription()
    {
        return 'action|db|tblname|[where(id=2)/main_where]';
    }
}
