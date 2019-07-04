import React, { Component } from 'react';
import 'react-perfect-scrollbar/dist/css/styles.css';
import classnames from 'classnames';
import PerfectScrollbar from 'react-perfect-scrollbar';
import styles from './scroll-bar.scss';

class ScrollBar extends Component {
  render() {
    const { children, scrollType, scrollBarCustom } = this.props;
    return (
      <PerfectScrollbar
        className={classnames(
          styles['scrollbar-container'],
          scrollBarCustom ? styles[scrollBarCustom] : '',
        )}
        onScrollRight
      >
        {children}
      </PerfectScrollbar>
    );
  }
}

export default ScrollBar;
