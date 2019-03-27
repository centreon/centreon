import React from "react";
import classnames from 'classnames';
import styles from './content-horizontal-line.scss';

const HorizontalLineContent = ({ hrTitle }) => (
  <div className={classnames(styles["content-hr"])}>
    <span className={classnames(styles["content-hr-title"])}>{hrTitle}</span>
  </div>
);

export default HorizontalLineContent;