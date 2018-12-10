'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var CardItem = function (_Component) {
  _inherits(CardItem, _Component);

  function CardItem() {
    _classCallCheck(this, CardItem);

    return _possibleConstructorReturn(this, (CardItem.__proto__ || Object.getPrototypeOf(CardItem)).apply(this, arguments));
  }

  _createClass(CardItem, [{
    key: 'render',
    value: function render() {
      var _props = this.props,
          children = _props.children,
          itemBorderColor = _props.itemBorderColor,
          itemFooterColor = _props.itemFooterColor,
          itemFooterLabel = _props.itemFooterLabel;

      return _react2.default.createElement(
        'div',
        { className: 'card-item card-item-bordered-' + itemBorderColor },
        children,
        _react2.default.createElement(
          'span',
          { className: 'card-item-footer card-item-footer-' + itemFooterColor },
          itemFooterLabel
        )
      );
    }
  }]);

  return CardItem;
}(_react.Component);

exports.default = CardItem;