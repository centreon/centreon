import React from "react";
import classnames from 'classnames';
import styles from './custom-title.scss';

const Title = ({ icon, label, titleColor, customTitleStyles, onClick, style, labelStyle }) => (
  <h2 className={classnames(styles["custom-title"], customTitleStyles ? styles["custom-title-styles"] : '')}
    onClick={onClick}
    style={style}
  >
    {icon ? (
      <span className={classnames(styles["custom-title-icon"], {[styles[`custom-title-icon-${icon}`]]: true})}/>
    ) : null}
    <span
      className={classnames(styles["custom-title-label"], styles[titleColor ? titleColor : ''])}
      style={labelStyle}
    >
      {label}
    </span>
  </h2>
);

export default Title;
