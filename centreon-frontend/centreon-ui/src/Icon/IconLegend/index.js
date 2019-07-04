import React from 'react';
import classnames from 'classnames';
import styles from './icon-legend.scss';
import IconAction from '../IconAction';

const IconLegend = ({ iconColor, buttonIconType, title, legendType }) => {
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
};

export default IconLegend;
