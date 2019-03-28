import React from "react";
import classnames from 'classnames';
import styles from './switcher.scss';

class Switcher extends React.Component {
  state = {
    value: true,
    toggled: false
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
        toggled: !value,
        value
      });
    }
  };

  onChange = () => {
    const { onChange, filterKey } = this.props;
    const { value, toggled } = this.state;
    this.setState({
      value: !value,
      toggled: !toggled
    });
    if (onChange) {
      onChange(!value, filterKey);
    }
  };

  toggled = () => {
    const { toggled } = this.state;
    this.setState({
      toggled: !toggled
    });
  };

  render() {
    const { switcherTitle, switcherStatus, customClass } = this.props;
    const { value, toggled } = this.state;
    console.log(customClass)
    return (
      <div className={classnames(styles.switcher, customClass)}>
        <span className={classnames(styles["switcher-title"])}>
          {switcherTitle ? switcherTitle : " "}
        </span>
        <span className={classnames(styles["switcher-status"])}>{switcherStatus}</span>
        <label className={classnames(styles.switch, styles[toggled ? "switch-active" : "switch-hide"])}>
          <input
            type="checkbox"
            checked={!value}
            onClick={this.onChange.bind(this)}
          />
          <span className={classnames(styles["switch-slider"], styles["switch-round"] )}/>
          <span className={classnames(styles["switch-status"], styles["switch-status-show"])}>on</span>
          <span className={classnames(styles["switch-status"], styles["switch-status-hide"])}>off</span>
        </label>
      </div>
    );
  }
}

export default Switcher;