/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './icon-toggle-submenu.scss';

function IconToggleSubmenu({ iconType, iconPosition, rotate, ...rest }) {
  const cn = classnames(
    {
      [styles[`icons-toggle-${iconType}`]]: true,
    },
    styles[iconPosition || ''],
    { [styles['icons-toggle-rotate']]: !!rotate },
  );

  return <span className={cn} {...rest} />;
}

export default IconToggleSubmenu;
