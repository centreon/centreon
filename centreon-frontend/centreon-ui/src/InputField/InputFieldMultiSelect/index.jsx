/* eslint-disable react/prop-types */
/* eslint-disable react/jsx-no-bind */
/* eslint-disable react/no-array-index-key */
/* eslint-disable no-return-assign */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './input-multi-select.scss';
import Checkbox from '../../Checkbox';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';

class InputFieldMultiSelect extends Component {
  constructor(props) {
    super(props);

    this.state = {
      active: false,
      allOptions: [],
      options: [],
      activeOptions: {},
    };
  }

  componentWillMount() {
    const { options, value } = this.props;
    const activeOptions = {};
    if (value) {
      // eslint-disable-next-line
      for (const val of value) {
        activeOptions[val] = true;
      }
    }
    if (options) {
      this.setState({
        options,
        allOptions: options,
        activeOptions,
      });
    }
  }

  componentWillReceiveProps(nextProps) {
    const { options, value } = nextProps;
    const activeOptions = {};
    if (value) {
      // eslint-disable-next-line
      for (const val of value) {
        activeOptions[val] = true;
      }
    }
    if (options) {
      this.setState({
        options,
        allOptions: options,
        activeOptions,
      });
    }
  }

  componentWillUnmount() {
    window.removeEventListener('mousedown', this.handleClickOutside, false);
  }

  // eslint-disable-next-line
  UNSAFE_componentWillMount() {
    window.addEventListener('mousedown', this.handleClickOutside, false);
  }

  searchTextChanged(e) {
    const searchString = e.target.value;
    const { allOptions } = this.state;
    this.setState({
      options: allOptions.filter((option) => {
        return (
          String(option.name)
            .toLowerCase()
            .indexOf(String(searchString).toLowerCase()) > -1
        );
      }),
    });
  }

  toggleSelect() {
    const { active } = this.state;
    this.setState({
      active: !active,
    });
  }

  optionChecked(option) {
    const { activeOptions } = this.state;
    const { onChange } = this.props;
    activeOptions[option.id] = !activeOptions[option.id];
    this.setState(
      {
        activeOptions,
      },
      () => {
        if (onChange) {
          const activeIds = [];
          // eslint-disable-next-line
          for (const key in activeOptions) {
            if (activeOptions[key]) {
              activeIds.push(key);
            }
          }
          onChange(activeIds);
        }
      },
    );
  }

  handleClickOutside(e) {
    if (!this.multiselect || this.multiselect.contains(e.target)) {
      return;
    }
    this.setState({
      active: false,
    });
  }

  render() {
    const { active, options, activeOptions } = this.state;
    const { size, error } = this.props;
    return (
      <div
        className={classnames(
          styles['multi-select'],
          styles[size || ''],
          styles[active ? 'active' : ''],
          error ? styles['has-danger'] : '',
        )}
        ref={(multiselect) => (this.multiselect = multiselect)}
      >
        <div className={classnames(styles['multi-select-wrap'])}>
          <input
            onChange={this.searchTextChanged}
            className={classnames(styles['multi-select-input'])}
            type="text"
            placeholder="Search"
            onFocus={() => {
              this.setState({
                active: true,
              });
            }}
          />
          <IconToggleSubmenu
            iconPosition="icons-toggle-position-multiselect"
            iconType="arrow"
            onClick={this.toggleSelect.bind(this)}
          />
        </div>

        {active ? (
          <div className={classnames(styles['multi-select-dropdown'])}>
            {options
              ? options.map((option, index) => {
                  return (
                    <Checkbox
                      key={`multiselect-checkbox-${index}`}
                      label={option.name}
                      onClick={this.optionChecked.bind(this, option)}
                      iconColor="green"
                      onChange={() => {}}
                      checked={activeOptions[option.id] || false}
                    />
                  );
                })
              : null}
          </div>
        ) : null}

        {error ? (
          <div className={classnames(styles['form-error'])}>{error}</div>
        ) : null}
      </div>
    );
  }
}

export default InputFieldMultiSelect;
