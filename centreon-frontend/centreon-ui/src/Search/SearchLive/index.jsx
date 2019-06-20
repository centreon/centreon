/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/label-has-for */
/* eslint-disable jsx-a11y/label-has-associated-control */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './search-live.scss';
import ButtonActionInput from '../../Button/ButtonActionInput';
import '../SearchWithArrow/search-with-arrow.scss';

class SearchLive extends Component {
  onChange(e) {
    const { onChange, filterKey } = this.props;
    onChange(e.target.value, filterKey);
  }

  render() {
    const { label, value, icon } = this.props;
    return (
      <div
        className={classnames(
          styles['search-live'],
          styles[icon ? 'custom' : ''],
        )}
      >
        {label && <label>{label}</label>}
        <input type="text" value={value} onChange={this.onChange.bind(this)} />
        {icon ? (
          <ButtonActionInput
            buttonColor="green"
            iconColor="white"
            buttonActionType="delete"
            buttonIconType="arrow-right"
          />
        ) : null}
      </div>
    );
  }
}

export default SearchLive;
