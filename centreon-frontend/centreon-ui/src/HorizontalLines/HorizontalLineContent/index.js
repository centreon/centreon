import React from "react";
import classnames from 'classnames';
import styles from './content-horizontal-line.scss';

const HorizontalLineContent = ({ hrTitle, hrColor, titleColor }) => (
  <div className={classnames(styles["content-hr"], {[styles[`content-hr-${hrColor}`]]: hrColor})}>
    <span className={classnames(styles["content-hr-title"], {[styles[`content-hr-title-${titleColor}`]]: titleColor})}>{hrTitle}</span>
  </div>
);

export default HorizontalLineContent;