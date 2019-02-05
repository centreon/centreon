"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var ContentSliderRightArrow = function ContentSliderRightArrow(_ref) {
  var goToNextSlide = _ref.goToNextSlide,
      iconColor = _ref.iconColor;

  return _react2.default.createElement(
    "span",
    { className: "content-slider-next", onClick: goToNextSlide },
    _react2.default.createElement("span", { className: "content-slider-next-icon " + (iconColor ? iconColor : '') })
  );
};

exports.default = ContentSliderRightArrow;