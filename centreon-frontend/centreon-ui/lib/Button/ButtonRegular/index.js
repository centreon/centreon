"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

var _IconAction = require("../../Icon/IconAction");

var _IconAction2 = _interopRequireDefault(_IconAction);

require("./button.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var Button = function Button(_ref) {
  var children = _ref.children,
      label = _ref.label,
      onClick = _ref.onClick,
      buttonType = _ref.buttonType,
      color = _ref.color,
      iconActionType = _ref.iconActionType,
      customClass = _ref.customClass,
      style = _ref.style;
  return _react2.default.createElement(
    "button",
    {
      className: "button button-" + buttonType + "-" + color + " linear " + (customClass ? customClass : null),
      onClick: onClick,
      style: style
    },
    iconActionType ? _react2.default.createElement(_IconAction2.default, { iconActionType: iconActionType }) : null,
    label,
    children
  );
};

exports.default = Button;