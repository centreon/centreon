import React from "react";
import classnames from 'classnames';
import styles from './icon-round.scss';

const IconRound = ({ iconColor, iconType, iconTitle, iconPosition }) => {
  const cnIconsRound = classnames(styles["icon"], styles["icons-round"], styles[iconColor]);
  const cnIconType = classnames("icon", styles["iconmoon"], styles[`icon-${iconType}`], styles[iconPosition ? iconPosition : '']);
  return (
    <span className={cnIconsRound}>
    <span
      className={cnIconType}
      title={iconTitle} 
    />
    </span>
  );
};

export default IconRound;