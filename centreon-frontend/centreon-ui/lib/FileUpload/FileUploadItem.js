"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var FileUploadItem = function (_Component) {
  _inherits(FileUploadItem, _Component);

  function FileUploadItem() {
    _classCallCheck(this, FileUploadItem);

    return _possibleConstructorReturn(this, (FileUploadItem.__proto__ || Object.getPrototypeOf(FileUploadItem)).apply(this, arguments));
  }

  _createClass(FileUploadItem, [{
    key: "render",
    value: function render() {
      var _props = this.props,
          icon = _props.icon,
          iconStatus = _props.iconStatus,
          title = _props.title,
          titleStatus = _props.titleStatus,
          info = _props.info,
          infoStatus = _props.infoStatus,
          infoStatusLabel = _props.infoStatusLabel,
          progressBar = _props.progressBar;

      return _react2.default.createElement(
        _react2.default.Fragment,
        null,
        _react2.default.createElement(
          "div",
          { className: "file-upload-item" },
          _react2.default.createElement("span", { className: "file-upload-item-icon " + icon + " " + iconStatus }),
          _react2.default.createElement(
            "span",
            { className: "file-upload-item-title " + titleStatus },
            title
          ),
          _react2.default.createElement(
            "span",
            { className: "file-upload-item-info " + infoStatus },
            infoStatusLabel,
            info
          ),
          _react2.default.createElement("span", { className: "icon-close icon-close-small" }),
          _react2.default.createElement("span", { className: "file-upload-item-progress " + progressBar })
        )
      );
    }
  }]);

  return FileUploadItem;
}(_react.Component);

exports.default = FileUploadItem;