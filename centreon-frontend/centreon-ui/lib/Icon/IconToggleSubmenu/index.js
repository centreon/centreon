'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

require('./icon-toggle-submenu.scss');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconToggleSubmenu = function IconToggleSubmenu(_ref) {
  var iconType = _ref.iconType;

  return _react2.default.createElement('span', { 'class': 'icons-toggle-' + iconType });
};

exports.default = IconToggleSubmenu;