/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './input-field-multi-select.scss';

class InputFieldMultiSelect extends Component {
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
          className={classnames(
            styles['multi-select'],
            multiSelectType ? styles['multi-select-empty'] : '',
          )}
        >
          {!multiSelectType && (
            <input
              name={name}
              type={type}
              placeholder={placeholder}
              className={classnames(styles['multi-select-input'])}
              {...rest}
            />
          )}
          {error ? (
            <div className={classnames(styles['form-error'])}>{error}</div>
          ) : null}
        </div>
      </React.Fragment>
    );
  }
}

export { InputFieldMultiSelect };

export default InputFieldMultiSelect;
