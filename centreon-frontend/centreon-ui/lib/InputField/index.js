"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.InputField = undefined;

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./input-text.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _objectWithoutProperties(obj, keys) { var target = {}; for (var i in obj) { if (keys.indexOf(i) >= 0) continue; if (!Object.prototype.hasOwnProperty.call(obj, i)) continue; target[i] = obj[i]; } return target; }

var InputField = function InputField(_ref) {
  var type = _ref.type,
      label = _ref.label,
      placeholder = _ref.placeholder,
      topRightLabel = _ref.topRightLabel,
      name = _ref.name,
      rest = _objectWithoutProperties(_ref, ["type", "label", "placeholder", "topRightLabel", "name"]);

  return _react2.default.createElement(
    "div",
    { className: "form-group", style: { width: '200px' } },
    _react2.default.createElement(
      "label",
      { htmlFor: rest.id },
      _react2.default.createElement(
        "span",
        null,
        label
      ),
      _react2.default.createElement(
        "span",
        { className: "label-option required" },
        topRightLabel ? topRightLabel : null
      )
    ),
    _react2.default.createElement("input", {
      name: name,
      type: type,
      placeholder: placeholder,
      className: "form-control"
    })
  );
};

exports.InputField = InputField;
exports.default = InputField;