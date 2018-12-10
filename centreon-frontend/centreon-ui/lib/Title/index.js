"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./custom-title.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var Title = function Title(_ref) {
  var icon = _ref.icon,
      label = _ref.label;
  return _react2.default.createElement(
    "h2",
    { "class": "custom-title" },
    icon ? _react2.default.createElement("span", { "class": "custom-title-icon custom-title-icon-" + icon }) : null,
    label
  );
};

exports.default = Title;