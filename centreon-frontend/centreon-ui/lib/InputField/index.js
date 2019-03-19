"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.InputField = void 0;

var _react = _interopRequireDefault(require("react"));

require("./input-text.scss");

var _IconInfo = _interopRequireDefault(require("../Icon/IconInfo"));

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _objectWithoutProperties(source, excluded) { if (source == null) return {}; var target = _objectWithoutPropertiesLoose(source, excluded); var key, i; if (Object.getOwnPropertySymbols) { var sourceSymbolKeys = Object.getOwnPropertySymbols(source); for (i = 0; i < sourceSymbolKeys.length; i++) { key = sourceSymbolKeys[i]; if (excluded.indexOf(key) >= 0) continue; if (!Object.prototype.propertyIsEnumerable.call(source, key)) continue; target[key] = source[key]; } } return target; }

function _objectWithoutPropertiesLoose(source, excluded) { if (source == null) return {}; var target = {}; var sourceKeys = Object.keys(source); var key, i; for (i = 0; i < sourceKeys.length; i++) { key = sourceKeys[i]; if (excluded.indexOf(key) >= 0) continue; target[key] = source[key]; } return target; }

var InputField = function InputField(_ref) {
  var type = _ref.type,
      label = _ref.label,
      placeholder = _ref.placeholder,
      topRightLabel = _ref.topRightLabel,
      name = _ref.name,
      inputSize = _ref.inputSize,
      error = _ref.error,
      iconName = _ref.iconName,
      iconColor = _ref.iconColor,
      rest = _objectWithoutProperties(_ref, ["type", "label", "placeholder", "topRightLabel", "name", "inputSize", "error", "iconName", "iconColor"]);

  return _react.default.createElement("div", {
    className: "form-group ".concat(inputSize) + (error ? ' has-danger' : '')
  }, _react.default.createElement("label", {
    htmlFor: rest.id
  }, _react.default.createElement("span", null, iconName ? _react.default.createElement(_IconInfo.default, {
    iconName: iconName,
    iconColor: iconColor
  }) : null, " ", label), _react.default.createElement("span", {
    className: "label-option required"
  }, topRightLabel ? topRightLabel : null)), _react.default.createElement("input", {
    name: name,
    type: type,
    placeholder: placeholder,
    className: "form-control"
  }), error ? _react.default.createElement("div", {
    class: "form-error"
  }, error) : null);
};

exports.InputField = InputField;
var _default = InputField;
exports.default = _default;