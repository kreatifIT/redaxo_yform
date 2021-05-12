<?php

/**
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 12.05.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$columns    = $this->getVar('columns', []);
$values     = $this->getVar('values', []);
$index      = $this->getVar('index', []);
$fieldId    = $this->getVar('field_id');
$ytemplates = $this->getVar('ytemplates');
$mainId     = $this->getVar('main_id');

?>
<tr>
    <?php foreach ($columns as $i => $column): ?>
        <?php
        $field = $column['field'];

        $field->params['form_output'] = [];
        $field->params['this']->setObjectparams('form_name', $fieldId . '.' . $i);
        $field->params['this']->setObjectparams('form_ytemplate', $ytemplates);
        $field->params['this']->setObjectparams('main_id', $mainId);
        $field->params['form_name']       = $field->getName();
        $field->params['form_label_type'] = 'html';
        $field->params['send']            = false;

        if ('be_manager_relation' == $field->getElement(0)) {
            $field->params['main_table'] = $field->getElement('table');
            $field->setName($field->getElement('field'));
        }
        $field->setValue($values[$i] ?? '');
        $field->setId($index);
        $field->enterObject();
        $field_output = trim($field->params['form_output'][$field->getId()]);
        ?>
        <td class="be-value-input type-<?= $column['field']->getElement(0) ?>"
            data-title="<?= rex_escape($column['label'], 'html_attr') ?>"><?= $field_output ?></td>
    <?php endforeach ?>

    <td class="delete-row">
        <a class="btn btn-xs btn-delete" href="javascript:;" onclick="yformBeTable.functions.rmRow(this)">
            <i class="rex-icon rex-icon-delete"></i>
            <?php echo rex_i18n::msg('yform_delete') ?>
        </a>
    </td>
</tr>