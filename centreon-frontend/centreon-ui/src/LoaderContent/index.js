import React, { Component } from "react";
import "./loader-content.scss";
import "loaders.css/loaders.min.css";

class Loader extends Component {
  render() {
    const {className} = this.props;
    return (
      <div className={`loader content ${className}`}>
        <div className="loader-inner ball-grid-pulse">
          <div />
          <div />
          <div />
          <div />
        </div>
      </div>
    );
  }
}

export default Loader;