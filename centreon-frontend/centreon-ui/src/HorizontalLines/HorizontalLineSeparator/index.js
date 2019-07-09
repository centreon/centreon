/* eslint-disable react/jsx-filename-extension */

import React from 'react';
import classnames from 'classnames';
import styles from './horizontal-line-separator.scss';

const HorizontalLineSeparator = () => (
  <span className={classnames(styles['hr-separator'])} />
);

export default HorizontalLineSeparator;
