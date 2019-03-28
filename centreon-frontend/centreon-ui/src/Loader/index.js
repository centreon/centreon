import React from "react";
import classnames from 'classnames';
import styles from './loader-additions.scss';

export default ({ fullContent }) => (
  <div 
    className={classnames(style.loader, fullContent ? 'full-relative-content' : '')}>
    <div className={classnames(styles["loader-inner"], styles["ball-grid-pulse"])}>
      <div />
      <div />
      <div />
      <div />
    </div>
  </div>
);