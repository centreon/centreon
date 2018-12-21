'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

require('./icon-number.scss');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconNumber = function IconNumber(_ref) {
  var iconColor = _ref.iconColor,
      iconType = _ref.iconType,
      iconNumber = _ref.iconNumber;

  return _react2.default.createElement(
    'a',
    { className: 'icons icons-number ' + iconType + ' ' + iconColor },
    _react2.default.createElement(
      'span',
      { className: 'number-wrap' },
      _react2.default.createElement(
        'span',
        { className: 'number-count' },
        iconNumber
      )
    )
  );
};

exports.default = IconNumber;