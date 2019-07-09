/* eslint-disable no-shadow */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/destructuring-assignment */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './tab.scss';
import Tab from './Tab';

class Tabs extends Component {
  constructor(props) {
    super(props);

    this.state = {
      activeTab: this.props.children[0].props.label,
    };
  }

  onClickTabItem = (tab) => {
    this.setState({ activeTab: tab });
  };

  render() {
    const {
      onClickTabItem,
      props: { children },
      state: { activeTab },
    } = this;
    const { error } = this.props;
    return (
      <div className={classnames(styles.tab)}>
        <ol className={classnames(styles['tab-list'])}>
          {children.map((child) => {
            const { label, error } = child.props;
            return (
              <Tab
                activeTab={activeTab}
                key={label}
                label={label}
                onClick={onClickTabItem}
                error={error}
              />
            );
          })}
        </ol>
        <div className={classnames(styles['tab-content'])}>
          {error && (
            <div className={classnames(styles['has-error'])}>{error}</div>
          )}
          {children.map((child) => {
            if (child.props.label !== activeTab) return undefined;
            return child.props.children;
          })}
        </div>
      </div>
    );
  }
}

export default Tabs;
