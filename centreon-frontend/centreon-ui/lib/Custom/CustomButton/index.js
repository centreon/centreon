"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;

var _react = _interopRequireDefault(require("react"));

require("./custom-button.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var CustomButton = function CustomButton(_ref) {
  var color = _ref.color,
      label = _ref.label;
  return _react.default.createElement("button", {
    className: "custom-button custom-button-".concat(color)
  }, label);
};

var _default = CustomButton;
exports.default = _default;