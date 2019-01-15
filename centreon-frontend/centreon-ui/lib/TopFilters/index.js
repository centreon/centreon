'use strict';

Object.defineProperty(exports, "__esModule", {
    value: true
});

var _extends = Object.assign || function (target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i]; for (var key in source) { if (Object.prototype.hasOwnProperty.call(source, key)) { target[key] = source[key]; } } } return target; };

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

var _index = require('../index');

var Centreon = _interopRequireWildcard(_index);

function _interopRequireWildcard(obj) { if (obj && obj.__esModule) { return obj; } else { var newObj = {}; if (obj != null) { for (var key in obj) { if (Object.prototype.hasOwnProperty.call(obj, key)) newObj[key] = obj[key]; } } newObj.default = obj; return newObj; } }

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var TopFilters = function (_Component) {
    _inherits(TopFilters, _Component);

    function TopFilters() {
        _classCallCheck(this, TopFilters);

        return _possibleConstructorReturn(this, (TopFilters.__proto__ || Object.getPrototypeOf(TopFilters)).apply(this, arguments));
    }

    _createClass(TopFilters, [{
        key: 'render',
        value: function render() {
            var _props = this.props,
                fullText = _props.fullText,
                switchers = _props.switchers,
                onChange = _props.onChange;


            return _react2.default.createElement(
                'div',
                { className: 'container container-gray' },
                _react2.default.createElement(
                    Centreon.Wrapper,
                    null,
                    _react2.default.createElement(
                        'div',
                        { className: 'container__row' },
                        fullText ? _react2.default.createElement(
                            'div',
                            { className: 'container__col-md-3 container__col-xs-12' },
                            _react2.default.createElement(Centreon.SearchLive, { onChange: onChange, label: fullText.label, value: fullText.value, filterKey: fullText.filterKey })
                        ) : null,
                        _react2.default.createElement(
                            'div',
                            { className: 'container__col-md-9 container__col-xs-12' },
                            _react2.default.createElement(
                                'div',
                                { className: 'container__row' },
                                switchers ? switchers.map(function (switcherColumn, index) {
                                    return _react2.default.createElement(
                                        'div',
                                        { key: 'switcherColumn' + index, className: 'container__col-sm-6 container__col-xs-12' },
                                        _react2.default.createElement(
                                            'div',
                                            { key: 'switcherSubColumn' + index, className: 'container__row' },
                                            switcherColumn.map(function (_ref, i) {
                                                var customClass = _ref.customClass,
                                                    switcherTitle = _ref.switcherTitle,
                                                    switcherStatus = _ref.switcherStatus,
                                                    button = _ref.button,
                                                    label = _ref.label,
                                                    buttonType = _ref.buttonType,
                                                    color = _ref.color,
                                                    onClick = _ref.onClick,
                                                    filterKey = _ref.filterKey,
                                                    value = _ref.value;
                                                return !button ? _react2.default.createElement(Centreon.Switcher, _extends({
                                                    key: 'switcher' + index + i,
                                                    customClass: customClass
                                                }, switcherTitle ? { switcherTitle: switcherTitle } : {}, {
                                                    switcherStatus: switcherStatus,
                                                    filterKey: filterKey,
                                                    onChange: onChange,
                                                    value: value
                                                })) : _react2.default.createElement(
                                                    'div',
                                                    {
                                                        key: 'switcher' + index + i,
                                                        className: 'container__col-sm-6 container__col-xs-4 center-vertical mt-1' },
                                                    _react2.default.createElement(Centreon.Button, {
                                                        key: 'switcherButton' + index + i,
                                                        label: label,
                                                        buttonType: buttonType,
                                                        color: color,
                                                        onClick: onClick })
                                                );
                                            })
                                        )
                                    );
                                }) : null
                            )
                        )
                    )
                )
            );
        }
    }]);

    return TopFilters;
}(_react.Component);

exports.default = TopFilters;