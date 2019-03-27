import React, { Component } from "react";
import classnames from 'classnames';
import "loaders.css/loaders.min.css";
import styles from './loader-content.scss';

class Loader extends Component {
  render() {
    const {className} = this.props;
    const cn = classnames(styles.loader, styles.content, styles[className ? className : '']);
    return (
      <div className={cn}>
        <div className={classnames(styles["loader-inner"], "ball-grid-pulse")}>
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