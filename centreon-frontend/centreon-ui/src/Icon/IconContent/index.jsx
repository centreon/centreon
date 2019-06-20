/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */

import React from 'react';
import classnames from 'classnames';
import styles from './content-icons.scss';

function IconContent({
  iconContentType,
  iconContentColor,
  loading,
  onClick,
  customClass,
}) {
  return (
    <span
      style={loading ? { top: '20%' } : {}}
      className={classnames(
        styles['content-icon'],
        { [styles[`content-icon-${iconContentType}`]]: true },
        styles[iconContentColor ? `content-icon-${iconContentColor}` : ''],
        styles[loading ? 'loading-animation' : ''],
        styles[customClass || ''],
      )}
      onClick={onClick}
    />
  );
}

export default IconContent;
