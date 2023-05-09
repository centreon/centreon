import { FormikValues, useFormikContext } from 'formik';
import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Box, Typography } from '@mui/material';

import { RichTextEditor } from '@centreon/ui';

import { labelPreviewZone, labelPreviewEmail } from '../../translatedLabels';
import { emptyEmail } from '../initialValues';

const useStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'space-between'
  },
  preview: {
    background: theme.palette.background.paper,
    height: '100%',
    padding: theme.spacing(4, 1)
  },
  title: {
    background: theme.palette.background.paper,
    marginBottom: theme.spacing(1),
    padding: theme.spacing(1, 2)
  }
}));

const EmailPreview = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { values } = useFormikContext<FormikValues>();

  return (
    <Box className={classes.container}>
      <Typography className={classes.title}>{t(labelPreviewEmail)}</Typography>
      <Box className={classes.preview}>
        {equals(values?.messages.message, emptyEmail) ? (
          <Typography>{t(labelPreviewZone)}</Typography>
        ) : (
          <RichTextEditor
            editable={false}
            editorState={values?.messages.message}
            namespace="Preview"
          />
        )}
      </Box>
    </Box>
  );
};

export default EmailPreview;
