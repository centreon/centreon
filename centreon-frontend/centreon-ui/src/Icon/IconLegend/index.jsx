/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */

import React from 'react';
import classnames from 'classnames';
import styles from './icon-legend.scss';
import IconAction from '../IconAction';

function IconLegend({ iconColor, buttonIconType, title, legendType }) {
  const cn = classnames(styles['icon-legend'], styles[legendType || '']);
  return (
    <span className={cn}>
      <IconAction
        iconDirection="icon-position-center"
        iconColor={iconColor || ''}
        iconActionType={buttonIconType}
      />
      {title && (
        <span className={classnames(styles['icon-legend-title'])}>{title}</span>
      )}
    </span>
  );
}

export default IconLegend;
