/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/label-has-for */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */

import React from 'react';
import classnames from 'classnames';
import styles from './checkbox.scss';

function Checkbox({ iconColor, label, info, name, onClick, ...rest }) {
  const cnCustomControl = classnames(
    styles['custom-control'],
    { [styles['custom-checkbox']]: true },
    styles[iconColor || ''],
  );

  return (
    <div className={classnames(styles['form-group'])} onClick={onClick}>
      <div className={cnCustomControl}>
        <input
          name={name}
          aria-checked={rest.checked}
          className={classnames(styles['custom-control-input'])}
          type="checkbox"
          {...rest}
        />
        <label
          htmlFor={rest.id}
          className={classnames(styles['custom-control-label'])}
        >
          {label}
          {info}
        </label>
      </div>
    </div>
  );
}

export { Checkbox };

export default Checkbox;
