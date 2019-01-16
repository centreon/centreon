"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./custom-subtitles.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var Subtitle = function Subtitle(_ref) {
  var label = _ref.label;
  return _react2.default.createElement(
    "h4",
    { className: "custom-subtitle" },
    label
  );
};

exports.default = Subtitle;