/* eslint-disable prettier/prettier */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable react/prop-types */
/* eslint-disable react/sort-comp */
/* eslint-disable camelcase */
import React, { Component } from 'react';
import { findDOMNode } from 'react-dom';
import classnames from 'classnames';
import styles from './input-select-table-cell.scss';
import CustomIconWithText from '../../Custom/CustomIconWithText';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';

class InputFieldSelectCustom extends Component {
  state = {
    active: false,
    allOptions: [],
    options: [],
    selected: {},
  };

  componentWillMount = () => {
    const { value, options } = this.props;
    if (options) {
      for (let i = 0; i < options.length; i += 1) {
        // eslint-disable-next-line
        if (options[i].id == value) {
          this.setState({
            selected: options[i],
          });
        }
      }
      this.setState({
        options,
        allOptions: options,
      });
    }
  };

  componentWillReceiveProps = (nextProps) => {
    const { value, options } = nextProps;
    if (options) {
      for (let i = 0; i < options.length; i += 1) {
        // eslint-disable-next-line
        if (options[i].id == value) {
          this.setState({
            selected: options[i],
          });
        }
      }
      this.setState({
        options,
        allOptions: options,
      });
    }
  };

  UNSAFE_componentWillMount() {
    window.addEventListener('mousedown', this.handleClickOutside, false);
  }

  componentWillUnmount() {
    window.removeEventListener('mousedown', this.handleClickOutside, false);
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

  optionChecked = (option, event) => {
    const { onChange } = this.props;
    this.setState(
      {
        selected: option,
        active: false,
      },
      () => {
        if (onChange) {
          onChange(option.id, event);
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
    const {
      size,
      error,
      icons,
      domainPath,
      customStyle,
      isColored,
    } = this.props;
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
          <span
            className={classnames(styles['input-select-field'])}
            onClick={this.toggleSelect}
          >
            {selected.name}
          </span>
          <IconToggleSubmenu
            iconPosition="icons-toggle-position-multiselect-table"
            iconType="arrow"
            onClick={this.toggleSelect}
          />
        </div>
        {active ? (
          <div className={classnames(styles['input-select-dropdown'])}>
            {options
              ? options.map((option) => (
                <div
                  style={
                      isColored
                        ? {
                            backgroundColor: option.color,
                            margin: '-4px',
                            lineHeight: '1.43',
                          }
                        : {
                            margin: '-4px',
                            lineHeight: '1.43',
                          }
                    }
                >
                  {icons ? (
                    <CustomIconWithText
                      label={option.name}
                      onClick={()=> this.optionChecked(option)}
                      image={`${domainPath}/${option.preview}`}
                    />
                    ) : (
                      <span
                        onClick={this.optionChecked.bind(this, option)}
                        className={classnames(styles['input-select-label'])}
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
          <div className={classnames(styles['form-error'])}>{error}</div>
        ) : null}
      </div>
    );
  }
}

export default InputFieldSelectCustom;
