import React from 'react';
import classnames from 'classnames';
import styles from './horizontal-line-separator.scss';

function HorizontalLineSeparator() {
  return <span className={classnames(styles['hr-separator'])} />;
}

export default HorizontalLineSeparator;
