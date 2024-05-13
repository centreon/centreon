import { useTranslation } from 'react-i18next';
import { isEmpty, isNotNil } from 'ramda';

import { FormControlLabel, FormGroup, Checkbox } from '@mui/material';

import { Button } from '@centreon/ui/components';

import { WidgetPropertyProps } from '../../../models';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import Subtitle from '../../../../components/Subtitle';

import { useCheckboxes } from './useCheckboxes';
import { labelSelectAll, labelUnselectAll } from './translatedLabels';

const WidgetCheckboxes = ({
  propertyName,
  options,
  label,
  defaultValue,
  secondaryLabel,
  keepOneOptionSelected
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

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

  return (
    <div>
      <Subtitle secondaryLabel={secondaryLabel}>{t(label)}</Subtitle>
      {!keepOneOptionSelected && (isNotNil(options) || isEmpty(options)) && (
        <Button
          disabled={!canEditField}
          size="small"
          variant="ghost"
          onClick={areAllOptionsSelected ? unselectAll : selectAll}
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
