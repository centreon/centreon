"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./custom-title.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var Title = function Title(_ref) {
  var icon = _ref.icon,
      label = _ref.label,
      titleColor = _ref.titleColor,
      onClick = _ref.onClick;
  return _react2.default.createElement(
    "h2",
    { className: "custom-title",
      onClick: onClick
    },
    icon ? _react2.default.createElement("span", { className: "custom-title-icon custom-title-icon-" + icon }) : null,
    _react2.default.createElement(
      "span",
      { className: "custom-title-label " + (titleColor ? titleColor : '') },
      label
    )
  );
};

exports.default = Title;