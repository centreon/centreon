import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import {
  Button,
  ButtonGroup as MuiButtonGroup,
  Typography
} from '@mui/material';

import { WidgetPropertyProps } from '../../../models';
import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { useResourceStyles } from '../Inputs.styles';
import SubInputs from '../../SubInputs';

import { useButtonGroup } from './useButtonGroup';

const ButtonGroup = ({
  propertyName,
  options,
  isInGroup,
  subInputs,
  label
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useResourceStyles();

  const { canEditField } = useCanEditProperties();

  const { isButtonSelected, selectOption, value } = useButtonGroup({
    propertyName
  });

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <SubInputs subInputs={subInputs} value={value}>
      <div>
        <Label className={classes.subtitle}>{t(label)}</Label>
        <MuiButtonGroup disabled={!canEditField} size="small">
          {options?.map(({ id, name }) => (
            <Button
              aria-label={t(name)}
              data-selected={isButtonSelected(id)}
              data-testId={id}
              key={id}
              variant={isButtonSelected(id) ? 'contained' : 'outlined'}
              onClick={selectOption(id)}
            >
              {t(name)}
            </Button>
          ))}
        </MuiButtonGroup>
      </div>
    </SubInputs>
  );
};

export default ButtonGroup;
