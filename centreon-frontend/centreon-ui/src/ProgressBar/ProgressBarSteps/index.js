import React, { Component } from "react";
import classnames from 'classnames';
import styles from './progress-bar-steps.scss';

class ProgressBarSteps extends Component {
  render() {
    const { children, customStyles } = this.props;
    return (
      <div className={classnames(styles["progress-bar"], customStyles ? styles["progress-bar-custom-styles"] : '')}>
        <div className={classnames(styles["progress-bar-wrapper"])}>
          <ul className={classnames(styles["progress-bar-items"])}>
            {children}
          </ul>
        </div>
      </div>
    );
  }
}

export default ProgressBarSteps;