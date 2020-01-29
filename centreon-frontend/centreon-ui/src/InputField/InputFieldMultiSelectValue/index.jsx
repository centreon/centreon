/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import clsx from 'clsx';
import styles from './input-field-multi-select.scss';

class InputFieldMultiSelectValue extends Component {
  render() {
    const {
      type,
      placeholder,
      name,
      error,
      multiSelectType,
      ...rest
    } = this.props;
    return (
      <React.Fragment>
        <div
          className={clsx(
            styles['multi-select'],
            multiSelectType ? styles['multi-select-empty'] : '',
          )}
        >
          {!multiSelectType && (
            <input
              name={name}
              type={type}
              placeholder={placeholder}
              className={clsx(styles['multi-select-input'])}
              {...rest}
            />
          )}
          {error ? (
            <div className={clsx(styles['form-error'])}>{error}</div>
          ) : null}
        </div>
      </React.Fragment>
    );
  }
}

export { InputFieldMultiSelectValue };

export default InputFieldMultiSelectValue;
