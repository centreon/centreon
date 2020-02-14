/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable eqeqeq */
/* eslint-disable react/prop-types */
/* eslint-disable camelcase */
/* eslint-disable react/sort-comp */

import React, { Component } from 'react';
import { findDOMNode } from 'react-dom';
import clsx from 'clsx';
import styles from './input-field.scss';
import CustomIconWithText from '../../Custom/CustomIconWithText';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';

class InputFieldSelectCustom extends Component {
  state = {
    active: false,
    filteredOptions: [],
    selected: null,
  };

  UNSAFE_componentWillMount() {
    const { options } = this.props;
    this.setState({ filteredOptions: options });
  }

  componentWillUnmount() {
    window.removeEventListener('mousedown', this.handleClickOutside, false);
  }

  UNSAFE_componentWillReceiveProps(nextProps) {
    const { value } = nextProps;

    this.setState({
      selected: value,
    });
  }

  componentDidMount() {
    window.addEventListener('mousedown', this.handleClickOutside, false);
  }

  toggleSelect = () => {
    const { disabled } = this.props;
    if (disabled) return;
    const { active } = this.state;
    this.setState({
      active: !active,
    });
  };

  searchTextChanged = (e) => {
    const searchString = e.target.value;
    const { options } = this.props;
    this.setState({
      filteredOptions: options.filter((option) => {
        return (
          String(option.name)
            .toLowerCase()
            .indexOf(String(searchString).toLowerCase()) > -1
        );
      }),
    });
  };

  optionChecked = (option) => {
    const { onChange } = this.props;

    this.setState(
      {
        active: false,
      },
      () => {
        if (onChange) {
          onChange(option);
        }
      },
    );
  };

  handleClickOutside = (e) => {
    if (!this.select || this.select.contains(e.target)) {
      return;
    }
    this.setState({
      active: false,
    });
  };

  focusInput = (component) => {
    const { options } = this.props;
    if (component) {
      this.setState({
        filteredOptions: options,
      });
      // eslint-disable-next-line react/no-find-dom-node
      findDOMNode(component).focus();
    }
  };

  referSelectField = (component) => {
    this.select = component;
  };

  render() {
    const { active, selected, filteredOptions } = this.state;
    const {
      ariaLabel,
      size,
      error,
      icons,
      domainPath,
      customStyle,
    } = this.props;
    return (
      <div
        className={clsx(
          styles['input-select'],
          styles[size || ''],
          styles[active ? 'active' : ''],
          error ? styles['has-danger'] : '',
          customStyle ? styles[customStyle] : '',
        )}
        ref={this.referSelectField}
      >
        <div className={clsx(styles['input-select-wrap'])}>
          {active ? (
            <input
              ref={this.focusInput}
              onChange={this.searchTextChanged}
              className={clsx(styles['input-select-input'])}
              type="text"
              placeholder="Search"
            />
          ) : (
            <span
              className={clsx(styles['input-select-field'])}
              onClick={this.toggleSelect.bind(this)}
              aria-label={ariaLabel}
            >
              {selected ? selected.name : ''}
            </span>
          )}
          <IconToggleSubmenu
            iconPosition="icons-toggle-position-multiselect"
            iconType="arrow"
            onClick={this.toggleSelect}
          />
        </div>
        {active ? (
          <div className={clsx(styles['input-select-dropdown'])}>
            {filteredOptions
              ? filteredOptions.map((option) => (
                  // eslint-disable-next-line react/jsx-indent
                  <div key={option.id}>
                    {icons ? (
                      <CustomIconWithText
                        label={option.name}
                        onClick={() => {
                          this.optionChecked(option);
                        }}
                        {...(option.preview
                          ? { image: `${domainPath}/${option.preview}` }
                          : { iconOff: true })}
                      />
                    ) : (
                      <span
                        onClick={this.optionChecked.bind(this, option)}
                        className={clsx(styles['input-select-label'])}
                      >
                        {option.name}
                      </span>
                    )}
                  </div>
                ))
              : null}
          </div>
        ) : null}
        {error ? (
          <div className={clsx(styles['form-error'])}>{error}</div>
        ) : null}
      </div>
    );
  }
}

export default InputFieldSelectCustom;
