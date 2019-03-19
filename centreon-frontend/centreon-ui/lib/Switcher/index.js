"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;

var _react = _interopRequireDefault(require("react"));

require("./switcher.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _typeof(obj) { if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") { _typeof = function _typeof(obj) { return typeof obj; }; } else { _typeof = function _typeof(obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }; } return _typeof(obj); }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

function _possibleConstructorReturn(self, call) { if (call && (_typeof(call) === "object" || typeof call === "function")) { return call; } return _assertThisInitialized(self); }

function _getPrototypeOf(o) { _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf : function _getPrototypeOf(o) { return o.__proto__ || Object.getPrototypeOf(o); }; return _getPrototypeOf(o); }

function _assertThisInitialized(self) { if (self === void 0) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function"); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, writable: true, configurable: true } }); if (superClass) _setPrototypeOf(subClass, superClass); }

function _setPrototypeOf(o, p) { _setPrototypeOf = Object.setPrototypeOf || function _setPrototypeOf(o, p) { o.__proto__ = p; return o; }; return _setPrototypeOf(o, p); }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

var Switcher =
/*#__PURE__*/
function (_React$Component) {
  _inherits(Switcher, _React$Component);

  function Switcher() {
    var _getPrototypeOf2;

    var _this;

    _classCallCheck(this, Switcher);

    for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }

    _this = _possibleConstructorReturn(this, (_getPrototypeOf2 = _getPrototypeOf(Switcher)).call.apply(_getPrototypeOf2, [this].concat(args)));

    _defineProperty(_assertThisInitialized(_this), "state", {
      value: true,
      toggled: false
    });

    _defineProperty(_assertThisInitialized(_this), "UNSAFE_componentDidMount", function () {
      var value = _this.props.value;

      if (value) {
        _this.setState({
          value: value
        });
      }
    });

    _defineProperty(_assertThisInitialized(_this), "UNSAFE_componentWillReceiveProps", function (nextProps) {
      var value = nextProps.value;

      if (_this.state.value != value) {
        _this.setState({
          toggled: !value,
          value: value
        });
      }
    });

    _defineProperty(_assertThisInitialized(_this), "onChange", function () {
      var _this$props = _this.props,
          onChange = _this$props.onChange,
          filterKey = _this$props.filterKey;
      var _this$state = _this.state,
          value = _this$state.value,
          toggled = _this$state.toggled;

      _this.setState({
        value: !value,
        toggled: !toggled
      });

      if (onChange) {
        onChange(!value, filterKey);
      }
    });

    _defineProperty(_assertThisInitialized(_this), "toggled", function () {
      var toggled = _this.state.toggled;

      _this.setState({
        toggled: !toggled
      });
    });

    return _this;
  }

  _createClass(Switcher, [{
    key: "render",
    value: function render() {
      var _this$props2 = this.props,
          switcherTitle = _this$props2.switcherTitle,
          switcherStatus = _this$props2.switcherStatus,
          customClass = _this$props2.customClass;
      var _this$state2 = this.state,
          value = _this$state2.value,
          toggled = _this$state2.toggled;
      return _react.default.createElement("div", {
        className: "switcher ".concat(customClass ? customClass : '')
      }, _react.default.createElement("span", {
        className: "switcher-title"
      }, switcherTitle ? switcherTitle : " "), _react.default.createElement("span", {
        className: "switcher-status"
      }, switcherStatus), _react.default.createElement("label", {
        className: "switch" + (toggled ? " switch-active" : " switch-hide")
      }, _react.default.createElement("input", {
        type: "checkbox",
        checked: !value,
        onClick: this.onChange.bind(this)
      }), _react.default.createElement("span", {
        className: "switch-slider switch-round"
      }), _react.default.createElement("span", {
        className: "switch-status switch-status-show"
      }, "on"), _react.default.createElement("span", {
        className: "switch-status switch-status-hide"
      }, "off")));
    }
  }]);

  return Switcher;
}(_react.default.Component);

var _default = Switcher;
exports.default = _default;