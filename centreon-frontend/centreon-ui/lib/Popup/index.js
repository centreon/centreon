"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./popup.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var Popup = function Popup(_ref) {
  var popupType = _ref.popupType,
      children = _ref.children;

  return _react2.default.createElement(
    _react2.default.Fragment,
    null,
    _react2.default.createElement(
      "div",
      { "class": "popup popup-" + popupType },
      _react2.default.createElement(
        "div",
        { "class": "popup-dialog" },
        _react2.default.createElement(
          "div",
          { "class": "popup-content" },
          children
        )
      )
    ),
    _react2.default.createElement("div", { "class": "popup-overlay" })
  );
};

exports.default = Popup;