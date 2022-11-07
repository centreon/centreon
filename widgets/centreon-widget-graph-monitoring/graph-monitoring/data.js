/* global jQuery */
jQuery(function() {
  var image = document.getElementById("graph");
  if (image) {
    image.onload = function() {
      var h = this.height;
      parent.iResize(window.name, h);
      jQuery(window).resize(function() {
        reload();
      });
    };
    reload();
  }
});

function reload() {
  var image = document.getElementById("graph");
  var w = jQuery(window).width();
  var imgSrc = jQuery(image).data('src');
  var now = new Date();

  imgSrc += '&time=' + Math.round(now.getTime() / 1000) + '&width=' + w;

  image.src = imgSrc;

  if (autoRefresh) {
    if (timeout) {
      clearTimeout(timeout);
    }
    timeout = setTimeout(reload, (autoRefresh * 1000));
  }
}
