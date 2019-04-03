import React from 'react';
import classnames from 'classnames';
import styles from './custom-icon-with-text.scss';

const CustomIconWithText = ({label}) => {
  return (
    <span className={classnames(styles["custom-multiple"])}>
      <span className={classnames(styles["custom-multiple-icon"])}></span>
      <span className={classnames(styles["custom-multiple-text"])}>{label}</span>
    </span>
  )
}

export default CustomIconWithText;