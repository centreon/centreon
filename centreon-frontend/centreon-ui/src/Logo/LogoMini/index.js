import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './logo-mini.scss';
import miniLogo from "../../../img/centreon-logo-mini.svg";

class LogoMini extends Component {
  render() { 
    const {customClass, onClick} = this.props;
    return ( 
      <div onClick={onClick} className={classnames(styles["logo-mini"], styles[customClass ? customClass : ''])}>
        <span>
          <img
            className={classnames(styles["logo-mini-image"])}
            src={miniLogo}
            width="23"
            height="21"
            alt=""
          />
        </span>
      </div>
    );
  }
}
 
export default LogoMini;