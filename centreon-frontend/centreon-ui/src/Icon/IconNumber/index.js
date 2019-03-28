import React from "react";
import classnames from 'classnames';
import styles from './icon-number.scss';

const IconNumber = ({ iconColor, iconType, iconNumber, iconLink }) => {
  return (
    <a className={classnames(styles.icons, styles["icons-number"], styles[iconType], styles[iconColor])}
      {...iconLink && { href: iconLink }}
    >
      <span className={classnames(styles["number-wrap"])}>
        <span className={classnames(styles["number-count"])}>{iconNumber}</span>
      </span>
    </a>
  );
};

export default IconNumber;