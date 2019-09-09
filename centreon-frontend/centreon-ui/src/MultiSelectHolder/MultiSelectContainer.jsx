import React, { Component } from 'react';
import MultiSelectHolder from '.';
import CustomRow from '../Custom/CustomRow';
import CustomColumn from '../Custom/CustomColumn';
import InputFieldMultiSelectValue from '../InputField/InputFieldMultiSelectValue';

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
        isEmpty={values.length == 0}
        multiSelectLabel={label}
        ariaLabel={ariaLabel}
        multiSelectCount={values.length.toString()}
        error={error}
        onClick={onEdit}
        selected={selected}
        emptyInfo={emptyInfo}
      >
        <CustomRow additionalStyles={['mb-0']}>
          {values.map((item, index) => {
            let result = null;
            if (index < excludeAfterIndex) {
              result = (
                <CustomColumn customColumn="md-6">
                  <InputFieldMultiSelectValue
                    disabled
                    placeholder={item.name}
                  />
                </CustomColumn>
              );
            }
            return result;
          })}
          {values.length > 5 ? (
            <CustomColumn customColumn="md-6">
              <InputFieldMultiSelectValue multiSelectType />
            </CustomColumn>
          ) : null}
        </CustomRow>
      </MultiSelectHolder>
    );
  }
}

MultiSelectContainer.propTypes = {
  label: PropTypes.string.isRequired,
  selected: PropTypes.bool.isRequired,
  values: PropTypes.arrayOf().isRequired,
  error: PropTypes.bool.isRequired,
  onEdit: PropTypes.func.isRequired,
  emptyInfo: PropTypes.string.isRequired,
};

export default MultiSelectContainer;
