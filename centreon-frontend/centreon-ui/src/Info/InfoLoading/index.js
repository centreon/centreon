import React from 'react';
import classnames from 'classnames';
import styles from './info-loading.scss';
import IconAction from '../../Icon/IconAction';

const InfoLoading = ({
  infoType,
  color,
  customClass,
  label,
  iconActionType,
  iconColor,
}) => {
  const cn = classnames(
    styles['info-loading'],
    {
      [styles[`info-loading-${infoType || ''}-${color || null}`]]: true,
    },
    styles.linear,
    customClass || '',
  );
  return (
    <span className={cn}>
      {iconActionType ? (
        <IconAction
          iconDirection="icon-position-left"
          iconColor={iconColor}
          iconActionType={iconActionType}
        />
      ) : (
        ''
      )}
      {label}
      <span className={classnames(styles['info-loading-icon'])} />
    </span>
  );
};

export default InfoLoading;
