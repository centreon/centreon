"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;

var _react = _interopRequireDefault(require("react"));

require("./info-state-icon.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconInfo = function IconInfo(_ref) {
  var iconName = _ref.iconName,
      iconText = _ref.iconText,
      iconColor = _ref.iconColor;
  return _react.default.createElement(_react.default.Fragment, null, iconName && _react.default.createElement("span", {
    className: "info info-".concat(iconName, " ").concat(iconColor ? iconColor : '')
  }), iconText && _react.default.createElement("span", {
    className: "info-text"
  }, iconText));
};

var _default = IconInfo;
exports.default = _default;