"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.RadioField = undefined;

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./radio-button.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _objectWithoutProperties(obj, keys) { var target = {}; for (var i in obj) { if (keys.indexOf(i) >= 0) continue; if (!Object.prototype.hasOwnProperty.call(obj, i)) continue; target[i] = obj[i]; } return target; }

var RadioField = function RadioField(_ref) {
  var checked = _ref.checked,
      error = _ref.error,
      label = _ref.label,
      info = _ref.info,
      rest = _objectWithoutProperties(_ref, ["checked", "error", "label", "info"]);

  return _react2.default.createElement(
    "div",
    { "class": "custom-control custom-radio form-group" },
    _react2.default.createElement("input", {
      className: "form-check-input",
      type: "radio",
      "aria-checked": checked,
      checked: checked,
      info: true
    }),
    _react2.default.createElement(
      "label",
      { htmlFor: rest.id, className: "custom-control-label" },
      label,
      info
    ),
    error ? _react2.default.createElement(
      "div",
      { className: "invalid-feedback" },
      _react2.default.createElement("i", { className: "fas fa-exclamation-triangle" }),
      _react2.default.createElement(
        "div",
        { className: "field__msg  field__msg--error" },
        error
      )
    ) : null
  );
};

exports.RadioField = RadioField;
exports.default = RadioField;