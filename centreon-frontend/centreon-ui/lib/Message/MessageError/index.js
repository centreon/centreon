"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./message-error.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var MessageError = function MessageError(_ref) {
  var messageError = _ref.messageError,
      text = _ref.text;

  return _react2.default.createElement(
    "span",
    { "class": "message-error " + messageError },
    text
  );
};

exports.default = MessageError;