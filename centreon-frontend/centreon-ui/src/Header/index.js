import React, { Component } from "react";
import styles from './header.scss';

class Header extends Component {
  render() {
    console.log(styles)
    const { children, style } = this.props;
    return (
      <header className={styles.header} style={style}>
        <div className={styles["header-inner"]}>{children}</div>
      </header>
    );
  }
}

export default Header;