'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

require('./icon-round.scss');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconRound = function IconRound(_ref) {
  var iconColor = _ref.iconColor,
      iconType = _ref.iconType,
      iconTitle = _ref.iconTitle;

  return _react2.default.createElement(
    'span',
    { className: 'icons icons-round ' + iconColor },
    _react2.default.createElement('span', { className: 'iconmoon icon-' + iconType, title: iconTitle })
  );
};

exports.default = IconRound;