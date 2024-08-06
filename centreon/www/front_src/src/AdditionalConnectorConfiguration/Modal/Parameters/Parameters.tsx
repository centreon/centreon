/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { equals } from 'ramda';

import { Divider } from '@mui/material';

import DeleteParameterGroupButton from './DeleteButton';
import AddParameterGroupButton from './AddButton';
import Parameter from './Parameter';
import useParameters from './useParameters';
import { useParametersStyles } from './useParametersStyles';

const Parameters = (): ReactElement => {
  const { classes } = useParametersStyles();

  const { addParameterGroup, deleteParameterGroup, parameters } =
    useParameters();

  return (
    <div>
      {parameters?.map((parameter, index) => (
        <div className={classes.parametersContainer} key={`${index}-parameter`}>
          <div className={classes.parametersComposition}>
            <Parameter index={index} parameter={parameter} />
            {parameters.length > 1 && (
              <DeleteParameterGroupButton
                onDeleteItem={() => deleteParameterGroup(index)}
              />
            )}
          </div>
          {!equals(parameters.length - 1, index) && (
            <Divider className={classes.parametersDivider} variant="middle" />
          )}
        </div>
      ))}
      <AddParameterGroupButton
        addButtonDisabled={false}
        onAddItem={addParameterGroup}
      />
    </div>
  );
};

export default Parameters;
