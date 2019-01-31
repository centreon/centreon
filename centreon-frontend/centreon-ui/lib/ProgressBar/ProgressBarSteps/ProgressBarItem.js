"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var ProgressBarItem = function ProgressBarItem(_ref) {
  var classActive = _ref.classActive,
      number = _ref.number;

  return _react2.default.createElement(
    "li",
    { className: "progress-bar-item" },
    _react2.default.createElement(
      "span",
      { className: "progress-bar-link " + classActive },
      number
    )
  );
};

exports.default = ProgressBarItem;