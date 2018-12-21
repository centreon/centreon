"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

var _ButtonRegular = require("../Button/ButtonRegular");

var _ButtonRegular2 = _interopRequireDefault(_ButtonRegular);

var _Popup = require("../Popup");

var _Popup2 = _interopRequireDefault(_Popup);

var _FileUploadItem = require("./FileUploadItem");

var _FileUploadItem2 = _interopRequireDefault(_FileUploadItem);

require("./file-upload.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var FileUpload = function FileUpload() {
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
            "div",
            { className: "container__col-xs-6 text-right" },
            _react2.default.createElement(_ButtonRegular2.default, { buttonType: "bordered", color: "white", label: "BROWSE" })
          )
        ),
        _react2.default.createElement("span", { className: "icon-close icon-close-middle" })
      ),
      _react2.default.createElement(
        "div",
        { className: "popup-body" },
        _react2.default.createElement(
          "div",
          { className: "file file-upload" },
          _react2.default.createElement(
            "div",
            { className: "file-upload-items" },
            _react2.default.createElement(_FileUploadItem2.default, {
              icon: "file",
              iconStatus: "success",
              title: "file-1.licence",
              titleStatus: "success",
              info: "0.3mb",
              progressBar: "success"
            }),
            _react2.default.createElement(_FileUploadItem2.default, {
              icon: "file",
              iconStatus: "success",
              title: "file-1.licence",
              titleStatus: "success",
              info: "0.3mb",
              progressBar: "success"
            }),
            _react2.default.createElement(_FileUploadItem2.default, {
              icon: "zip",
              iconStatus: "success",
              title: "file-1.licence",
              titleStatus: "success",
              info: "0.3mb",
              progressBar: "success"
            }),
            _react2.default.createElement(_FileUploadItem2.default, {
              icon: "file",
              iconStatus: "error",
              titleStatus: "error",
              title: "file-1.licence",
              infoStatus: "error",
              infoStatusLabel: "upload failed",
              progressBar: "error"
            }),
            _react2.default.createElement(_FileUploadItem2.default, {
              icon: "file",
              iconStatus: "warning",
              title: "file-1.licence",
              titleStatus: "warning",
              progressBar: "warning"
            })
          )
        )
      )
    )
  );
};

exports.default = FileUpload;