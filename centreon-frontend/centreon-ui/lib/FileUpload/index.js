"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

var _ButtonRegular = require("../Button/ButtonRegular");

var _ButtonRegular2 = _interopRequireDefault(_ButtonRegular);

var _Popup = require("../Popup");

var _Popup2 = _interopRequireDefault(_Popup);

var _FileUploadItem = require("./FileUploadItem");

var _FileUploadItem2 = _interopRequireDefault(_FileUploadItem);

var _FileUploadProgress = require("./FileUploadProgress");

var _FileUploadProgress2 = _interopRequireDefault(_FileUploadProgress);

require("./file-upload.scss");

var _reactFiles = require("react-files");

var _reactFiles2 = _interopRequireDefault(_reactFiles);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var FileUpload = function (_Component) {
  _inherits(FileUpload, _Component);

  function FileUpload() {
    var _ref;

    var _temp, _this, _ret;

    _classCallCheck(this, FileUpload);

    for (var _len = arguments.length, args = Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }

    return _ret = (_temp = (_this = _possibleConstructorReturn(this, (_ref = FileUpload.__proto__ || Object.getPrototypeOf(FileUpload)).call.apply(_ref, [this].concat(args))), _this), _this.state = {
      uploading: false,
      files: []
    }, _this.onFilesChange = function (files) {
      _this.setState({
        files: files
      });
    }, _this.onFilesError = function (error, file) {
      console.log('error code ' + error.code + ': ' + error.message);
    }, _this.onRemoveFile = function (idx) {
      var files = _this.state.files;

      files.splice(idx, 1);
      _this.setState({
        files: files
      });
    }, _temp), _possibleConstructorReturn(_this, _ret);
  }

  _createClass(FileUpload, [{
    key: "render",
    value: function render() {
      var _this2 = this;

      var _state = this.state,
          files = _state.files,
          uploading = _state.uploading;
      var onClose = this.props.onClose;

      return _react2.default.createElement(
        _react2.default.Fragment,
        null,
        _react2.default.createElement(
          _Popup2.default,
          { popupType: "small" },
          _react2.default.createElement(
            "div",
            { className: "popup-header blue-background-decorator" },
            _react2.default.createElement(
              "div",
              { className: "container__row" },
              _react2.default.createElement(
                "div",
                { className: "container__col-xs-6 center-vertical" },
                _react2.default.createElement(
                  "div",
                  { className: "file file-upload" },
                  _react2.default.createElement(
                    "span",
                    { className: "file-upload-title" },
                    _react2.default.createElement("span", { className: "file-upload-icon" }),
                    "File Upload"
                  )
                )
              ),
              _react2.default.createElement(
                _reactFiles2.default,
                {
                  onChange: this.onFilesChange,
                  onError: this.onFilesError,
                  accepts: ['.zip', '.license'],
                  multiple: true,
                  maxFiles: 5,
                  maxFileSize: 1048576,
                  minFileSize: 0,
                  clickable: true
                },
                _react2.default.createElement(
                  "div",
                  { className: "container__col-xs-6 text-right" },
                  _react2.default.createElement(_ButtonRegular2.default, { buttonType: "bordered", color: "white", label: "BROWSE" })
                )
              )
            ),
            _react2.default.createElement("span", { className: "icon-close icon-close-middle", onClick: onClose })
          ),
          files.length > 0 ? _react2.default.createElement(
            "div",
            { className: "popup-body" },
            _react2.default.createElement(
              "div",
              { className: "file file-upload" },
              _react2.default.createElement(
                "div",
                { className: "file-upload-items" },
                files.map(function (file, idx) {
                  return _react2.default.createElement(_FileUploadItem2.default, {
                    icon: "file",
                    iconStatus: "warning",
                    title: file.name,
                    titleStatus: "warning",
                    info: file.sizeReadable,
                    onDeleteFile: function onDeleteFile() {
                      _this2.onRemoveFile(idx);
                    },
                    uploading: uploading
                  });
                }),
                uploading ? _react2.default.createElement(_FileUploadProgress2.default, {
                  title: "Progress",
                  titleStatus: "percentage",
                  progressBar: "percentage",
                  uploadedPercentage: "70" }) : null
              )
            )
          ) : null
        )
      );
    }
  }]);

  return FileUpload;
}(_react.Component);

;

exports.default = FileUpload;