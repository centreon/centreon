"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

var _ContentSliderItem = require("./ContentSliderItem");

var _ContentSliderItem2 = _interopRequireDefault(_ContentSliderItem);

require("./content-slider.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _objectDestructuringEmpty(obj) { if (obj == null) throw new TypeError("Cannot destructure undefined"); }

var SliderContent = function SliderContent(_ref) {
  _objectDestructuringEmpty(_ref);

  return _react2.default.createElement(
    "div",
    { className: "content-slider" },
    _react2.default.createElement(
      "div",
      { className: "content-slider-indicators" },
      _react2.default.createElement("span", null),
      _react2.default.createElement("span", { className: "active" }),
      _react2.default.createElement("span", null)
    ),
    _react2.default.createElement(
      "div",
      { className: "content-slider-items" },
      _react2.default.createElement(_ContentSliderItem2.default, { sliderImage: "https://static.centreon.com/wp-content/uploads/2018/09/plugin-banner-it-operations-management.png" })
    ),
    _react2.default.createElement(
      "div",
      { className: "content-slider-controls" },
      _react2.default.createElement(
        "span",
        { className: "content-slider-prev" },
        _react2.default.createElement("span", { className: "content-slider-prev-icon" })
      ),
      _react2.default.createElement(
        "span",
        { className: "content-slider-next" },
        _react2.default.createElement("span", { className: "content-slider-next-icon" })
      )
    )
  );
};

exports.default = SliderContent;