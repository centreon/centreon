/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/label-has-for */

import React from 'react';
import classnames from 'classnames';
import styles from './input-text.scss';
import IconInfo from '../Icon/IconInfo';

function InputField({
  type,
  label,
  placeholder,
  topRightLabel,
  name,
  inputSize,
  error,
  iconName,
  iconColor,
  noMargin,
  ...rest
}) {
  return (
    <div
      className={classnames(
        styles['form-group'],
        styles[inputSize || ''],
        error ? styles['has-danger'] : '',
        noMargin ? styles['no-bottom-margin'] : '',
      )}
    >
      {label && (
        <label htmlFor={rest.id}>
          <span>
            {iconName ? (
              <IconInfo iconName={iconName} iconColor={iconColor} />
            ) : null}
            {label}
          </span>
          <span className={classnames(styles['label-option'], styles.required)}>
            {topRightLabel || null}
          </span>
        </label>
      )}
      <input
        name={name}
        type={type}
        placeholder={placeholder}
        className={classnames(styles['form-control'])}
        {...rest}
      />
      {error ? (
        <div className={classnames(styles['form-error'])}>{error}</div>
      ) : null}
    </div>
  );
}

export { InputField };

export default InputField;
