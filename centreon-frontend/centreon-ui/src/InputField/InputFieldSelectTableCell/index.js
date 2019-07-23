/* eslint-disable react/jsx-indent */
/* eslint-disable react/jsx-no-bind */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable no-return-assign */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/no-find-dom-node */
/* eslint-disable camelcase */
/* eslint-disable no-plusplus */
/* eslint-disable react/prop-types */
/* eslint-disable react/sort-comp */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './input-select-table-cell.scss';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';
// import CustomIconWithText from '../../Custom/CustomIconWithText';

class InputFieldSelectCustom extends Component {
  state = {
    active: false,
  };

  render() {
    const { active } = this.state;
    const { size, error, customStyle } = this.props;
    return (
      <div
        className={classnames(
          styles['input-select'],
          styles[size || ''],
          styles[active ? 'active' : ''],
          error ? styles['has-danger'] : '',
          customStyle ? styles[customStyle] : '',
        )}
        ref={(select) => (this.select = select)}
      >
        <div className={classnames(styles['input-select-wrap'])}>
          <input
            ref={this.focusInput}
            onChange={this.searchTextChanged}
            className={classnames(styles['input-select-input'])}
            type="text"
            placeholder="Search"
          />
          <IconToggleSubmenu
            iconPosition="icons-toggle-position-multiselect-table"
            iconType="arrow"
          />
        </div>
        <div className={classnames(styles['input-select-dropdown'])}>
          <React.Fragment>
            {/* <CustomIconWithText /> */}
            <span className={classnames(styles['input-select-label'])}>
              Option name
            </span>
          </React.Fragment>
        </div>
        {error ? (
          <div className={classnames(styles['form-error'])}>{error}</div>
        ) : null}
      </div>
    );
  }
}

export default InputFieldSelectCustom;
