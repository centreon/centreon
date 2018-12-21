"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./search-live.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var SearchLive = function SearchLive(_ref) {
  var label = _ref.label;
  return _react2.default.createElement(
    "div",
    { className: "search-live" },
    _react2.default.createElement(
      "label",
      { "for": "search-live" },
      label
    ),
    _react2.default.createElement("input", { type: "text", id: "search-live", name: "search-live" })
  );
};

exports.default = SearchLive;