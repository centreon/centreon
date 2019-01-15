"use strict";

Object.defineProperty(exports, "__esModule", {
    value: true
});

var _extends = Object.assign || function (target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i]; for (var key in source) { if (Object.prototype.hasOwnProperty.call(source, key)) { target[key] = source[key]; } } } return target; };

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

var ExtensionsHolder = function (_React$Component) {
    _inherits(ExtensionsHolder, _React$Component);

    function ExtensionsHolder() {
        _classCallCheck(this, ExtensionsHolder);

        return _possibleConstructorReturn(this, (ExtensionsHolder.__proto__ || Object.getPrototypeOf(ExtensionsHolder)).apply(this, arguments));
    }

    _createClass(ExtensionsHolder, [{
        key: "render",
        value: function render() {
            var _props = this.props,
                title = _props.title,
                entities = _props.entities;

            return _react2.default.createElement(
                Centreon.Wrapper,
                null,
                _react2.default.createElement(Centreon.HorizontalLineContent, { hrTitle: title }),
                _react2.default.createElement(
                    Centreon.Card,
                    null,
                    _react2.default.createElement(
                        "div",
                        { className: "container__row" },
                        entities.map(function (entity) {
                            return _react2.default.createElement(
                                "div",
                                { className: "container__col-md-3 container__col-sm-6 container__col-xs-12" },
                                _react2.default.createElement(
                                    Centreon.CardItem,
                                    _extends({
                                        itemBorderColor: entity.installed ? entity.licence && entity.licence != 'N/A' ? "green" : "orange" : "gray"
                                    }, entity.licence && entity.licence != 'N/A' ? { itemFooterColor: 'red' } : {}, entity.licence && entity.licence != 'N/A' ? { itemFooterLabel: entity.licence } : {}),
                                    _react2.default.createElement(Centreon.IconInfo, { iconName: "state" }),
                                    _react2.default.createElement(
                                        "div",
                                        { className: "custom-title-heading" },
                                        _react2.default.createElement(Centreon.Title, { icon: "object", label: entity.description }),
                                        _react2.default.createElement(Centreon.Subtitle, { label: "by " + entity.label })
                                    ),
                                    _react2.default.createElement(
                                        Centreon.Button,
                                        _extends({
                                            buttonType: entity.version.outdated ? "regular" : "bordered",
                                            color: entity.version.outdated ? "orange" : "blue",
                                            label: "Available " + entity.version.available
                                        }, entity.version.outdated ? { iconActionType: "update" } : {}),
                                        entity.installed === false ? _react2.default.createElement(Centreon.IconContent, { iconContentType: "add" }) : null
                                    ),
                                    entity.installed ? _react2.default.createElement(Centreon.ButtonAction, { buttonActionType: "delete", buttonIconType: "delete" }) : null
                                )
                            );
                        })
                    )
                )
            );
        }
    }]);

    return ExtensionsHolder;
}(_react2.default.Component);

exports.default = ExtensionsHolder;