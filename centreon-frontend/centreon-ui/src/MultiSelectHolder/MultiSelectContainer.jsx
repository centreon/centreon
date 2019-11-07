/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import MultiSelectHolder from '.';
import CustomRow from '../Custom/CustomRow';
import CustomColumn from '../Custom/CustomColumn';
import MultiSelectValue from '../InputField/InputFieldMultiSelectValue';

const excludeAfterIndex = 5;

class MultiSelectContainer extends Component {
  render() {
    const {
      label,
      ariaLabel,
      selected,
      error,
      values = [],
      onEdit,
      emptyInfo,
    } = this.props;
    return (
      <MultiSelectHolder
        isEmpty={values.length === 0}
        multiSelectLabel={label}
        ariaLabel={ariaLabel}
        multiSelectCount={values.length.toString()}
        error={error}
        onClick={onEdit}
        selected={selected}
        emptyInfo={emptyInfo}
      >
        {values.length > 0 ? (
          <CustomRow additionalStyles={['mb-0']}>
            {values.map((item, index) => {
              let result = null;
              if (index < excludeAfterIndex) {
                result = (
                  <CustomColumn customColumn="md-6">
                    <MultiSelectValue disabled placeholder={item.name} />
                  </CustomColumn>
                );
              }
              return result;
            })}
            {values.length > 5 ? (
              <CustomColumn customColumn="md-6">
                <MultiSelectValue multiSelectType />
              </CustomColumn>
            ) : null}
          </CustomRow>
        ) : null}
      </MultiSelectHolder>
    );
  }
}

export default MultiSelectContainer;
