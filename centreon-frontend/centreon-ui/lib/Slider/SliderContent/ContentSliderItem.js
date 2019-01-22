'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var ContentSliderItem = function ContentSliderItem(_ref) {
  var image = _ref.image,
      isActive = _ref.isActive;


  var styles = {
    backgroundImage: 'url(' + image + ')',
    backgroundSize: 'cover',
    backgroundRepeat: 'no-repeat',
    backgroundPosition: '50% 60%'
  };

  return _react2.default.createElement('div', {
    alt: 'Slider image',
    className: 'content-slider-item ' + (isActive ? 'active-slide' : ''),
    style: styles
  });
};

exports.default = ContentSliderItem;