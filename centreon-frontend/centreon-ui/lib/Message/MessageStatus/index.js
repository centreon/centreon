'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

require('./message-status.scss');

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var ContentMessage = function ContentMessage(_ref) {
  var messageStatus = _ref.messageStatus,
      messageText = _ref.messageText,
      messageInfo = _ref.messageInfo;

  return _react2.default.createElement(
    'span',
    { className: 'message-status ' + messageStatus },
    messageText,
    _react2.default.createElement(
      'span',
      { className: 'message-status-info' },
      messageInfo
    )
  );
};

exports.default = ContentMessage;