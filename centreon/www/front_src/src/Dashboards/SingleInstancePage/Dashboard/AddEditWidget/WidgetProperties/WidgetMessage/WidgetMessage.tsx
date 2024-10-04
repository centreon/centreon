import { Box, SvgIcon, Typography } from '@mui/material';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { widgetPropertiesAtom } from '../../atoms';
import { useWidgetMessageStyles } from '../widgetProperties.styles';

import parse from 'html-react-parser';

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
          viewBox="0 0 20 20"
          data-testid="Message icon"
        >
          {parse(message.icon)}
        </SvgIcon>
      )}
      <Typography variant="h6" className={classes.label}>
        {t(message.label)}
      </Typography>
    </Box>
  );
};

export default WidgetMessage;
