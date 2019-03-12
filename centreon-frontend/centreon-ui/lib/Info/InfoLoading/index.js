'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

var _IconAction = require('../../Icon/IconAction');

var _IconAction2 = _interopRequireDefault(_IconAction);

require('./info-loading.scss');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var InfoLoading = function InfoLoading(_ref) {
  var infoType = _ref.infoType,
      color = _ref.color,
      customClass = _ref.customClass,
      label = _ref.label,
      iconActionType = _ref.iconActionType,
      iconColor = _ref.iconColor;

  return _react2.default.createElement(
    'span',
    {
      className: 'info-loading info-loading-' + infoType + '-' + color + ' linear ' + (customClass ? customClass : '')
    },
    iconActionType ? _react2.default.createElement(_IconAction2.default, { iconColor: iconColor, iconActionType: iconActionType }) : '',
    label,
    _react2.default.createElement('span', { className: 'info-loading-icon' })
  );
};

exports.default = InfoLoading;