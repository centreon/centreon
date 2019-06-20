import React, { Component } from "react";
import classnames from 'classnames';
import styles from "../global-sass-files/_containers.scss";

class ExtensionsWrapper extends Component {
  render() {
    const { children } = this.props;
    return <div className={classnames(styles["content-wrapper"])}>{children}</div>;
  }
}

export default ExtensionsWrapper;