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

var ExtensionDeletePopup = function (_React$Component) {
    _inherits(ExtensionDeletePopup, _React$Component);

    function ExtensionDeletePopup() {
        _classCallCheck(this, ExtensionDeletePopup);

        return _possibleConstructorReturn(this, (ExtensionDeletePopup.__proto__ || Object.getPrototypeOf(ExtensionDeletePopup)).apply(this, arguments));
    }

    _createClass(ExtensionDeletePopup, [{
        key: "render",
        value: function render() {
            var _props = this.props,
                deletingEntity = _props.deletingEntity,
                onConfirm = _props.onConfirm,
                onCancel = _props.onCancel;


            return _react2.default.createElement(
                Centreon.Popup,
                { popupType: "small" },
                _react2.default.createElement(
                    "div",
                    { "class": "popup-header" },
                    _react2.default.createElement(Centreon.Title, { label: deletingEntity.description, icon: "object" })
                ),
                _react2.default.createElement(
                    "div",
                    { "class": "popup-body" },
                    _react2.default.createElement(Centreon.MessageInfo, { messageInfo: "red", text: "Do you want to delete this extension. This, action will remove all associated data." })
                ),
                _react2.default.createElement(
                    "div",
                    { className: "popup-footer" },
                    _react2.default.createElement(
                        "div",
                        { "class": "container__row" },
                        _react2.default.createElement(
                            "div",
                            { "class": "container__col-xs-6" },
                            _react2.default.createElement(Centreon.Button, {
                                label: "Delete",
                                buttonType: "regular",
                                color: "red",
                                iconActionType: "delete-white",
                                onClick: function onClick(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    onConfirm(deletingEntity.id);
                                }
                            })
                        ),
                        _react2.default.createElement(
                            "div",
                            { "class": "container__col-xs-6 text-right" },
                            _react2.default.createElement(Centreon.Button, {
                                label: "Cancel",
                                buttonType: "regular",
                                color: "gray",
                                onClick: function onClick(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    onCancel();
                                }
                            })
                        )
                    )
                ),
                _react2.default.createElement(Centreon.IconClose, {
                    iconType: "middle",
                    onClick: function onClick(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        onCancel();
                    } })
            );
        }
    }]);

    return ExtensionDeletePopup;
}(_react2.default.Component);

exports.default = ExtensionDeletePopup;