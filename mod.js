require(['jquery'], $ => {
    $(document).ready(() => {
        const $message = $('#datafield_notification-message');
        $.each($('.datafield_notification-messagevariables'), (idx, ele) => {
            const $ele = $(ele);
            $ele.append(` - ${$ele.attr('data-value')}`);
        });

        $('.datafield_notification-messagevariables').click(e => {
            const messagevariable = $(e.target).attr('data-value');
            $message.val($message.val() + messagevariable);
        });
    });
});
