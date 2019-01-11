"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./search-live.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var SearchLive = function (_React$Component) {
  _inherits(SearchLive, _React$Component);

  function SearchLive() {
    var _ref;

    var _temp, _this, _ret;

    _classCallCheck(this, SearchLive);

    for (var _len = arguments.length, args = Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }

    return _ret = (_temp = (_this = _possibleConstructorReturn(this, (_ref = SearchLive.__proto__ || Object.getPrototypeOf(SearchLive)).call.apply(_ref, [this].concat(args))), _this), _this.onChange = function (e) {
      var _this$props = _this.props,
          onChange = _this$props.onChange,
          filterKey = _this$props.filterKey;

      onChange(e.target.value, filterKey);
    }, _temp), _possibleConstructorReturn(_this, _ret);
  }

  _createClass(SearchLive, [{
    key: "render",
    value: function render() {
      var _props = this.props,
          label = _props.label,
          value = _props.value;


      return _react2.default.createElement(
        "div",
        { className: "search-live" },
        _react2.default.createElement(
          "label",
          null,
          label
        ),
        _react2.default.createElement("input", { type: "text", value: value, onChange: this.onChange.bind(this) })
      );
    }
  }]);

  return SearchLive;
}(_react2.default.Component);

exports.default = SearchLive;