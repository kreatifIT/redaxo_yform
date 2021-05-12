var yformBeTable = (function ($) {
    var _initiators = {},
        _functions = {};

    $(document).on('rex:ready', function () {
        $('[data-be-table]:not(.initialized)').each(function () {
            _initiators.initTable(this);
        });
    });

    _initiators.initTable = function (tableEl) {
        var $table = $(tableEl);
        $table.addClass('initialized');
    };

    _functions.addRow = function (_this) {
        var $this = $(_this),
            $wrapper = $this.parents('[data-be-table]'),
            $table = $wrapper.find('table'),
            rowHtml = $wrapper.data('row-html'),
            rowIndex = $wrapper.data('row-index'),
            colIndex = $wrapper.data('col-count'),
            regexp = [
                new RegExp("(REX_MEDIA_)", 'g'),
                new RegExp("(openREXMedia\\()", 'g'),
                new RegExp("(addREXMedia\\()", 'g'),
                new RegExp("(deleteREXMedia\\()", 'g'),
                new RegExp("(viewREXMedia\\()", 'g'),

                new RegExp("(REX_MEDIALIST_SELECT_)", 'g'),
                new RegExp("(moveREXMedialist\\()", 'g'),
                new RegExp("(openREXMedialist\\()", 'g'),
                new RegExp("(addREXMedialist\\()", 'g'),
                new RegExp("(deleteREXMedialist\\()", 'g'),
                new RegExp("(viewREXMedialist\\()", 'g'),

                new RegExp("(REX_LINK_)[0-9]+", 'g'),
                new RegExp("(REX_LINK_NAME\\[)[0-9]+", 'g'),
                new RegExp("(openLinkMap\\('REX_LINK_)[0-9]+", 'g'),
                new RegExp("(deleteREXLink\\()[0-9]+", 'g'),

                new RegExp("(REX_LINKLIST_SELECT_)", 'g'),
                new RegExp("(moveREXLinklist\\()", 'g'),
                new RegExp("(openREXLinklist\\()", 'g'),
                new RegExp("(deleteREXLinklist\\()", 'g'),
            ];

        // set new row field ids
        rowHtml = rowHtml.replace(new RegExp('{{FIELD_ID}}', 'g'), rowIndex);
        rowHtml = rowHtml.replace(new RegExp('--FIELD_ID--', 'g'), rowIndex);

        for (var i in regexp) {
            rowHtml = rowHtml.replace(regexp[i], '$1' + parseInt(rowIndex + colIndex));
        }

        var $tr = $(rowHtml);

        // replace be medialist
        $tr.find('select[id^="REX_MEDIALIST_"]').each(function () {
            var $select = $(this),
                $input = $select.parent().children('input:first'),
                id = $select.prop('id').replace('REX_MEDIALIST_SELECT_', '');
            $input.prop('id', 'REX_MEDIALIST_' + id);
        });

        $table.find('tbody').append($tr);
        $wrapper.data('row-index', rowIndex + 1);
        $(document).trigger('be_table:row-added', [$tr]);
    };

    _functions.rmRow = function (_this) {
        var $this = $(_this),
            $tr = $this.parents('tr'),
            $wrapper = $tr.parents('[data-be-table]');

        $tr.fadeOut('normal', function () {
            $(document).trigger('be_table:before-row-remove', [$tr]);
            $tr.remove();
            $(document).trigger('be_table:row-removed', [$wrapper]);
        });
    };


    return {
        initiators: _initiators,
        functions: _functions
    };
})(jQuery);