import React from 'react';
import classnames from 'classnames';
import styles from '../../global-sass-files/_grid.scss';

const CustomRow = ({children}) => {
  return (
    <div className={classnames(styles["container__row"])}>
      {children}
    </div>
  )
};

export default CustomRow;