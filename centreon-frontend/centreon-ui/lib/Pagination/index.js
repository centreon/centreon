"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;

var _react = _interopRequireDefault(require("react"));

var _IconAction = _interopRequireDefault(require("../Icon/IconAction"));

require("./pagination.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var Pagination = function Pagination() {
  return _react.default.createElement("div", {
    className: "pagination"
  }, _react.default.createElement("a", {
    href: "#"
  }, "First"), _react.default.createElement(_IconAction.default, {
    iconActionType: "arrow-right"
  }), _react.default.createElement("a", {
    href: "#"
  }, "1"), _react.default.createElement("a", {
    href: "#",
    className: "active"
  }, "2"), _react.default.createElement("a", {
    href: "#"
  }, "3"), _react.default.createElement(_IconAction.default, {
    iconActionType: "arrow-right"
  }), _react.default.createElement("a", {
    href: "#"
  }, "Last"));
};

var _default = Pagination;
exports.default = _default;