"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./content-icons.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconContent = function IconContent(_ref) {
  var iconContentType = _ref.iconContentType,
      iconContentColor = _ref.iconContentColor,
      loading = _ref.loading;
  return _react2.default.createElement("span", {
    style: loading ? { top: "20%" } : {},
    className: "content-icon content-icon-" + iconContentType + " " + (iconContentColor ? "content-icon-" + iconContentType + "-" + iconContentColor : "") + " " + (loading ? "loading-animation" : "")
  });
};

exports.default = IconContent;