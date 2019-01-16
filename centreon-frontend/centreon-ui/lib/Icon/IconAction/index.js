"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./action-icons.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var IconAction = function IconAction(_ref) {
  var iconActionType = _ref.iconActionType;
  return _react2.default.createElement("span", { className: "icon-action icon-action-" + iconActionType });
};

exports.default = IconAction;