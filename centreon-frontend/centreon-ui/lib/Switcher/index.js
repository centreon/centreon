"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./switcher.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var Switcher = function Switcher(_ref) {
  var switcherTitle = _ref.switcherTitle,
      switcherStatus = _ref.switcherStatus;
  return _react2.default.createElement(
    "div",
    { "class": "switcher" },
    _react2.default.createElement(
      "span",
      { "class": "switcher-title" },
      switcherTitle
    ),
    _react2.default.createElement(
      "span",
      { "class": "switcher-status" },
      switcherStatus
    ),
    _react2.default.createElement(
      "label",
      { "class": "switch" },
      _react2.default.createElement("input", { type: "checkbox" }),
      _react2.default.createElement("span", { "class": "switch-slider switch-round" })
    )
  );
};

exports.default = Switcher;