"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

require("./progress-bar-steps.scss");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var ProgressBarSteps = function (_Component) {
  _inherits(ProgressBarSteps, _Component);

  function ProgressBarSteps() {
    _classCallCheck(this, ProgressBarSteps);

    return _possibleConstructorReturn(this, (ProgressBarSteps.__proto__ || Object.getPrototypeOf(ProgressBarSteps)).apply(this, arguments));
  }

  _createClass(ProgressBarSteps, [{
    key: "render",
    value: function render() {
      var _props = this.props,
          classActive = _props.classActive,
          number = _props.number,
          children = _props.children;

      return _react2.default.createElement(
        "div",
        { className: "progress-bar" },
        _react2.default.createElement(
          "div",
          { className: "progress-bar-wrapper" },
          _react2.default.createElement(
            "ul",
            { className: "progress-bar-items" },
            children
          )
        )
      );
    }
  }]);

  return ProgressBarSteps;
}(_react.Component);

exports.default = ProgressBarSteps;