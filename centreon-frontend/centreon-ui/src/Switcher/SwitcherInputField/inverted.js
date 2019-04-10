import React from "react";
import classnames from 'classnames';
import styles from './swithcer-input-field.scss';
import IconClose from "../../Icon/IconClose";
import IconAction from "../../Icon/IconAction";

class SwitcherInputField extends React.Component {
  state = {
    value: false
  };

  UNSAFE_componentDidMount = () => {
    const { value } = this.props;
    if (value) {
      this.setState({
        value
      });
    }
  };

  UNSAFE_componentWillReceiveProps = nextProps => {
    const { value } = nextProps;
    if (this.state.value != value) {
      this.setState({
        value
      });
    }
  };

  onChange = () => {
    const { onChange } = this.props;
    const { value } = this.state;
    this.setState({
      value: !value
    });
    if (onChange) {
      onChange(!value);
    }
  };

  render() {
    const { customClass } = this.props;
    const { value } = this.state;
    return (
      <div className={classnames(styles["switcher-input"], styles[customClass ? customClass : ''])}>
        <label className={classnames(styles.switch, styles[value ? "switch-active" : "switch-hide"])}>
          <input
            type="checkbox"
            checked={value}
            onClick={this.onChange.bind(this)}
          />
          <span className={classnames(styles["switch-slider"], styles["switch-round"] )}>
            <span className={classnames(styles["switcher-icon-left"])}>
              <IconClose customStyle="icon-close-custom" iconType="small" />
            </span>
            <span className={classnames(styles["switcher-icon-right"])}>
              <IconAction customStyle="icon-action-custom" iconActionType="check" />
            </span>
          </span>
        </label>
      </div>
    );
  }
}

export default SwitcherInputField;