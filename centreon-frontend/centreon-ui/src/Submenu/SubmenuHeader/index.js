import React, { Component } from "react";
import classnames from 'classnames';
import styles from "./submenu.scss";

class SubmenuHeader extends Component {
  render() {
    const { submenuType, children } = this.props;
    return <div className={classnames(styles[`submenu-${submenuType}`])}>{children}</div>;
  }
}

export default SubmenuHeader;