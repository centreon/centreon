"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.Checkbox = undefined;

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./checkbox.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _objectWithoutProperties(obj, keys) { var target = {}; for (var i in obj) { if (keys.indexOf(i) >= 0) continue; if (!Object.prototype.hasOwnProperty.call(obj, i)) continue; target[i] = obj[i]; } return target; }

var Checkbox = function Checkbox(_ref) {
  var iconColor = _ref.iconColor,
      checked = _ref.checked,
      label = _ref.label,
      value = _ref.value,
      info = _ref.info,
      name = _ref.name,
      rest = _objectWithoutProperties(_ref, ["iconColor", "checked", "label", "value", "info", "name"]);

  return _react2.default.createElement(
    "div",
    { className: "form-group" },
    _react2.default.createElement(
      "div",
      { className: "custom-control custom-checkbox " + (iconColor ? iconColor : '') },
      _react2.default.createElement("input", {
        name: name,
        "aria-checked": checked,
        checked: checked,
        className: "custom-control-input",
        type: "checkbox"
      }),
      _react2.default.createElement(
        "label",
        { htmlFor: rest.id, className: "custom-control-label" },
        label,
        info
      )
    )
  );
};

exports.Checkbox = Checkbox;
exports.default = Checkbox;