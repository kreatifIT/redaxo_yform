<?php
$class_group = trim('form-group ' . $this->getHTMLClass() . ' ' . $this->getWarningClass());

$data_index = 0;
$notice = [];
if ('' != $this->getElement('notice')) {
    $notice[] = rex_i18n::translate($this->getElement('notice'), false);
}
if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']) {
    $notice[] = '<span class="text-warning">' . rex_i18n::translate($this->params['warning_messages'][$this->getId()], false) . '</span>'; //    var_dump();
}
if (count($notice) > 0) {
    $notice = '<p class="help-block">' . implode('<br />', $notice) . '</p>';
} else {
    $notice = '';
}

$ytemplates = $this->params['this']->getObjectparams('form_ytemplate');
$main_id = $this->params['this']->getObjectparams('main_id');


$fragment = new rex_fragment();
$fragment->setVar('columns', $columns, false);
$fragment->setVar('values', []);
$fragment->setVar('field_id', $this->getId(), false);
$fragment->setVar('ytemplates', $ytemplates, false);
$fragment->setVar('main_id', $main_id);
$fragment->setVar('index', '{{FIELD_ID}}');
$newRowHtml = $fragment->parse('yform/manager/value/be_table_row.php');

?>
<div class="<?= $class_group ?>" id="<?= $this->getHTMLId() ?>" data-be-table data-col-count="<?= count($columns) ?>" data-row-index="<?= count($data) ?>"
     data-row-html="<?= rex_escape($newRowHtml, 'html_attr') ?>">
    <label class="control-label" for="<?php echo $this->getFieldId() ?>"><?php echo $this->getLabel() ?></label>
    <table class="table table-hover table-bordered">
        <thead>
        <tr>
            <?php foreach ($columns as $column): ?>
                <th class="type-<?= $column['field']->getElement(0) ?>"><?php echo htmlspecialchars($column['label']) ?></th>
            <?php endforeach ?>
            <th class="rex-table-action">
                <a class="btn btn-xs btn-primary" href="javascript:;" onclick="yformBeTable.functions.addRow(this)">
                    <i class="rex-icon rex-icon-add"></i>
                    <?php echo rex_i18n::msg('yform_add_row') ?>
                </a>
            </th>
        </tr>
        </thead>
        <tbody>
        <?php
        foreach ($data as $data_index => $row) {
            $fragment = new rex_fragment();
            $fragment->setVar('columns', $columns, false);
            $fragment->setVar('values', $row, false);
            $fragment->setVar('field_id', $this->getId(), false);
            $fragment->setVar('ytemplates', $ytemplates, false);
            $fragment->setVar('main_id', $main_id);
            $fragment->setVar('index', $data_index);
            echo $fragment->parse('yform/manager/value/be_table_row.php');
        }
        ?>
        </tbody>
    </table>
    <a class="btn btn-primary btn-xs add-mobile-btn" href="javascript:;" onclick="yformBeTable.functions.addRow(this)">
        <i class="rex-icon rex-icon-add"></i>
        <?php echo rex_i18n::msg('yform_add_row') ?>
    </a>

    <?php echo $notice ?>
</div>
