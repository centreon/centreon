import { TextField } from '@centreon/ui';
import { ItemComposition } from '@centreon/ui/components';

import { equals, keys } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { ParameterKeys, Parameter as ParameterModel } from '../../models';
import PasswordFiled from './PasswordField/PasswordField';
import useParameter from './useParameter';
import { useParameterStyles } from './useParametersStyles';

interface Props {
  index: number;
  parameter: ParameterModel;
}

const Parameter = ({ parameter, index }: Props): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useParameterStyles();

  const { changeParameterValue, getError, handleBlur } = useParameter({
    index
  });

  return (
    <ItemComposition addButtonHidden>
      <div
        className={classes.parameterComposition}
        data-testid="parameterGroup"
      >
        {keys(parameter).map((name) => (
          <div className={classes.parameterCompositionItem} key={name}>
            <ItemComposition.Item
              className={classes.parameterItem}
              deleteButtonHidden
              key={name}
            >
              {equals(name, ParameterKeys.password) ? (
                <PasswordFiled
                  error={getError?.(name)}
                  index={index}
                  onBlur={handleBlur(`parameters.vcenters.${index}.${name}`)}
                  value={parameter[name]}
                />
              ) : (
                <TextField
                  dataTestId={`${name}_value`}
                  error={getError?.(name)}
                  fullWidth
                  label={t(name)}
                  name={name}
                  onBlur={handleBlur(`parameters.vcenters.${index}.${name}`)}
                  onChange={changeParameterValue}
                  required={true}
                  type="text"
                  value={parameter[name] as string}
                />
              )}
            </ItemComposition.Item>
          </div>
        ))}
      </div>
    </ItemComposition>
  );
};

export default Parameter;
