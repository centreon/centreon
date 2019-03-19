"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;

var _react = _interopRequireWildcard(require("react"));

var _ContentSliderItem = _interopRequireDefault(require("./ContentSliderItem"));

var _ContentSliderLeftArrow = _interopRequireDefault(require("./ContentSliderLeftArrow"));

var _ContentSliderRightArrow = _interopRequireDefault(require("./ContentSliderRightArrow"));

var _ContentSliderIndicators = _interopRequireDefault(require("./ContentSliderIndicators"));

require("./content-slider.scss");

var _sliderDefaultImage = _interopRequireDefault(require("./slider-default-image.png"));

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _interopRequireWildcard(obj) { if (obj && obj.__esModule) { return obj; } else { var newObj = {}; if (obj != null) { for (var key in obj) { if (Object.prototype.hasOwnProperty.call(obj, key)) { var desc = Object.defineProperty && Object.getOwnPropertyDescriptor ? Object.getOwnPropertyDescriptor(obj, key) : {}; if (desc.get || desc.set) { Object.defineProperty(newObj, key, desc); } else { newObj[key] = obj[key]; } } } } newObj.default = obj; return newObj; } }

function _typeof(obj) { if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") { _typeof = function _typeof(obj) { return typeof obj; }; } else { _typeof = function _typeof(obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }; } return _typeof(obj); }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

function _possibleConstructorReturn(self, call) { if (call && (_typeof(call) === "object" || typeof call === "function")) { return call; } return _assertThisInitialized(self); }

function _getPrototypeOf(o) { _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf : function _getPrototypeOf(o) { return o.__proto__ || Object.getPrototypeOf(o); }; return _getPrototypeOf(o); }

function _assertThisInitialized(self) { if (self === void 0) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function"); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, writable: true, configurable: true } }); if (superClass) _setPrototypeOf(subClass, superClass); }

function _setPrototypeOf(o, p) { _setPrototypeOf = Object.setPrototypeOf || function _setPrototypeOf(o, p) { o.__proto__ = p; return o; }; return _setPrototypeOf(o, p); }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

var SliderContent =
/*#__PURE__*/
function (_Component) {
  _inherits(SliderContent, _Component);

  function SliderContent(props) {
    var _this;

    _classCallCheck(this, SliderContent);

    _this = _possibleConstructorReturn(this, _getPrototypeOf(SliderContent).call(this, props));

    _defineProperty(_assertThisInitialized(_this), "goToPrevSlide", function () {
      var currentIndex = _this.state.currentIndex;
      if (currentIndex === 0) return;

      _this.setState(function (prevState) {
        return {
          currentIndex: prevState.currentIndex - 1,
          translateValue: prevState.translateValue + _this.slideWidth()
        };
      });
    });

    _defineProperty(_assertThisInitialized(_this), "goToNextSlide", function () {
      var currentIndex = _this.state.currentIndex;
      var images = _this.props.images;

      if (currentIndex === images.length - 1) {
        return _this.setState({
          currentIndex: 0,
          translateValue: 0
        });
      } // This will not run if we met the if condition above


      _this.setState(function (prevState) {
        return {
          currentIndex: prevState.currentIndex + 1,
          translateValue: prevState.translateValue - _this.slideWidth()
        };
      });
    });

    _defineProperty(_assertThisInitialized(_this), "slideWidth", function () {
      return document.querySelector(".content-slider-wrapper") ? document.querySelector(".content-slider-wrapper").clientWidth : 780;
    });

    _defineProperty(_assertThisInitialized(_this), "renderSlides", function () {
      var currentIndex = _this.state.currentIndex;
      var images = _this.props.images;
      var slides = images.map(function (image, index) {
        var isActive = currentIndex === index ? true : false;
        return _react.default.createElement(_ContentSliderItem.default, {
          key: index,
          image: image,
          isActive: isActive
        });
      });

      if (images.length === 0) {
        return [_react.default.createElement(_ContentSliderItem.default, {
          image: _sliderDefaultImage.default,
          isActive: true
        })];
      }

      return slides;
    });

    _defineProperty(_assertThisInitialized(_this), "handleDotClick", function (e) {
      var _this$state = _this.state,
          currentIndex = _this$state.currentIndex,
          translateValue = _this$state.translateValue;
      var dotIndex = parseInt(e.target.getAttribute("data-index")); // Go back

      if (dotIndex < currentIndex) {
        return _this.setState({
          currentIndex: dotIndex,
          translateValue: -dotIndex * _this.slideWidth()
        });
      } // Go forward


      _this.setState({
        currentIndex: dotIndex,
        translateValue: translateValue + (currentIndex - dotIndex) * _this.slideWidth()
      });
    });

    _this.state = {
      currentIndex: 0,
      translateValue: 0
    };
    return _this;
  }

  _createClass(SliderContent, [{
    key: "render",
    value: function render() {
      var _this$state2 = this.state,
          currentIndex = _this$state2.currentIndex,
          translateValue = _this$state2.translateValue;
      var _this$props = this.props,
          images = _this$props.images,
          children = _this$props.children;
      return _react.default.createElement("div", {
        className: "content-slider-wrapper"
      }, _react.default.createElement("div", {
        className: "content-slider"
      }, _react.default.createElement("div", {
        className: "content-slider-items",
        style: {
          transform: "translateX(".concat(translateValue, "px)")
        }
      }, this.renderSlides()), _react.default.createElement("div", {
        className: "content-slider-controls"
      }, currentIndex === 0 ? null : _react.default.createElement(_ContentSliderLeftArrow.default, {
        goToPrevSlide: this.goToPrevSlide,
        iconColor: "gray"
      }), images.length === 0 ? null : _react.default.createElement(_ContentSliderRightArrow.default, {
        goToNextSlide: this.goToNextSlide,
        iconColor: "gray"
      })), _react.default.createElement(_ContentSliderIndicators.default, {
        images: images,
        currentIndex: currentIndex,
        handleDotClick: this.handleDotClick
      })), children);
    }
  }]);

  return SliderContent;
}(_react.Component);

var _default = SliderContent;
exports.default = _default;