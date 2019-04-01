import React from 'react';
import classnames from 'classnames';
import styles from '../../global-sass-files/_grid.scss';

const CustomColumn = ({children, customColumn}) => {
  return (
    <div className={classnames({[styles[`container__col-${customColumn}`]]: true})}>
      {children}
    </div>
  )
};

export default CustomColumn;