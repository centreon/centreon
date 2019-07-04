import React, { Component } from "react";
import "react-perfect-scrollbar/dist/css/styles.css";
import classnames from "classnames";
import styles from "./scroll-bar.scss";
import PerfectScrollbar from "react-perfect-scrollbar";

class ScrollBar extends Component {
  render() {
    const { children, scrollType, scrollBarCustom } = this.props;
    return (
      <PerfectScrollbar
        className={classnames(
          styles["scrollbar-container"],
          scrollBarCustom ? styles[scrollBarCustom] : ""
        )}
        onScrollRight={true}
      >
        {children}
      </PerfectScrollbar>
    );
  }
}

export default ScrollBar;
