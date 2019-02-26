'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

var _RadioButton = require('../../RadioButton');

var _RadioButton2 = _interopRequireDefault(_RadioButton);

var _SearchLive = require('../../Search/SearchLive');

var _SearchLive2 = _interopRequireDefault(_SearchLive);

var _Checkbox = require('../../Checkbox');

var _Checkbox2 = _interopRequireDefault(_Checkbox);

require('./table-dynamic.scss');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var TableDynamic = function (_Component) {
  _inherits(TableDynamic, _Component);

  function TableDynamic() {
    _classCallCheck(this, TableDynamic);

    return _possibleConstructorReturn(this, (TableDynamic.__proto__ || Object.getPrototypeOf(TableDynamic)).apply(this, arguments));
  }

  _createClass(TableDynamic, [{
    key: 'render',
    value: function render() {
      return _react2.default.createElement(
        'table',
        { 'class': 'table-dynamic' },
        _react2.default.createElement(
          'thead',
          null,
          _react2.default.createElement(
            'tr',
            null,
            _react2.default.createElement(
              'th',
              { scope: 'col' },
              _react2.default.createElement(
                'div',
                { className: 'container__row' },
                _react2.default.createElement(
                  'div',
                  { className: 'container__col-md-3 center-vertical' },
                  _react2.default.createElement(_Checkbox2.default, { label: 'ALL HOSTS', name: 'all-hosts' })
                ),
                _react2.default.createElement(
                  'div',
                  { className: 'container__col-md-6 center-vertical' },
                  _react2.default.createElement(_SearchLive2.default, null)
                )
              )
            ),
            _react2.default.createElement(
              'th',
              { scope: 'col' },
              _react2.default.createElement(_SearchLive2.default, null)
            )
          )
        ),
        _react2.default.createElement(
          'tbody',
          null,
          _react2.default.createElement(
            'tr',
            null,
            _react2.default.createElement(
              'td',
              null,
              _react2.default.createElement(_Checkbox2.default, {
                label: 'Host 1 lorem ipsum dolor sit amet',
                name: 'host1'
              })
            ),
            _react2.default.createElement(
              'td',
              null,
              _react2.default.createElement(_SearchLive2.default, null)
            )
          ),
          _react2.default.createElement(
            'tr',
            null,
            _react2.default.createElement(
              'td',
              null,
              _react2.default.createElement(_Checkbox2.default, {
                label: 'Host 1 lorem ipsum dolor sit amet',
                name: 'host1'
              })
            ),
            _react2.default.createElement(
              'td',
              null,
              _react2.default.createElement(_SearchLive2.default, null)
            )
          ),
          _react2.default.createElement(
            'tr',
            null,
            _react2.default.createElement(
              'td',
              null,
              _react2.default.createElement(_Checkbox2.default, {
                label: 'Host 1 lorem ipsum dolor sit amet',
                name: 'host1'
              })
            ),
            _react2.default.createElement(
              'td',
              null,
              _react2.default.createElement(_SearchLive2.default, null)
            )
          ),
          _react2.default.createElement(
            'tr',
            null,
            _react2.default.createElement(
              'td',
              null,
              _react2.default.createElement(_Checkbox2.default, {
                label: 'Host 1 lorem ipsum dolor sit amet',
                name: 'host1'
              })
            ),
            _react2.default.createElement(
              'td',
              null,
              _react2.default.createElement(_SearchLive2.default, null)
            )
          )
        )
      );
    }
  }]);

  return TableDynamic;
}(_react.Component);

exports.default = TableDynamic;