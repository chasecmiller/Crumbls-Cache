jQuery(document).ready(function ($) {
    var showInputsForType = function () {
        jQuery('select[id^="crumbls_settings_"]').each(function () {
            var val = jQuery(this).val();
            var p = jQuery(this).closest('.ui-tabs-panel');
            var children = p.find('tr').not('.always-visible');
            children.not('.'+val).addClass('hidden');
            children.filter(function( index ) {
                return jQuery(this).hasClass(val);
            }).removeClass('hidden');
        })
    }
    showInputsForType();
    var tabs = $('.crumbls-tabs').tabs({
        select: function (event, ui) {
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
    $('select[id^="crumbls_settings_"]').on('change', function (e) {
        showInputsForType();
    });
});