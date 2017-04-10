jQuery(document).ready(function ($) {
    var showInputsForType = function () {
//        jQuery('')
        jQuery('select[id^="crumbls_cache_type"]').each(function () {
            var val = jQuery(this).val();
            var p = jQuery(this).closest('.ui-tabs-panel');
            var children = p.find('.field');
            children.not('.'+val).addClass('hidden');
            children.filter(function( index ) {
                return jQuery(this).hasClass(val);
            }).removeClass('hidden');
        })
    }
    showInputsForType();
    var tabs = $('.crumbls-tabs').tabs({
        select: function (event, ui) {
            console.log('wtf');
            console.log(ui);
//                $("#tab-wrap").attr('class', $(ui.panel).attr('id'));
        },
        activate: function (event, ui) {
            jQuery(':focus').blur();
            if (ui.oldTab == ui.newTab) {
                return;
            }
            ui.oldTab.removeClass('nav-tab-active');
            ui.newTab.addClass('nav-tab-active');
        },
    });

    /**
     * Handle select changes.
     */
    $('select[id^="crumbls_cache_type"]').on('change', function (e) {
        showInputsForType();
    });
});