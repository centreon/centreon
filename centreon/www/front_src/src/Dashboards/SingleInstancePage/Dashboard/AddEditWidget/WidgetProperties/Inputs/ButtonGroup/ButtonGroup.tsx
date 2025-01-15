import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import {
  Button,
  ButtonGroup as MuiButtonGroup,
  Stack,
  Typography
} from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { WidgetPropertyProps } from '../../../models';
import { useResourceStyles } from '../Inputs.styles';

import { useButtonGroup } from './useButtonGroup';

const ButtonGroup = ({
  propertyName,
  options,
  isInGroup,
  label,
  secondaryLabel
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useResourceStyles();

  const { canEditField } = useCanEditProperties();

  const { isButtonSelected, selectOption } = useButtonGroup({
    propertyName
  });

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div>
      <Label className={classes.subtitle}>{t(label)}</Label>
      <Stack alignItems="center" direction="row" gap={1.5}>
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
        {secondaryLabel && (
          <Tooltip
            followCursor={false}
            label={t(secondaryLabel)}
            position="right"
          >
            <InfoOutlinedIcon
              color="primary"
              data-testid={`secondary-label-${propertyName}`}
              fontSize="small"
            />
          </Tooltip>
        )}
      </Stack>
    </div>
  );
};

export default ButtonGroup;
