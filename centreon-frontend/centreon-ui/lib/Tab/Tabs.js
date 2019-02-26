'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

var _Tab = require('./Tab');

var _Tab2 = _interopRequireDefault(_Tab);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var Tabs = function (_Component) {
  _inherits(Tabs, _Component);

  function Tabs(props) {
    _classCallCheck(this, Tabs);

    var _this = _possibleConstructorReturn(this, (Tabs.__proto__ || Object.getPrototypeOf(Tabs)).call(this, props));

    _this.onClickTabItem = function (tab) {
      _this.setState({ activeTab: tab });
    };

    _this.state = {
      activeTab: _this.props.children[0].props.label
    };
    return _this;
  }

  _createClass(Tabs, [{
    key: 'render',
    value: function render() {
      var onClickTabItem = this.onClickTabItem,
          children = this.props.children,
          activeTab = this.state.activeTab;


      return _react2.default.createElement(
        'div',
        { className: 'tab' },
        _react2.default.createElement(
          'ol',
          { className: 'tab-list' },
          children.map(function (child) {
            var label = child.props.label;


            return _react2.default.createElement(_Tab2.default, {
              activeTab: activeTab,
              key: label,
              label: label,
              onClick: onClickTabItem
            });
          })
        ),
        _react2.default.createElement(
          'div',
          { className: 'tab-content' },
          children.map(function (child) {
            if (child.props.label !== activeTab) return undefined;
            return child.props.children;
          })
        )
      );
    }
  }]);

  return Tabs;
}(_react.Component);

exports.default = Tabs;