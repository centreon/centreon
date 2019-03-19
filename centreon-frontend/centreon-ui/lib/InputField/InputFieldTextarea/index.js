"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;

var _react = _interopRequireDefault(require("react"));

require("./textarea.scss");

var _IconInfo = _interopRequireDefault(require("../../Icon/IconInfo"));

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var InputFieldTextarea = function InputFieldTextarea(_ref) {
  var error = _ref.error,
      label = _ref.label,
      textareaType = _ref.textareaType,
      iconName = _ref.iconName,
      iconColor = _ref.iconColor;
  return _react.default.createElement("div", {
    className: "form-group textarea ".concat(textareaType) + (error ? ' has-danger' : '')
  }, _react.default.createElement("label", null, iconName ? _react.default.createElement(_IconInfo.default, {
    iconName: iconName,
    iconColor: iconColor
  }) : null, " ", label, " "), _react.default.createElement("textarea", {
    className: "form-control",
    rows: "3"
  }), error ? _react.default.createElement("div", {
    className: "form-error"
  }, error) : null);
};

var _default = InputFieldTextarea;
exports.default = _default;