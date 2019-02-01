"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./message-info.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var MessageInfo = function MessageInfo(_ref) {
  var messageInfo = _ref.messageInfo,
      text = _ref.text;

  return _react2.default.createElement(
    "span",
    { "class": "message-info " + messageInfo },
    text
  );
};

exports.default = MessageInfo;