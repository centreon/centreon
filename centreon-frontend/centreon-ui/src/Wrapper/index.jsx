/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from '../global-sass-files/_containers.scss';

function ExtensionsWrapper({ children }) {
  return (
    <div className={classnames(styles['content-wrapper'])}>{children}</div>
  );
}

export default ExtensionsWrapper;
