import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './tab.scss';

class Tab extends Component {
  onClick = () => {
    const { label, onClick } = this.props;
    onClick(label);
  };

  render() {
    const {
      onClick,
      props: { activeTab, label, error },
    } = this;

    let className = classnames(
      styles['tab-list-item'],
      error ? styles['has-error'] : '',
    );

    if (activeTab === label) {
      className += classnames(` ${styles['tab-list-active']}`);
    }
    return (
      <li className={className} onClick={onClick}>
        {label}
      </li>
    );
  }
}

export default Tab;
