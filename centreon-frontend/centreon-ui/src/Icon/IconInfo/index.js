/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import clsx from 'clsx';
import styles from './info-state-icon.scss';

const IconInfo = ({
  iconText,
  iconName = null,
  iconColor = null,
  iconPosition = null,
}) => {
  const cn = clsx(
    styles.info,
    { [styles[`info-${iconName}`]]: true },
    styles[iconPosition || ''],
    styles[iconColor || ''],
  );
  return (
    <>
      {iconName && <span className={cn} />}
      {iconText && (
        <span className={clsx(styles['info-text'])}>{iconText}</span>
      )}
    </>
  );
};

export default IconInfo;
