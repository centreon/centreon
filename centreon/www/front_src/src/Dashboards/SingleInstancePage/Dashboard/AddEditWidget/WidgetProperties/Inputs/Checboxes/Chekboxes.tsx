import {
  Checkbox,
  FormControlLabel,
  FormGroup,
  Typography
} from '@mui/material';

import { Button } from '@centreon/ui/components';

import { isEmpty, isNotNil } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { WidgetPropertyProps } from '../../../models';
import { useResourceStyles } from '../Inputs.styles';
import { labelSelectAll, labelUnselectAll } from './translatedLabels';
import { useCheckboxes } from './useCheckboxes';

const WidgetCheckboxes = ({
  propertyName,
  options,
  label,
  defaultValue,
  secondaryLabel,
  keepOneOptionSelected,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useResourceStyles();

  const { canEditField } = useCanEditProperties();

  const {
    isChecked,
    change,
    selectAll,
    unselectAll,
    areAllOptionsSelected,
    optionsToDisplay
  } = useCheckboxes({
    defaultValue,
    keepOneOptionSelected,
    options: options || [],
    propertyName
  });

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div>
      <Label className={classes.subtitle} secondaryLabel={secondaryLabel}>
        {t(label)}
      </Label>
      {!keepOneOptionSelected && (isNotNil(options) || isEmpty(options)) && (
        <Button
          data-testid={
            areAllOptionsSelected ? t(labelUnselectAll) : t(labelSelectAll)
          }
          disabled={!canEditField}
          onClick={areAllOptionsSelected ? unselectAll : selectAll}
          size="small"
          variant="ghost"
        >
          {areAllOptionsSelected ? t(labelUnselectAll) : t(labelSelectAll)}
        </Button>
      )}
      <FormGroup>
        {optionsToDisplay.map(({ id, name }) => (
          <FormControlLabel
            control={
              <Checkbox
                checked={isChecked(id as string)}
                name={id as string}
                onChange={change}
              />
            }
            disabled={!canEditField}
            key={id}
            label={t(name)}
          />
        ))}
      </FormGroup>
    </div>
  );
};

export default WidgetCheckboxes;
