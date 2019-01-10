"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var TopFilters = function (_Component) {
  _inherits(TopFilters, _Component);

  function TopFilters() {
    _classCallCheck(this, TopFilters);

    return _possibleConstructorReturn(this, (TopFilters.__proto__ || Object.getPrototypeOf(TopFilters)).apply(this, arguments));
  }

  _createClass(TopFilters, [{
    key: "render",
    value: function render() {
      return _react2.default.createElement(
        "div",
        { className: "container container-gray" },
        _react2.default.createElement(
          Centreon.Wrapper,
          null,
          _react2.default.createElement(
            "div",
            { className: "container__row" },
            _react2.default.createElement(
              "div",
              { className: "container__col-md-3 container__col-xs-12" },
              _react2.default.createElement(Centreon.SearchLive, { label: "Search:" })
            ),
            _react2.default.createElement(
              "div",
              { className: "container__col-md-9 container__col-xs-12" },
              _react2.default.createElement(
                "div",
                { className: "container__row" },
                _react2.default.createElement(
                  "div",
                  { className: "container__col-sm-6 container__col-xs-12" },
                  _react2.default.createElement(
                    "div",
                    { className: "container__row" },
                    _react2.default.createElement(Centreon.Switcher, {
                      customClass: "container__col-md-4 container__col-xs-4",
                      switcherTitle: "Status:",
                      switcherStatus: "Not installed" }),
                    _react2.default.createElement(Centreon.Switcher, {
                      customClass: "container__col-md-4 container__col-xs-4",
                      switcherStatus: "Installed" }),
                    _react2.default.createElement(Centreon.Switcher, {
                      customClass: "container__col-md-4 container__col-xs-4",
                      switcherStatus: "Update" })
                  )
                ),
                _react2.default.createElement(
                  "div",
                  { className: "container__col-sm-6 container__col-xs-12" },
                  _react2.default.createElement(
                    "div",
                    { className: "container__row" },
                    _react2.default.createElement(Centreon.Switcher, {
                      customClass: "container__col-sm-3 container__col-xs-4",
                      switcherTitle: "Type:",
                      switcherStatus: "Module" }),
                    _react2.default.createElement(Centreon.Switcher, {
                      customClass: "container__col-sm-3 container__col-xs-4",
                      switcherStatus: "Widget" }),
                    _react2.default.createElement(
                      "div",
                      { className: "container__col-sm-6 container__col-xs-4 center-vertical mt-1" },
                      _react2.default.createElement(Centreon.Button, { label: "Clear Filters", buttonType: "bordered", color: "black" })
                    )
                  )
                )
              )
            )
          )
        )
      );
    }
  }]);

  return TopFilters;
}(_react.Component);

exports.default = TopFilters;