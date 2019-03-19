"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;

var _react = _interopRequireDefault(require("react"));

require("./custom-subtitles.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var Subtitle = function Subtitle(_ref) {
  var label = _ref.label,
      subtitleType = _ref.subtitleType;
  return _react.default.createElement("h4", {
    className: "custom-subtitle ".concat(subtitleType)
  }, label);
};

var _default = Subtitle;
exports.default = _default;