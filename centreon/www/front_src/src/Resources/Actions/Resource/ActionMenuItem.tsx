import { MenuItem, MenuItemProps, Tooltip, Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { labelActionNotPermitted } from '../../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  description: {
    color: theme.palette.text.secondary,
    fontSize: '0.8rem',
    maxWidth: theme.spacing(31),
    whiteSpace: 'normal'
  },
  detailed: {
    alignItems: 'flex-start',
    display: 'flex',
    flexDirection: 'column'
  },
  title: {
    color: theme.palette.primary.main,
    fontWeight: 'bold'
  }
}));

type Props = {
  description?: string;
  label: string;
  permitted: boolean;
  testId: string;
} & Pick<MenuItemProps, 'onClick' | 'disabled'>;

const ActionMenuItem = ({
  permitted,
  label,
  description,
  testId,
  onClick,
  disabled
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const title = permitted ? '' : t(labelActionNotPermitted);

  return (
    <Tooltip title={title}>
      <div>
        <MenuItem
          className={description ? classes.detailed : undefined}
          data-testid={testId}
          disabled={disabled}
          onClick={onClick}
        >
          {description ? (
            <>
              <Typography className={classes.title}>{t(label)}</Typography>
              <Typography className={classes.description}>
                {t(description)}
              </Typography>
            </>
          ) : (
            t(label)
          )}
        </MenuItem>
      </div>
    </Tooltip>
  );
};

export default ActionMenuItem;
