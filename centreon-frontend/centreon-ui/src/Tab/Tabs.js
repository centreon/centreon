import React, { Component } from 'react';
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
  }

  render() {
    const {
      onClickTabItem,
      props: {
        children
      },
      state: {
        activeTab,
      }
    } = this;
    const {error} = this.props
    return (
      <div className="tab">
        <ol className="tab-list">
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
        <div className="tab-content">
          {error && <div className="has-error">{error}</div>}
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