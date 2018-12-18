"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconBordered = function IconBordered(_ref) {
  var iconColor = _ref.iconColor,
      iconType = _ref.iconType,
      iconNumber = _ref.iconNumber;

  return _react2.default.createElement(
    "a",
    { "class": "icons-" + iconType + " " + iconColor },
    _react2.default.createElement(
      "span",
      { className: "number" },
      _react2.default.createElement(
        "span",
        { className: "icons-number" },
        iconNumber
      )
    )
  );
};

exports.default = IconBordered;