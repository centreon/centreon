/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable eqeqeq */
/* eslint-disable react/prop-types */
/* eslint-disable camelcase */
/* eslint-disable react/sort-comp */
import React, { Component } from 'react';
import { findDOMNode } from 'react-dom';
import classnames from 'classnames';
import styles from './input-field.scss';
import CustomIconWithText from '../../Custom/CustomIconWithText';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';

class InputFieldSelectCustom extends Component {
  state = {
    active: false,
    allOptions: [],
    options: [],
    selected: null,
  };

  componentWillUnmount() {
    window.removeEventListener('mousedown', this.handleClickOutside, false);
  }

  componentWillMount = () => {
    const { value, options, onChange } = this.props;
    const { selected } = this.state;
    let found = false;
    if (options) {
      for (let i = 0; i < options.length; i += 1) {
        if (options[i].id == value) {
          this.setState({
            selected: options[i],
          });
          found = true;
        }
      }
      this.setState({
        options,
        allOptions: options,
        ...(!found && { selected: null }),
      });
      if (!found && selected !== null) {
        onChange(null);
      }
    }
  };

  componentWillReceiveProps = (nextProps) => {
    const { value, options, onChange } = nextProps;
    const { selected } = this.state;
    let found = false;
    if (options) {
      for (let i = 0; i < options.length; i += 1) {
        if (options[i].id == value) {
          this.setState({
            selected: options[i],
          });
          found = true;
        }
      }
      this.setState({
        options,
        allOptions: options,
        ...(!found && { selected: null }),
      });
      if (!found && selected !== null) {
        onChange(null);
      }
    }
  };

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
  };

  optionChecked = (option) => {
    const { onChange } = this.props;
    this.setState(
      {
        selected: option,
        active: false,
      },
      () => {
        if (onChange) {
          onChange(option.id);
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
    const { allOptions } = this.state;
    if (component) {
      this.setState({
        options: allOptions,
      });
      // eslint-disable-next-line react/no-find-dom-node
      findDOMNode(component).focus();
    }
  };

  referSelectField = (component) => {
    this.select = component;
  };

  render() {
    const { active, selected, options } = this.state;
    const { size, error, icons, domainPath, customStyle } = this.props;
    return (
      <div
        className={classnames(
          styles['input-select'],
          styles[size || ''],
          styles[active ? 'active' : ''],
          error ? styles['has-danger'] : '',
          customStyle ? styles[customStyle] : '',
        )}
        ref={this.referSelectField}
      >
        <div className={classnames(styles['input-select-wrap'])}>
          {active ? (
            <input
              ref={this.focusInput}
              onChange={this.searchTextChanged}
              className={classnames(styles['input-select-input'])}
              type="text"
              placeholder="Search"
            />
          ) : (
            <span
              className={classnames(styles['input-select-field'])}
              onClick={this.toggleSelect.bind(this)}
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
          <div className={classnames(styles['input-select-dropdown'])}>
            {options
              ? options.map((option) => (
                // eslint-disable-next-line react/jsx-indent
                <React.Fragment>
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
                      className={classnames(styles['input-select-label'])}
                    >
                        {option.name}
                      </span>
                  )}
                </React.Fragment>
              ))
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

export default InputFieldSelectCustom;