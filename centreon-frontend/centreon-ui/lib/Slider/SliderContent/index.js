"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _react = require("react");

var _react2 = _interopRequireDefault(_react);

var _ContentSliderItem = require("./ContentSliderItem");

var _ContentSliderItem2 = _interopRequireDefault(_ContentSliderItem);

var _ContentSliderLeftArrow = require("./ContentSliderLeftArrow");

var _ContentSliderLeftArrow2 = _interopRequireDefault(_ContentSliderLeftArrow);

var _ContentSliderRightArrow = require("./ContentSliderRightArrow");

var _ContentSliderRightArrow2 = _interopRequireDefault(_ContentSliderRightArrow);

var _ContentSliderIndicators = require("./ContentSliderIndicators");

var _ContentSliderIndicators2 = _interopRequireDefault(_ContentSliderIndicators);

var _IconContent = require("../../Icon/IconContent");

var _IconContent2 = _interopRequireDefault(_IconContent);

require("./content-slider.scss");

var _sliderDefaultImage = require("./slider-default-image.png");

var _sliderDefaultImage2 = _interopRequireDefault(_sliderDefaultImage);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var SliderContent = function (_Component) {
  _inherits(SliderContent, _Component);

  function SliderContent(props) {
    _classCallCheck(this, SliderContent);

    var _this = _possibleConstructorReturn(this, (SliderContent.__proto__ || Object.getPrototypeOf(SliderContent)).call(this, props));

    _this.goToPrevSlide = function () {
      var currentIndex = _this.state.currentIndex;


      if (currentIndex === 0) return;

      _this.setState(function (prevState) {
        return {
          currentIndex: prevState.currentIndex - 1,
          translateValue: prevState.translateValue + _this.slideWidth()
        };
      });
    };

    _this.goToNextSlide = function () {
      var currentIndex = _this.state.currentIndex;
      var images = _this.props.images;

      if (currentIndex === images.length - 1) {
        return _this.setState({ currentIndex: 0, translateValue: 0 });
      }

      // This will not run if we met the if condition above
      _this.setState(function (prevState) {
        return {
          currentIndex: prevState.currentIndex + 1,
          translateValue: prevState.translateValue - _this.slideWidth()
        };
      });
    };

    _this.slideWidth = function () {
      return document.querySelector(".content-slider-wrapper") ? document.querySelector(".content-slider-wrapper").clientWidth : 780;
    };

    _this.renderSlides = function () {
      var currentIndex = _this.state.currentIndex;
      var images = _this.props.images;


      var slides = images.map(function (image, index) {
        var isActive = currentIndex === index ? true : false;
        return _react2.default.createElement(_ContentSliderItem2.default, { key: index, image: image, isActive: isActive });
      });

      if (images.length === 0) {
        return [_react2.default.createElement(_ContentSliderItem2.default, { image: _sliderDefaultImage2.default, isActive: true })];
      }

      return slides;
    };

    _this.handleDotClick = function (e) {
      var _this$state = _this.state,
          currentIndex = _this$state.currentIndex,
          translateValue = _this$state.translateValue;


      var dotIndex = parseInt(e.target.getAttribute("data-index"));

      // Go back
      if (dotIndex < currentIndex) {
        return _this.setState({
          currentIndex: dotIndex,
          translateValue: -dotIndex * _this.slideWidth()
        });
      }

      // Go forward
      _this.setState({
        currentIndex: dotIndex,
        translateValue: translateValue + (currentIndex - dotIndex) * _this.slideWidth()
      });
    };

    _this.state = {
      currentIndex: 0,
      translateValue: 0
    };
    return _this;
  }

  _createClass(SliderContent, [{
    key: "render",
    value: function render() {
      var _state = this.state,
          currentIndex = _state.currentIndex,
          translateValue = _state.translateValue;
      var _props = this.props,
          images = _props.images,
          children = _props.children;


      return _react2.default.createElement(
        "div",
        { className: "content-slider-wrapper" },
        _react2.default.createElement(
          "div",
          { className: "content-slider" },
          _react2.default.createElement(
            "div",
            {
              className: "content-slider-items",
              style: {
                transform: "translateX(" + translateValue + "px)"
              }
            },
            this.renderSlides()
          ),
          _react2.default.createElement(
            "div",
            { className: "content-slider-controls" },
            currentIndex === 0 ? null : _react2.default.createElement(_ContentSliderLeftArrow2.default, {
              goToPrevSlide: this.goToPrevSlide,
              iconColor: "gray"
            }),
            images.length === 0 ? null : _react2.default.createElement(_ContentSliderRightArrow2.default, {
              goToNextSlide: this.goToNextSlide,
              iconColor: "gray"
            })
          ),
          _react2.default.createElement(_ContentSliderIndicators2.default, {
            images: images,
            currentIndex: currentIndex,
            handleDotClick: this.handleDotClick
          })
        ),
        children,
        _react2.default.createElement(_IconContent2.default, { iconContentType: "add", iconContentColor: "green" })
      );
    }
  }]);

  return SliderContent;
}(_react.Component);

exports.default = SliderContent;