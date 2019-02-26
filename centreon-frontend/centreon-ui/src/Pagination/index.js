import React from 'react';
import './pagination.scss';

const Pagination = () => {
  return (
    <div class="pagination">
      <a href="#">First</a>
      <a href="#">1</a>
      <a href="#" className="active">2</a>
      <a href="#">3</a>
      <a href="#">Last</a>
    </div>
  )
}

export default Pagination;