import React from 'react';
import classnames from 'classnames';
import styles from './content-horizontal-line.scss';

const HorizontalLineContent = ({ hrTitle, hrColor, hrTitleColor }) => (
  <div
    className={classnames(styles['content-hr'], {
      [styles[`content-hr-${hrColor}`]]: hrColor,
    })}
  >
    <span
      className={classnames(styles['content-hr-title'], {
        [styles[`content-hr-title-${hrTitleColor}`]]: hrTitleColor,
      })}
    >
      {hrTitle}
    </span>
  </div>
);

export default HorizontalLineContent;
