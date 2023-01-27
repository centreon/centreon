var timeout;

jQuery(function () {
    loadMetric();
});

function loadMetric()
{
  jQuery.ajax({
        url: './index.php',
        type: 'GET',
        data: {
            widgetId: widgetId
        },
        success : function (htmlData) {
            const data = jQuery(htmlData).filter('#metric');
            const container = $('#metric');
            container.html(data);

            const height = container[0].scrollHeight;
            if (height) {
                parent.iResize(window.name, height);
            } else {
                parent.iResize(window.name, 340);
            }
        }
    });

    if (autoRefresh && autoRefresh != '') {
        if (timeout) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(loadMetric, (autoRefresh * 1000));
    }
}