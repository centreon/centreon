import React, { Component } from "react";
import classnames from 'classnames';
import styles from './content-slider.scss';
import ContentSliderItem from "./ContentSliderItem";
import ContentSliderLeftArrow from "./ContentSliderLeftArrow";
import ContentSliderRightArrow from "./ContentSliderRightArrow";
import ContentSliderIndicators from "./ContentSliderIndicators";

class SliderContent extends Component {
  constructor(props) {
    super(props);

    this.state = {
      currentIndex: 0,
      translateValue: 0
    };
  }

  goToPrevSlide = () => {
    const { currentIndex } = this.state;

    if (currentIndex === 0) return;

    this.setState(prevState => ({
      currentIndex: prevState.currentIndex - 1,
      translateValue: prevState.translateValue + this.slideWidth()
    }));
  };

  goToNextSlide = () => {
    const { currentIndex } = this.state;
    const { images } = this.props;
    if (currentIndex === images.length - 1) {
      return this.setState({ currentIndex: 0, translateValue: 0 });
    }

    // This will not run if we met the if condition above
    this.setState(prevState => ({
      currentIndex: prevState.currentIndex + 1,
      translateValue: prevState.translateValue - this.slideWidth()
    }));
  };

  slideWidth = () => {
    return document.querySelector(".content-slider-wrapper")
      ? document.querySelector(".content-slider-wrapper").clientWidth
      : 780;
  };

  renderSlides = () => {
    const { currentIndex } = this.state;
    const { type, images } = this.props;

    const slides = images.map((image, index) => {
      let isActive = currentIndex === index ? true : false;
      return (
        <ContentSliderItem key={index} image={image} isActive={isActive} />
      );
    });

    if (images.length === 0) {
      return [
        <ContentSliderItem
          cssImage={type}
          isActive={true}
        />
      ];
    }

    return slides;
  };

  handleDotClick = e => {
    const { currentIndex, translateValue } = this.state;

    const dotIndex = parseInt(e.target.getAttribute("data-index"));

    // Go back
    if (dotIndex < currentIndex) {
      return this.setState({
        currentIndex: dotIndex,
        translateValue: -dotIndex * this.slideWidth()
      });
    }

    // Go forward
    this.setState({
      currentIndex: dotIndex,
      translateValue:
        translateValue + (currentIndex - dotIndex) * this.slideWidth()
    });
  };

  render() {
    const { currentIndex, translateValue } = this.state;
    const { images, children } = this.props;

    return (
      <div className={classnames(styles["content-slider-wrapper"])}>
        <div className={classnames(styles["content-slider"])}>
          <div
            className={classnames(styles["content-slider-items"])}
            style={{
              transform: `translateX(${translateValue}px)`
            }}
          >
            {this.renderSlides()}
          </div>
          <div className={classnames(styles["content-slider-controls"])}>
            {currentIndex === 0 ? null : (
              <ContentSliderLeftArrow
                goToPrevSlide={this.goToPrevSlide}
                iconColor="gray"
              />
            )}
            {images.length <= 1 ? null : (
              <ContentSliderRightArrow
                goToNextSlide={this.goToNextSlide}
                iconColor="gray"
              />
            )}
          </div>
          <ContentSliderIndicators
            images={images}
            currentIndex={currentIndex}
            handleDotClick={this.handleDotClick}
          />
        </div>
        {children}
      </div>
    );
  }
}

export default SliderContent;