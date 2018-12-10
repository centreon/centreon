"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./content-horizontal-line.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var HorizontalLineContent = function HorizontalLineContent(_ref) {
  var hrTitle = _ref.hrTitle;
  return _react2.default.createElement(
    "div",
    { "class": "content-hr" },
    _react2.default.createElement(
      "span",
      { "class": "content-hr-title" },
      hrTitle
    )
  );
};

exports.default = HorizontalLineContent;