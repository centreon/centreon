"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./switcher.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var Switcher = function (_React$Component) {
  _inherits(Switcher, _React$Component);

  function Switcher() {
    var _ref;

    var _temp, _this, _ret;

    _classCallCheck(this, Switcher);

    for (var _len = arguments.length, args = Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }

    return _ret = (_temp = (_this = _possibleConstructorReturn(this, (_ref = Switcher.__proto__ || Object.getPrototypeOf(Switcher)).call.apply(_ref, [this].concat(args))), _this), _this.state = {
      value: true
    }, _this.UNSAFE_componentDidMount = function () {
      var value = _this.props.value;

      if (value) {
        _this.setState({
          value: value
        });
      }
    }, _this.UNSAFE_componentWillReceiveProps = function (nextProps) {
      var value = nextProps.value;

      if (_this.state.value != value) {
        _this.setState({
          value: value
        });
      }
    }, _this.onChange = function () {
      var _this$props = _this.props,
          onChange = _this$props.onChange,
          filterKey = _this$props.filterKey;
      var value = _this.state.value;

      _this.setState({
        value: !value
      });
      if (onChange) {
        onChange(!value, filterKey);
      }
    }, _temp), _possibleConstructorReturn(_this, _ret);
  }

  _createClass(Switcher, [{
    key: "render",
    value: function render() {
      var _props = this.props,
          switcherTitle = _props.switcherTitle,
          switcherStatus = _props.switcherStatus,
          customClass = _props.customClass;
      var value = this.state.value;

      return _react2.default.createElement(
        "div",
        { className: "switcher " + customClass },
        _react2.default.createElement(
          "span",
          { className: "switcher-title" },
          switcherTitle ? switcherTitle : " "
        ),
        _react2.default.createElement(
          "span",
          { className: "switcher-status" },
          switcherStatus
        ),
        _react2.default.createElement(
          "label",
          { className: "switch" },
          _react2.default.createElement("input", { type: "checkbox", checked: value, onClick: this.onChange.bind(this) }),
          _react2.default.createElement("span", { className: "switch-slider switch-round" })
        )
      );
    }
  }]);

  return Switcher;
}(_react2.default.Component);

exports.default = Switcher;