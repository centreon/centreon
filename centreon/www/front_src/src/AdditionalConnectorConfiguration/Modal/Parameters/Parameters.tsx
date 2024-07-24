/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { equals } from 'ramda';

import { Divider } from '@mui/material';

import DeleteDatasetButton from './DeleteButton';
import AddDatasetButton from './AddButton';
import Parameter from './Parameter';
import { useParametersStyles } from './useParametersStyles';
import useParameters from './useParameters';

const Parameters = (): ReactElement => {
  const { classes } = useParametersStyles();

  const {
    addParameterGroup,
    deleteParameterGroup,
    getFieldType,
    parameters,
    changeParameterValue,
    getError,
    onBlur
  } = useParameters();

  return (
    <div>
      {parameters?.map((parameter, index) => (
        <div className={classes.parametersContainer} key={`${index}-parameter`}>
          <div className={classes.parametersComposition}>
            <Parameter
              changeParameterValue={changeParameterValue(index)}
              getError={getError(index)}
              getFieldType={getFieldType}
              handleBlur={onBlur(index)}
              parameter={parameter}
            />
            {parameters.length > 1 && (
              <DeleteDatasetButton
                onDeleteItem={() => deleteParameterGroup(index)}
              />
            )}
          </div>
          {!equals(parameters.length - 1, index) && (
            <Divider className={classes.parametersDivider} variant="middle" />
          )}
        </div>
      ))}
      <AddDatasetButton
        addButtonDisabled={false}
        onAddItem={addParameterGroup}
      />
    </div>
  );
};

export default Parameters;
