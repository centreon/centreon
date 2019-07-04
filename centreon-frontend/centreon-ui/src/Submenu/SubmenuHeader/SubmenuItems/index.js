import React, { Component } from "react";
import classnames from "classnames";
import styles from "../submenu.scss";

class SubmenuItems extends Component {
  render() {
    const { children } = this.props;
    return <ul className={classnames(styles["submenu-items"])}>{children}</ul>;
  }
}

export default SubmenuItems;
