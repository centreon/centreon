import React, {Component} from "react";
import ContentSliderItem from "./ContentSliderItem";
import ContentSliderLeftArrow from './ContentSliderLeftArrow';
import ContentSliderRightArrow from './ContentSliderRightArrow';
import ContentSliderIndicators from './ContentSliderIndicators';
import IconContent from '../../Icon/IconContent';
import "./content-slider.scss";

class SliderContent extends Component {
  constructor(props) {
    super(props);

    this.state = {
      images: [
        "https://static.centreon.com/wp-content/uploads/2018/09/plugin-banner-it-operatio" +
            "ns-management.png",
        "https://s3.us-east-2.amazonaws.com/dzuz14/thumbnails/canyon.jpg",
        "https://s3.us-east-2.amazonaws.com/dzuz14/thumbnails/city.jpg",
        "https://s3.us-east-2.amazonaws.com/dzuz14/thumbnails/desert.jpg"
      ],
      currentIndex: 0,
      translateValue: 0
    }
  }

  goToPrevSlide = () => {
    const {currentIndex} = this.state;

    if (currentIndex === 0) 
      return;
    
    this.setState(prevState => ({
      currentIndex: prevState.currentIndex - 1,
      translateValue: prevState.translateValue + this.slideWidth()
    }))
  }

  goToNextSlide = () => {
    const {currentIndex, images} = this.state;

    if (currentIndex === images.length - 1) {
      return this.setState({currentIndex: 0, translateValue: 0})
    }

    // This will not run if we met the if condition above
    this.setState(prevState => ({
      currentIndex: prevState.currentIndex + 1,
      translateValue: prevState.translateValue - this.slideWidth()
    }));
  }

  slideWidth = () => {
    return document
      .querySelector('.content-slider-item')
      .clientWidth
  }

  renderSlides = () => {
    const {images, currentIndex} = this.state;
    
    const slides = images.map((image, index) => {
      let isActive = (currentIndex === index)
        ? true
        : false
      return (<ContentSliderItem key={index} image={image} isActive={isActive}/>)
    });

    return slides
  }

  handleDotClick = e => {
    const {currentIndex, translateValue} = this.state;

    const dotIndex = parseInt(e.target.getAttribute('data-index'))

    // Go back
    if (dotIndex < currentIndex) {
      return this.setState({
        currentIndex: dotIndex,
        translateValue: -dotIndex * this.slideWidth()
      })
    }

    // Go forward
    this.setState({
      currentIndex: dotIndex,
      translateValue: translateValue + (currentIndex - dotIndex) * this.slideWidth()
    })
  }

  render() {
    const {images, currentIndex, translateValue} = this.state;

    return (
      <div className="content-slider-wrapper">
        <div className="content-slider">
          <div
            className="content-slider-items"
            style={{
            transform: `translateX(${translateValue}px)`
          }}>
            {this.renderSlides()}
          </div>
          <div className="content-slider-controls">
            <ContentSliderLeftArrow goToPrevSlide={this.goToPrevSlide}/>
            <ContentSliderRightArrow goToNextSlide={this.goToNextSlide}/>
          </div>
          <ContentSliderIndicators
            images={images}
            currentIndex={currentIndex}
            handleDotClick={this.handleDotClick}/>
        </div>
        <IconContent iconContentType="add" iconContentColor="green" />
      </div>
    )
  }
}

export default SliderContent;
