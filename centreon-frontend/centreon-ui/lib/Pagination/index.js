'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

require('./pagination.scss');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var Pagination = function Pagination() {
  return _react2.default.createElement(
    'div',
    { 'class': 'pagination' },
    _react2.default.createElement(
      'a',
      { href: '#' },
      'First'
    ),
    _react2.default.createElement(
      'a',
      { href: '#' },
      '1'
    ),
    _react2.default.createElement(
      'a',
      { href: '#', className: 'active' },
      '2'
    ),
    _react2.default.createElement(
      'a',
      { href: '#' },
      '3'
    ),
    _react2.default.createElement(
      'a',
      { href: '#' },
      'Last'
    )
  );
};

exports.default = Pagination;