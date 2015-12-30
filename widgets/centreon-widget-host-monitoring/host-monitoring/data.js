
jQuery(function () {
    loadPage();
});

/**
 * Load Page
 */
function loadPage()
{
    jQuery.ajax("./src/index.php?widgetId=" + widgetId + "&page=" + pageNumber, {
        success: function (htmlData) {
            jQuery("#hostMonitoringTable").empty().append(htmlData);
            var hostMonitoringTable = jQuery("#hostMonitoringTable").find("img, style, script, link").load(function () {
                var h = document.getElementById("hostMonitoringTable").scrollHeight + 50;
                parent.iResize(window.name, h);
            });
        }
    });
    if (autoRefresh) {
        if (timeout) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(loadPage, (autoRefresh * 1000));
    }
}

/**
 * Load toolbar
 */
function loadToolBar()
{
    jQuery("#toolBar").load(
            "./src/toolbar.php",
            {
                widgetId: widgetId
            }
    );
}

jQuery(function () {
    loadToolBar();
    loadPage();
    ManageMoreView(0);
    $('.checkall').live('click', function () {
        var chck = this.checked;
        $(this).parents().find(':checkbox').each(function () {
            $(this).attr('checked', chck);
            clickedCb[$(this).attr('id')] = chck;
        });
    });
    $(".selection").live('click', function () {
        clickedCb[$(this).attr('id')] = this.checked;
    });

    $(".manageMoreViews").live('click', function () {
        ManageMoreView(1);
    });
});


function ResizeFrame(ifrm, height)
{
    if (height < 150) {
        height = 150;
    }
    jQuery("[name=" + ifrm + "]").height(height);
}

function ManageMoreView(click)
{
    if (click == 0) {
        if (more_views == 0 || more_views == 1) {
            jQuery('#manage').val(more_views);
            jQuery('.manageMoreViews').show();
            if (more_views == 0) {
              jQuery('.manageMoreViews').addClass('more_views_disable').removeClass('more_views_enable');
            } else {
               jQuery('.manageMoreViews').addClass('more_views_enable').removeClass('more_views_disable');
            }
        }
    } else {
        if (jQuery('#manage').val() == 1) {
           jQuery('#manage').val(0);
           jQuery('.manageMoreViews').addClass('more_views_disable').removeClass('more_views_enable');
           jQuery('#toolBar, #pagination').hide();
        } else {
            jQuery('#manage').val(1);
            jQuery('.manageMoreViews').addClass('more_views_enable').removeClass('more_views_disable');
           jQuery('#toolBar, #pagination').show();
        }
    }

}