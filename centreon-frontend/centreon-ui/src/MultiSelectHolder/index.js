import React from "react";
import IconEdit from "../MaterialComponents/Icons/IconEdit";
import classnames from "classnames";
import styles from "./multi-select-holder.scss";

class InputFieldMultiSelectEmpty extends React.Component {
  state = {
    isHovered: false
  };

  toggleHover = () => {
    const { isHovered } = this.state;
    this.setState({
      isHovered: !isHovered
    });
  };

  render() {
    const { isEmpty, multiSelectCount, multiSelectLabel, children } = this.props;
    const { isHovered } = this.state;
    return (
      <div
        className={classnames(
          styles["multi-select-holder"],
          isEmpty ? styles["multi-select-holder-empty"] : ""
        )}
        onMouseEnter={this.toggleHover.bind(this)}
        onMouseLeave={this.toggleHover.bind(this)}
      >
        {isEmpty && (
          <React.Fragment>
            <span
              className={classnames(styles["multi-select-holder-empty-label"])}
            >{`${multiSelectLabel} (${multiSelectCount})`}</span>
            <span className={classnames(styles["multi-select-holder-add"])}>
              + Click to link indicators
            </span>
          </React.Fragment>
        )}
        {children}
        {isHovered ? <IconEdit /> : null}
      </div>
    );
  }
}

export default InputFieldMultiSelectEmpty;
