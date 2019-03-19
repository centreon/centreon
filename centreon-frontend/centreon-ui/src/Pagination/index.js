import React from 'react';
import IconAction from '../Icon/IconAction';
import './pagination.scss';

const Pagination = () => {
  return (
    <div className="pagination">
      <a href="#">First</a>
      <IconAction iconActionType="arrow-right" />
      <a href="#">1</a>
      <a href="#" className="active">2</a>
      <a href="#">3</a>
      <IconAction iconActionType="arrow-right" />
      <a href="#">Last</a>
    </div>
  )
}

export default Pagination;