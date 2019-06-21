import React, { Component } from "react";
import { findDOMNode } from "react-dom";
import classnames from "classnames";
import styles from "./input-field.scss";
import ScrollBar from "../../ScrollBar";
import CustomIconWithText from "../../Custom/CustomIconWithText";
import IconToggleSubmenu from "../../Icon/IconToggleSubmenu";

class InputFieldSelectCustom extends Component {
  state = {
    active: false,
    allOptions: [],
    options: [],
    selected: {}
  };

  toggleSelect = () => {
    const { active } = this.state;
    this.setState({
      active: !active
    });
  };

  componentWillMount = () => {
    const { value, options } = this.props;
    for (let i = 0; i < options.length; i++) {
      if (options[i].id == value) {
        this.setState({
          selected: options[i]
        });
      }
    }
    if (options) {
      this.setState({
        options,
        allOptions: options
      });
    }
  };

  componentWillReceiveProps = nextProps => {
    const { value, options } = nextProps;
    for (let i = 0; i < options.length; i++) {
      if (options[i].id == value) {
        this.setState({
          selected: options[i]
        });
      }
    }
    if (options) {
      this.setState({
        options,
        allOptions: options
      });
    }
  };

  searchTextChanged = e => {
    let searchString = e.target.value;
    let { allOptions } = this.state;
    this.setState({
      options: allOptions.filter(option => {
        return (
          String(option.name)
            .toLowerCase()
            .indexOf(String(searchString).toLowerCase()) > -1
        );
      })
    });
  };

  optionChecked = option => {
    const { onChange } = this.props;
    this.setState(
      {
        selected: option,
        active: false
      },
      () => {
        if (onChange) {
          onChange(option.id);
        }
      }
    );
  };

  UNSAFE_componentWillMount() {
    window.addEventListener("mousedown", this.handleClickOutside, false);
  }

  componentWillUnmount() {
    window.removeEventListener("mousedown", this.handleClickOutside, false);
  }

  handleClickOutside = e => {
    if (!this.select || this.select.contains(e.target)) {
      return;
    }
    this.setState({
      active: false
    });
  };

  focusInput = component => {
    const { allOptions } = this.state;
    if (component) {
      this.setState({
        options: allOptions
      });
      findDOMNode(component).focus();
    }
  };

  render() {
    const { active, selected, options } = this.state;
    const { size, label, error, icons, domainPath, customStyle } = this.props;
    return (
      <div
        className={classnames(
          styles["input-select"],
          styles[size ? size : ""],
          styles[active ? "active" : ""],
          error ? styles["has-danger"] : "",
          customStyle ? styles[customStyle] : "",
        )}
        ref={select => (this.select = select)}
      >
        <div className={classnames(styles["input-select-wrap"])}>
          {active ? (
            <input
              ref={this.focusInput}
              onChange={this.searchTextChanged}
              className={classnames(styles["input-select-input"])}
              type="text"
              placeholder="Search"
            />
          ) : (
            <span
              className={classnames(styles["input-select-field"])}
              onClick={this.toggleSelect.bind(this)}
            >
              {selected.name}
            </span>
          )}
          <IconToggleSubmenu
            iconPosition="icons-toggle-position-multiselect"
            iconType="arrow"
            onClick={this.toggleSelect.bind(this)}
          />
        </div>
        {active ? (
          <div className={classnames(styles["input-select-dropdown"])}>
            {options
              ? options.map(option => (
                  <React.Fragment>
                    {icons ? (
                      <CustomIconWithText
                        label={option.name}
                        onClick={this.optionChecked.bind(this, option)}
                        image={`${domainPath}/${option.preview}`}
                      />
                    ) : (
                      <span
                        onClick={this.optionChecked.bind(this, option)}
                        className={classnames(styles["input-select-label"])}
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
          <div className={classnames(styles["form-error"])}>{error}</div>
        ) : null}
      </div>
    );
  }
}

export default InputFieldSelectCustom;
