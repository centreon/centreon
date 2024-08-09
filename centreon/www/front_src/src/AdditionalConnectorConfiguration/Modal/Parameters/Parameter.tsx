import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';
import { keys } from 'ramda';

import { TextField } from '@centreon/ui';
import { ItemComposition } from '@centreon/ui/components';

import { labelValue, labelName } from '../../translatedLabels';
import { Parameter } from '../models';

import { useParameterStyles } from './useParametersStyles';
import useParameter from './useParameter';

interface Props {
  index: number;
  parameter: Parameter;
}

const Parameter = ({ parameter, index }: Props): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useParameterStyles();

  const {
    changeParameterValue,
    getError,
    getFieldType,
    handleBlur,
    getIsFieldRequired
  } = useParameter({ index });

  return (
    <div className={classes.parameterComposition} data-testid="parameterGroup">
      <ItemComposition addButtonHidden>
        {keys(parameter).map((name) => (
          <div className={classes.parameterCompositionItem} key={name}>
            <ItemComposition.Item
              deleteButtonHidden
              className={classes.parameterItem}
              key={name}
            >
              <TextField
                disabled
                fullWidth
                dataTestId={name}
                label={t(labelName)}
                value={t(name)}
              />
              <TextField
                fullWidth
                dataTestId={`${name}_value`}
                error={getError?.(name)}
                label={t(labelValue)}
                name={name}
                required={getIsFieldRequired(name)}
                type={getFieldType(name)}
                value={parameter[name]}
                onBlur={handleBlur(`parameters.vcenters.${index}.${name}`)}
                onChange={changeParameterValue}
              />
            </ItemComposition.Item>
          </div>
        ))}
      </ItemComposition>
    </div>
  );
};

export default Parameter;
