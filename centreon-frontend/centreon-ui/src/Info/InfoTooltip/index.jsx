/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './info-tooltip.scss';
import IconInfo from '../../Icon/IconInfo';

function Tooltip({ iconText, tooltipText, iconColor }) {
  return (
    <div className={classnames(styles.tooltip)}>
      <IconInfo iconName="question" iconText={iconText} iconColor={iconColor} />
      <span className={classnames(styles.tooltiptext)}>{tooltipText}</span>
    </div>
  );
}

export default Tooltip;
