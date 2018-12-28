'use strict';

Object.defineProperty(exports, "__esModule", {
    value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require('react');

var _react2 = _interopRequireDefault(_react);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var DynamicComponentLoader = function (_Component) {
    _inherits(DynamicComponentLoader, _Component);

    function DynamicComponentLoader() {
        var _ref;

        var _temp, _this, _ret;

        _classCallCheck(this, DynamicComponentLoader);

        for (var _len = arguments.length, args = Array(_len), _key = 0; _key < _len; _key++) {
            args[_key] = arguments[_key];
        }

        return _ret = (_temp = (_this = _possibleConstructorReturn(this, (_ref = DynamicComponentLoader.__proto__ || Object.getPrototypeOf(DynamicComponentLoader)).call.apply(_ref, [this].concat(args))), _this), _this.state = {
            componentLoaded: false
        }, _this.componentWillReceiveProps = function (nextProps) {
            var componentName = nextProps.componentName;

            if (componentName != _this.props.componentName) {
                document.removeEventListener('component' + _this.props.componentName + 'Loaded', _this.setComponentLoaded);
                document.addEventListener('component' + componentName + 'Loaded', _this.setComponentLoaded);
            }
        }, _this.componentWillMount = function () {
            if (_this.props.componentName) {
                document.addEventListener('component' + _this.props.componentName + 'Loaded', _this.setComponentLoaded);
            }
        }, _this.setComponentLoaded = function () {
            _this.setState({
                componentLoaded: true
            });
        }, _this.componentWillUnmount = function () {
            var componentName = _this.props.componentName;

            document.removeEventListener('component' + componentName + 'Loaded', _this.setComponentLoaded);
        }, _temp), _possibleConstructorReturn(_this, _ret);
    }

    _createClass(DynamicComponentLoader, [{
        key: 'render',
        value: function render() {
            var componentLoaded = this.state.componentLoaded;
            var componentUrl = this.props.componentUrl;


            return _react2.default.createElement(
                _react2.default.Fragment,
                null,
                componentLoaded ? null : _react2.default.createElement('iframe', {
                    src: componentUrl,
                    style: _defineProperty({
                        width: 0,
                        height: 0,
                        border: '0'
                    }, 'border', 'none') })
            );
        }
    }]);

    return DynamicComponentLoader;
}(_react.Component);

exports.default = DynamicComponentLoader;