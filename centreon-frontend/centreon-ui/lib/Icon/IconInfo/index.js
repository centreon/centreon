"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./info-state-icon.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconInfo = function IconInfo(_ref) {
  var iconName = _ref.iconName;
  return _react2.default.createElement("span", { "class": "info info-" + iconName });
};

exports.default = IconInfo;