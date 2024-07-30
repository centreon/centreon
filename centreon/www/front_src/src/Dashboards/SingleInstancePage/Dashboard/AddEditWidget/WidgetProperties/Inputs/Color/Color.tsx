import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { CardActionArea, Paper, Popover, Typography } from '@mui/material';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import KeyboardArrowUpIcon from '@mui/icons-material/KeyboardArrowUp';

import { Subtitle } from '@centreon/ui';
import { IconButton } from '@centreon/ui/components';

import { WidgetPropertyProps } from '../../../models';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import ColorChip from './ColorChip';
import { useColorSelector } from './useColorSelector';
import colors from './colors.json';
import { useColorSelectorStyle } from './ColorSelector.styles';

const ColorSelector = ({
  propertyName,
  isInGroup,
  label
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColorSelectorStyle();

  const { canEditField } = useCanEditProperties();

  const { value, toggle, isOpen, selectColor, isColorSelected } =
    useColorSelector({
      propertyName
    });

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div>
      {label && <Label>{t(label)}</Label>}
      <Paper className={classes.selectorContainer}>
        <CardActionArea
          className={classes.selectorContent}
          data-testid="color selector"
          disabled={!canEditField}
          onClick={toggle}
        >
          <ColorChip color={value ?? colors[0]} />
          {isOpen ? <KeyboardArrowUpIcon /> : <KeyboardArrowDownIcon />}
        </CardActionArea>
      </Paper>
      <Popover
        anchorEl={isOpen}
        anchorOrigin={{
          horizontal: 'left',
          vertical: 'bottom'
        }}
        open={Boolean(isOpen)}
        slotProps={{
          paper: {
            className: classes.popover
          }
        }}
        onClose={toggle}
      >
        <div className={classes.colors}>
          {colors.map((color) => (
            <IconButton
              className={isColorSelected(color) && classes.selectedColor}
              icon={<ColorChip color={color} />}
              key={color}
              size="small"
              onClick={selectColor(color)}
            />
          ))}
        </div>
      </Popover>
    </div>
  );
};

export default ColorSelector;
