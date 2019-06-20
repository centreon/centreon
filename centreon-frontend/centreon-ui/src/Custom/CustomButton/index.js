import React from 'react';
import classnames from 'classnames';
import styles from './custom-button.scss';

const CustomButton = ({ color, label }) => {
  const cn = classnames(styles["custom-button"], {[styles[`custom-button-${color ? color : ""}`]]: true});
  return (
    <button className={cn}>
      {label}
    </button>
  )
}

export default CustomButton;