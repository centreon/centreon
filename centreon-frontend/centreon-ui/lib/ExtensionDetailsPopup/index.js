"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

var _index = require("../index");

var Centreon = _interopRequireWildcard(_index);

function _interopRequireWildcard(obj) { if (obj && obj.__esModule) { return obj; } else { var newObj = {}; if (obj != null) { for (var key in obj) { if (Object.prototype.hasOwnProperty.call(obj, key)) newObj[key] = obj[key]; } } newObj.default = obj; return newObj; } }

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var ExtensionDetailPopup = function (_React$Component) {
  _inherits(ExtensionDetailPopup, _React$Component);

  function ExtensionDetailPopup() {
    _classCallCheck(this, ExtensionDetailPopup);

    return _possibleConstructorReturn(this, (ExtensionDetailPopup.__proto__ || Object.getPrototypeOf(ExtensionDetailPopup)).apply(this, arguments));
  }

  _createClass(ExtensionDetailPopup, [{
    key: "render",
    value: function render() {
      var _props = this.props,
          onCloseClicked = _props.onCloseClicked,
          modalDetails = _props.modalDetails,
          onVersionClicked = _props.onVersionClicked;

      if (modalDetails === null) {
        return null;
      }
      return _react2.default.createElement(
        Centreon.Popup,
        { popupType: "big" },
        _react2.default.createElement(Centreon.Slider, null),
        _react2.default.createElement(
          "div",
          { "class": "popup-header" },
          _react2.default.createElement(Centreon.Title, { label: modalDetails.title }),
          _react2.default.createElement(Centreon.Subtitle, { label: modalDetails.label }),
          _react2.default.createElement(Centreon.Button, {
            onClick: function onClick() {
              onVersionClicked(modalDetails.id);
            },
            label: "Available " + modalDetails.version.available,
            buttonType: "regular",
            color: "blue"
          }),
          _react2.default.createElement(Centreon.Button, {
            label: modalDetails.stability,
            buttonType: "bordered",
            color: "gray",
            style: { margin: "15px" }
          }),
          _react2.default.createElement(Centreon.Button, {
            label: modalDetails.license,
            buttonType: "bordered",
            color: "orange"
          })
        ),
        _react2.default.createElement(Centreon.HorizontalLine, null),
        _react2.default.createElement(
          "div",
          { "class": "popup-body" },
          _react2.default.createElement(Centreon.Description, {
            date: "Last update " + modalDetails.last_update
          }),
          _react2.default.createElement(Centreon.Description, { title: "Description:" }),
          _react2.default.createElement(Centreon.Description, { text: modalDetails.description })
        ),
        _react2.default.createElement(Centreon.HorizontalLine, null),
        _react2.default.createElement(
          "div",
          { className: "popup-footer" },
          _react2.default.createElement(Centreon.Description, { note: modalDetails.release_note })
        ),
        _react2.default.createElement(Centreon.IconClose, { iconType: "big", onClick: onCloseClicked })
      );
    }
  }]);

  return ExtensionDetailPopup;
}(_react2.default.Component);

exports.default = ExtensionDetailPopup;