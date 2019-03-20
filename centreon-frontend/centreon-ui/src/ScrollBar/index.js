import React, {Component} from 'react';
import 'react-perfect-scrollbar/dist/css/styles.css';
import './scroll-bar.scss';
import PerfectScrollbar from 'react-perfect-scrollbar';

class ScrollBar extends Component {
  render(){
    const {children, scrollType} = this.props;
    return (
      <PerfectScrollbar
          onScrollY={true}
          className={scrollType}
          >
          {children}
      </PerfectScrollbar>
    )
  }
}

export default ScrollBar;