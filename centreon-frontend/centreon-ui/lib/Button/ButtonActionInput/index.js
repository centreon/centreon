"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

var _IconAction = require("../../Icon/IconAction");

var _IconAction2 = _interopRequireDefault(_IconAction);

require("./button-action-input.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var ButtonActionInput = function ButtonActionInput(_ref) {
  var buttonActionType = _ref.buttonActionType,
      buttonIconType = _ref.buttonIconType,
      onClick = _ref.onClick,
      buttonColor = _ref.buttonColor,
      iconColor = _ref.iconColor;
  return _react2.default.createElement(
    "span",
    {
      className: "button-action-input button-action-input-" + buttonActionType + " " + buttonColor,
      onClick: onClick
    },
    _react2.default.createElement(_IconAction2.default, { iconColor: iconColor ? iconColor : '', iconActionType: buttonIconType })
  );
};

exports.default = ButtonActionInput;