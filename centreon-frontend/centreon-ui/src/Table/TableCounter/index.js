import React from 'react';
import IconAction from '../../Icon/IconAction';
import './table-counter.scss';

const TableCounter = ({activeClass, number}) => {
  return (
    <div className={`table-counter ${activeClass ? activeClass : ''}`}>
      <span className="table-counter-number">
        {number}
        <IconAction iconActionType="arrow-right" />
      </span>
      <div className="table-counter-dropdown">
        <span className="table-counter-number">{number}</span>
        <span className="table-counter-number active">{number}</span>
        <span className="table-counter-number">{number}</span>
        <span className="table-counter-number">{number}</span>
      </div>
    </div>
  )
}

export default TableCounter;