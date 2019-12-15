require(['jquery'], $ => {
    $(document).ready(() => {
        const CONDITION_EMPTYFIELD = 2;

        const $message = $('#datafield_notification-message');

        const $condition = $('#datafield_notification-condition');

        const $emptyfields = $('.datafield_notification-condition-emptyfield');

        const updateFields = () => {
            $emptyfields.hide();
            if (parseInt($condition.val()) === CONDITION_EMPTYFIELD) {
                $emptyfields.show();
            }
        };

        $.each($('.datafield_notification-messagevariables'), (idx, ele) => {
            const $ele = $(ele);
            $ele.append(` - ${$ele.attr('data-value')}`);
        });

        $('.datafield_notification-messagevariables').click(e => {
            const messagevariable = $(e.target).attr('data-value');
            $message.val($message.val() + messagevariable);
        });

        $condition.change(() => { updateFields(); });

        updateFields();
    });
});
