import React, { Component } from "react";
import classnames from "classnames";
import styles from "./card.scss";

class Card extends Component {
  render() {
    const { children, style } = this.props;
    return (
      <div style={style} className={classnames(styles.card)}>
        <div>{children}</div>
      </div>
    );
  }
}

export default Card;
