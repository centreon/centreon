"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _extends = Object.assign || function (target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i]; for (var key in source) { if (Object.prototype.hasOwnProperty.call(source, key)) { target[key] = source[key]; } } } return target; };

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./icon-number.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconNumber = function IconNumber(_ref) {
  var iconColor = _ref.iconColor,
      iconType = _ref.iconType,
      iconNumber = _ref.iconNumber,
      iconLink = _ref.iconLink;

  return _react2.default.createElement(
    "a",
    _extends({
      className: "icons icons-number " + iconType + " " + iconColor
    }, iconLink && { href: iconLink }),
    _react2.default.createElement(
      "span",
      { className: "number-wrap" },
      _react2.default.createElement(
        "span",
        { className: "number-count" },
        iconNumber
      )
    )
  );
};

exports.default = IconNumber;