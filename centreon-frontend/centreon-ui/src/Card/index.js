import React, { Component } from "react";

import "./card.scss";

class Card extends Component {
  render() {
    const {
      children,
      itemBorderColor,
      itemFooterColor,
      itemFooterLabel
    } = this.props;
    return (
      <div className="card">
        <div className="card-items">
          <div
            className={`card-item card-item-bordered-${itemBorderColor}`}
            style={{
              width: "250px"
            }}
          >
            {children}
            <span
              className={`card-item-footer card-item-footer-${itemFooterColor}`}
            >
              {itemFooterLabel}
            </span>
          </div>
        </div>
      </div>
    );
  }
}

export default Card;
