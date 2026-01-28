import DescriptionOutlinedIcon from '@mui/icons-material/DescriptionOutlined';
import { Box, Typography } from '@mui/material';

import { type FormikValues, useFormikContext } from 'formik';
import { path, split } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import FileDropZone, { transformFileListToArray } from '../../FileDropZone';
import type { InputPropsWithoutGroup } from './models';

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
    [values, fieldNamePath]
  ) as FileList;

  const filesArray = transformFileListToArray(files);

  const changeFiles = (newFiles: FileList | null): void => {
    if (change) {
      change({ setFieldTouched, setFieldValue, value: newFiles });

      return;
    }

    setFieldValue(fieldName, newFiles);
  };

  return (
    <Box aria-label={t(label)} data-testid={dataTestId}>
      <Typography variant="h6">{t(label)}</Typography>
      <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
        <FileDropZone
          {...file}
          accept={file?.accept || '*'}
          changeFiles={changeFiles}
          files={files || null}
          label={label}
          resetFilesStatusAndUploadData={() => undefined}
        />
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
          {filesArray.map((file) => (
            <Box
              key={file.name}
              sx={{ display: 'flex', flexDirection: 'row', gap: 1 }}
            >
              <DescriptionOutlinedIcon color="success" fontSize="small" />
              <Typography>{file.name}</Typography>
            </Box>
          ))}
        </Box>
      </Box>
    </Box>
  );
};

export default File;
