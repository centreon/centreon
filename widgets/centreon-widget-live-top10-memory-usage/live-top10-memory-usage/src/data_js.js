var timeout;

jQuery(function() {
  loadTop10();
});

function loadTop10() {
  jQuery.ajax({
    url: './index.php',
    type: 'GET',
    data: {
      widgetId: widgetId
    },
    success : function(htmlData) {
      var data = jQuery(htmlData).filter('#top-10-memory').find('table');
      var $container = $('#top-10-memory');
      var h;

      $container.html(data);

      h = $container.scrollHeight + 10;

      if(h){
        parent.iResize(window.name, h);
      } else {
        parent.iResize(window.name, 200);
      }
    }
  });

  if (autoRefresh && autoRefresh != "") {
    if (timeout) {
      clearTimeout(timeout);
    }

    timeout = setTimeout(loadTop10, (autoRefresh * 1000));
  }
}
