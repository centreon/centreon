"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./content-description.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var DescriptionContent = function DescriptionContent(_ref) {
  var date = _ref.date,
      title = _ref.title,
      text = _ref.text,
      note = _ref.note;
  return _react2.default.createElement(
    _react2.default.Fragment,
    null,
    date ? _react2.default.createElement(
      "span",
      { className: "content-description-date" },
      date
    ) : null,
    title ? _react2.default.createElement(
      "h3",
      { className: "content-description-title" },
      title
    ) : null,
    text ? _react2.default.createElement(
      "p",
      { className: "content-description-text" },
      text
    ) : null,
    note ? _react2.default.createElement(
      "span",
      { className: "content-description-release-note" },
      note
    ) : null
  );
};

exports.default = DescriptionContent;