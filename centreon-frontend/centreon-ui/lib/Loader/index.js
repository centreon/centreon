"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./loader-additions.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

exports.default = function (_ref) {
  var fullContent = _ref.fullContent;
  return _react2.default.createElement(
    "div",
    { className: "loader " + (fullContent ? 'full-relative-content' : '') },
    _react2.default.createElement(
      "div",
      { className: "loader-inner ball-grid-pulse" },
      _react2.default.createElement("div", null),
      _react2.default.createElement("div", null),
      _react2.default.createElement("div", null),
      _react2.default.createElement("div", null)
    )
  );
};