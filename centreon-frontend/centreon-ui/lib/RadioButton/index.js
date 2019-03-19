"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.RadioField = void 0;

var _react = _interopRequireDefault(require("react"));

require("./radio-button.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _objectWithoutProperties(source, excluded) { if (source == null) return {}; var target = _objectWithoutPropertiesLoose(source, excluded); var key, i; if (Object.getOwnPropertySymbols) { var sourceSymbolKeys = Object.getOwnPropertySymbols(source); for (i = 0; i < sourceSymbolKeys.length; i++) { key = sourceSymbolKeys[i]; if (excluded.indexOf(key) >= 0) continue; if (!Object.prototype.propertyIsEnumerable.call(source, key)) continue; target[key] = source[key]; } } return target; }

function _objectWithoutPropertiesLoose(source, excluded) { if (source == null) return {}; var target = {}; var sourceKeys = Object.keys(source); var key, i; for (i = 0; i < sourceKeys.length; i++) { key = sourceKeys[i]; if (excluded.indexOf(key) >= 0) continue; target[key] = source[key]; } return target; }

var RadioField = function RadioField(_ref) {
  var checked = _ref.checked,
      error = _ref.error,
      label = _ref.label,
      info = _ref.info,
      iconColor = _ref.iconColor,
      rest = _objectWithoutProperties(_ref, ["checked", "error", "label", "info", "iconColor"]);

  return _react.default.createElement("div", {
    class: "custom-control custom-radio form-group ".concat(iconColor ? iconColor : '')
  }, _react.default.createElement("input", {
    className: "form-check-input",
    type: "radio",
    "aria-checked": checked,
    checked: checked,
    info: true
  }), _react.default.createElement("label", {
    htmlFor: rest.id,
    className: "custom-control-label"
  }, label, info), error ? _react.default.createElement("div", {
    className: "invalid-feedback"
  }, _react.default.createElement("i", {
    className: "fas fa-exclamation-triangle"
  }), _react.default.createElement("div", {
    className: "field__msg  field__msg--error"
  }, error)) : null);
};

exports.RadioField = RadioField;
var _default = RadioField;
exports.default = _default;