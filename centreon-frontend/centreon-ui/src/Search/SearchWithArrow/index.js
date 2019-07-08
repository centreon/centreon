/* eslint-disable jsx-a11y/label-has-for */
/* eslint-disable jsx-a11y/label-has-associated-control */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './search-with-arrow.scss';
import ButtonActionInput from '../../Button/ButtonActionInput';

class SearchLive extends Component {
  onChange = (e) => {
    const { onChange, filterKey } = this.props;
    onChange(e.target.value, filterKey);
  };

  render() {
    const { label, value, searchLiveCustom } = this.props;

    return (
      <div
        className={classnames(
          styles['search-live'],
          styles.custom,
          searchLiveCustom ? styles['search-live-custom'] : '',
        )}
      >
        {label && <label>{label}</label>}
        <input type="text" value={value} onChange={this.onChange.bind(this)} />
        <ButtonActionInput
          buttonColor="green"
          iconColor="white"
          buttonActionType="delete"
          buttonIconType="arrow-right"
          buttonPosition="button-action-icon-custom"
        />
      </div>
    );
  }
}

export default SearchLive;
