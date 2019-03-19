import React, { Component } from "react";
import './progress-bar-steps.scss';

class ProgressBarSteps extends Component {
  render() {
    const { children } = this.props;
    return (
      <div className="progress-bar">
        <div className="progress-bar-wrapper">
          <ul className="progress-bar-items">
            {children}
          </ul>
        </div>
      </div>
    );
  }
}

export default ProgressBarSteps;