import React, { Component, Children } from "react";
import { findDOMNode } from "react-dom";
import PropTypes from "prop-types";
import { normalize } from "./helpers";

export default class BoundingBox extends Component {
  state = {
    isInViewport: null
  };

  static propTypes = {
    onChange: PropTypes.func,
    children: PropTypes.oneOfType([PropTypes.element, PropTypes.func])
  };

  componentDidMount() {
    this.node = findDOMNode(this);
    if (this.props.active) {
      this.startWatching();
    }
  }

  componentWillUnmount() {
    this.stopWatching();
  }

  componentDidUpdate(prevProps) {
    this.node = findDOMNode(this);

    if (this.props.active && !prevProps.active) {
      this.setState({
        isInViewport: null
      });

      this.startWatching();
    } else if (!this.props.active) {
      this.stopWatching();
    }
  }

  getContainer = () => {
    return window;
  };

  startWatching = () => {
    if (this.interval) {
      return;
    }

    this.interval = setInterval(this.isIn, 0);
  };

  stopWatching = () => {
    if (this.interval) {
      this.interval = clearInterval(this.interval);
    }
  };

  roundRectDown(rect) {
    return {
      top: Math.floor(rect.top),
      left: Math.floor(rect.left),
      bottom: Math.floor(rect.bottom),
      right: Math.floor(rect.right)
    };
  }

  isIn = () => {
    const element = this.node;

    if (!element) {
      return this.state;
    }

    const rect = normalize(this.roundRectDown(element.getBoundingClientRect()));

    const windowRect = {
      top: 0,
      left: 0,
      bottom: window.innerHeight || document.documentElement.clientHeight,
      right: window.innerWidth || document.documentElement.clientWidth
    };

    const rectBox = {
      top: windowRect.top - rect.top,
      left: windowRect.left - rect.left,
      bottom: windowRect.bottom - rect.bottom,
      right: windowRect.right - rect.right,
      offsetHeight: element.offsetHeight
    };

    const isNotHidden = rect.height > 0 && rect.width > 0;

    let isInViewport =
      isNotHidden &&
      rect.top >= windowRect.top &&
      rect.left >= windowRect.left &&
      rect.bottom <= windowRect.bottom &&
      rect.right <= windowRect.right;

    let state = this.state;
    if (
      this.state.isInViewport !== isInViewport ||
      this.state.rectBox.top !== rectBox.top ||
      rectBox.bottom !== this.state.rectBox.bottom
    ) {
      state = {
        rectBox
      };
      this.setState(state);
      if (this.props.onChange) this.props.onChange(isInViewport);
    }

    return state;
  };

  render() {
    if (this.props.children instanceof Function) {
      return this.props.children({
        rectBox: this.state.rectBox
      });
    }
    return Children.only(this.props.children);
  }
}
