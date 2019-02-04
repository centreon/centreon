import React, { Component } from "react";

class Button extends Component {
  render() {
    const { label, onClick, color } = this.props;
    return (
      <button onClick={onClick} style={{ color: color }}>
        {label}
      </button>
    );
  }
}

export default Button;
