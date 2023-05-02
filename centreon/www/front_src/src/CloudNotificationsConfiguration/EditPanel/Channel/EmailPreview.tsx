import { FormikValues, useFormikContext } from 'formik';
import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { RichTextEditor } from '@centreon/ui';

import { labelPreviewZone } from '../../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'space-between'
  },
  preview: {
    background: theme.palette.background.paper,
    height: '90%',
    padding: theme.spacing(4, 2)
  },
  title: {
    background: theme.palette.background.paper,
    padding: theme.spacing(0.8)
  }
}));

const EmailPreview = (): JSX.Element => {
  const { values } = useFormikContext<FormikValues>();
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Box className={classes.container}>
      <Typography className={classes.title}>Preview Email</Typography>
      <Box className={classes.preview}>
        <RichTextEditor
          editable={false}
          editorState={values?.messages.message}
          namespace="Preview"
          placeholder={t(labelPreviewZone)}
        />
      </Box>
    </Box>
  );
};

export default EmailPreview;
