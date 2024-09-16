import { FormikValues, useFormikContext } from 'formik';
import FileDropZone, { transformFileListToArray } from '../../FileDropZone';
import { InputPropsWithoutGroup } from './models';
import { isEmpty, isNil, path, split } from 'ramda';
import { useMemo } from 'react';
import { PostAdd } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { Box, Typography } from '@mui/material';
import { labelDropOrSelectAFile } from '../translatedLabels';

interface FileContentProps {
  files: FileList | null;
  label?: string;
}

const File = ({
  fieldName,
  file,
  change,
  dataTestId,
  label
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const files = useMemo(
    () => path(fieldNamePath, values),
    [values]
  ) as FileList;

  const changeFiles = (newFiles: FileList | null): void => {
    if (change) {
      change({ setFieldValue, setFieldTouched, value: newFiles });

      return;
    }

    setFieldValue(fieldName, newFiles);
  };

  return (
    <Box
      data-testid={dataTestId}
      aria-label={t(label)}
      sx={{ position: 'relative' }}
    >
      <FileDropZone
        {...file}
        accept={file?.accept || '*'}
        files={files || null}
        changeFiles={changeFiles}
        resetFilesStatusAndUploadData={() => undefined}
        label={label}
      />
    </Box>
  );
};

export default File;
