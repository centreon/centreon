import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';
import { keys } from 'ramda';

import { TextField } from '@centreon/ui';
import { ItemComposition } from '@centreon/ui/components';

import { labelValue, labelName } from '../../translatedLabels';
import { Parameter } from '../models';

import { useParameterStyles } from './useParametersStyles';

interface Props {
  changeParameterValue: (event) => void;
  getError: (propertyName: string) => string;
  getFieldType: (name: string) => string;
  handleBlur: (proprtyName: string) => void;
  parameter: Parameter;
}

const Parameter = ({
  parameter,
  getFieldType,
  changeParameterValue,
  getError,
  handleBlur
}: Props): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useParameterStyles();

  return (
    <div className={classes.parameterComposition}>
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
                required
                dataTestId={labelName}
                label={t(labelName)}
                value={t(name)}
              />
              <TextField
                fullWidth
                required
                dataTestId={name}
                error={getError(name)}
                label={t(labelValue)}
                name={name}
                type={getFieldType(name)}
                value={parameter[name]}
                onBlur={handleBlur(name)}
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
