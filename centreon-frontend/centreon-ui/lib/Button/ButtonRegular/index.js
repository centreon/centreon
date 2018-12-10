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
  var label = _ref.label,
      onClick = _ref.onClick,
      buttonType = _ref.buttonType,
      color = _ref.color,
      iconActionType = _ref.iconActionType;
  return _react2.default.createElement(
    "button",
    {
      className: "button button-" + buttonType + "-" + color + " linear",
      onClick: onClick
    },
    iconActionType ? _react2.default.createElement(_IconAction2.default, { iconActionType: iconActionType }) : null,
    label
  );
};

exports.default = Button;