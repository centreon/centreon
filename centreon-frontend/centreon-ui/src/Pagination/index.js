/* eslint-disable react/jsx-no-bind */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/anchor-is-valid */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable no-plusplus */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './pagination.scss';
import IconAction from '../Icon/IconAction';

class Pagination extends Component {
  state = {
    currentPage: 0,
  };

  previousPage = () => {
    const { currentPage } = this.state;
    console.log(currentPage);
    if (currentPage > 0) {
      this.pageChanged(currentPage - 1);
    }
  };

  nextPage = () => {
    const { pageCount } = this.props;
    const { currentPage } = this.state;
    console.log(pageCount, currentPage);
    if (currentPage < pageCount - 1) {
      this.pageChanged(currentPage + 1);
    }
  };

  pageChanged = (page) => {
    const { onPageChange } = this.props;
    this.setState(
      {
        currentPage: page,
      },
      () => {
        onPageChange(page);
      },
    );
  };

  renderPages = (count) => {
    const { currentPage } = this.state;
    const pages = [];
    for (let i = 0; i < count; i++) {
      pages.push(
        <a
          key={`paginationPage${i}`}
          onClick={this.pageChanged.bind(this, i)}
          className={classnames(i === currentPage ? styles.active : '')}
        >
          {i + 1}
        </a>,
      );
    }

    return <React.Fragment>{pages}</React.Fragment>;
  };

  render() {
    const { pageCount, onPageChange } = this.props;
    if (!onPageChange || !pageCount) {
      return null;
    }
    return (
      <div className={classnames(styles.pagination)}>
        <a onClick={this.pageChanged.bind(this, 0)}>First</a>
        <IconAction
          iconActionType="arrow-right"
          onClick={this.previousPage.bind(this)}
        />
        {this.renderPages(pageCount)}
        <IconAction
          iconActionType="arrow-right"
          onClick={this.nextPage.bind(this)}
        />
        <a onClick={this.pageChanged.bind(this, pageCount - 1)}>Last</a>
      </div>
    );
  }
}

export default Pagination;
