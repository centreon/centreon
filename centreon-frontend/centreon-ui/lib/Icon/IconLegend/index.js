'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

var _IconAction = require('../IconAction');

var _IconAction2 = _interopRequireDefault(_IconAction);

require('./icon-legend.scss');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconLegend = function IconLegend(_ref) {
  var iconColor = _ref.iconColor,
      buttonIconType = _ref.buttonIconType,
      title = _ref.title,
      legendType = _ref.legendType;

  return _react2.default.createElement(
    'span',
    { className: 'icon-legend ' + (legendType ? legendType : '') },
    _react2.default.createElement(_IconAction2.default, { iconColor: iconColor ? iconColor : '', iconActionType: buttonIconType }),
    title && _react2.default.createElement(
      'span',
      { className: 'icon-legend-title' },
      title
    )
  );
};

exports.default = IconLegend;