import React, { Component } from "react";
import "./card.scss";

class Card extends Component {
  render() {
    const { children } = this.props;
    return (
      <div className="card">
        <div className="card-items">{children}</div>
      </div>
    );
  }
}

export default Card;
