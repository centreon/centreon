import { Box, SvgIcon, Typography } from '@mui/material';

import parse from 'html-react-parser';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { widgetPropertiesAtom } from '../../atoms';
import { useWidgetMessageStyles } from '../widgetProperties.styles';

const WidgetMessage = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useWidgetMessageStyles();

  const selectedWidgetProperties = useAtomValue(widgetPropertiesAtom);

  const message = selectedWidgetProperties?.message;

  if (!message) {
    return <div />;
  }

  return (
    <Box className={classes.container}>
      {message?.icon && (
        <SvgIcon
          className={classes.icon}
          color="inherit"
          data-icon={message.label}
          data-testid="Message icon"
          viewBox="0 0 20 20"
        >
          {parse(message.icon)}
        </SvgIcon>
      )}
      <Typography className={classes.label} variant="h6">
        {t(message.label)}
      </Typography>
    </Box>
  );
};

export default WidgetMessage;
