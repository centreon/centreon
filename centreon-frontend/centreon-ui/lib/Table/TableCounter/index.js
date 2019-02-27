'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

var _IconAction = require('../../Icon/IconAction');

var _IconAction2 = _interopRequireDefault(_IconAction);

require('./table-counter.scss');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var TableCounter = function TableCounter(_ref) {
  var activeClass = _ref.activeClass,
      number = _ref.number;

  return _react2.default.createElement(
    'div',
    { className: 'table-counter ' + (activeClass ? activeClass : '') },
    _react2.default.createElement(
      'span',
      { className: 'table-counter-number' },
      number,
      _react2.default.createElement(_IconAction2.default, { iconActionType: 'arrow-right' })
    ),
    _react2.default.createElement(
      'div',
      { className: 'table-counter-dropdown' },
      _react2.default.createElement(
        'span',
        { className: 'table-counter-number' },
        number
      ),
      _react2.default.createElement(
        'span',
        { className: 'table-counter-number active' },
        number
      ),
      _react2.default.createElement(
        'span',
        { className: 'table-counter-number' },
        number
      ),
      _react2.default.createElement(
        'span',
        { className: 'table-counter-number' },
        number
      )
    )
  );
};

exports.default = TableCounter;