"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var ContentSliderLeftArrow = function ContentSliderLeftArrow(_ref) {
  var goToPrevSlide = _ref.goToPrevSlide;

  return _react2.default.createElement(
    "span",
    { className: "content-slider-prev", onClick: goToPrevSlide },
    _react2.default.createElement("span", { className: "content-slider-prev-icon" })
  );
};

exports.default = ContentSliderLeftArrow;