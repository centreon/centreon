"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./icon-header.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconHeader = function IconHeader(_ref) {
  var iconType = _ref.iconType,
      iconName = _ref.iconName,
      style = _ref.style,
      onClick = _ref.onClick;

  return _react2.default.createElement(
    "span",
    { className: "icons-wrap", style: style },
    _react2.default.createElement("span", { onClick: onClick, className: "iconmoon icon-" + iconType }),
    _react2.default.createElement(
      "span",
      { className: "icon__name" },
      iconName
    )
  );
};

exports.default = IconHeader;