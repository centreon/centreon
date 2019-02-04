"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var ContentSliderIndicators = function ContentSliderIndicators(_ref) {
  var images = _ref.images,
      currentIndex = _ref.currentIndex,
      handleDotClick = _ref.handleDotClick;

  return _react2.default.createElement(
    "div",
    { className: "content-slider-indicators" },
    images.map(function (image, i) {
      return _react2.default.createElement("span", {
        className: "" + (i === currentIndex ? "active" : "dot"),
        onClick: handleDotClick,
        "data-index": i,
        key: i
      });
    })
  );
};

exports.default = ContentSliderIndicators;