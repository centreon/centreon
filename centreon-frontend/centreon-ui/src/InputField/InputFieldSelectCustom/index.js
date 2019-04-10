import React, { Component } from "react";
import classnames from "classnames";
import styles from "./input-field.scss";
import ScrollBar from "../../ScrollBar";
import CustomIconWithText from "../../Custom/CustomIconWithText";
import IconToggleSubmenu from "../../Icon/IconToggleSubmenu";

class InputFieldSelectCustom extends Component {
  state = {
    active: false,
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
  };

  componentWillReceiveProps = nextProps => {
    const { value } = nextProps;
    const { options } = this.props;
    for (let i = 0; i < options.length; i++) {
      if (options[i].id == value) {
        this.setState({
          selected: options[i]
        });
      }
    }
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

  render() {
    const { active, selected } = this.state;
    const { size, label, error, options, icons, domainPath } = this.props;
    return (
      <div
        className={classnames(
          styles["input-select"],
          styles[size ? size : ""],
          styles[active ? "active" : ""],
          error ? styles["has-danger"] : ""
        )}
      >
        <div className={classnames(styles["input-select-wrap"])}>
          <span className={classnames(styles["input-select-field"])}>
            {selected.name}
          </span>
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
