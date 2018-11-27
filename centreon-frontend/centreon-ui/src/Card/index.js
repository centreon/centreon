import React, { Component } from 'react';

class Card extends Component {
  render() {
    const { content } = this.props;
    return (
      <div>{content}</div>
    );
  }
}

export default Card;