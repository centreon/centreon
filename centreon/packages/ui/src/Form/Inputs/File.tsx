import DescriptionOutlinedIcon from '@mui/icons-material/DescriptionOutlined';
import { Box, Typography } from '@mui/material';
import { FormikValues, useFormikContext } from 'formik';
import { path, split } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import FileDropZone, { transformFileListToArray } from '../../FileDropZone';
import { InputPropsWithoutGroup } from './models';

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

  const filesArray = transformFileListToArray(files);

  const changeFiles = (newFiles: FileList | null): void => {
    if (change) {
      change({ setFieldValue, setFieldTouched, value: newFiles });

      return;
    }

    setFieldValue(fieldName, newFiles);
  };

  return (
    <Box data-testid={dataTestId} aria-label={t(label)}>
      <Typography variant="h6">{t(label)}</Typography>
      <Box sx={{ display: 'flex', gap: 1, flexDirection: 'column' }}>
        <FileDropZone
          {...file}
          accept={file?.accept || '*'}
          files={files || null}
          changeFiles={changeFiles}
          resetFilesStatusAndUploadData={() => undefined}
          label={label}
        />
        <Box sx={{ display: 'flex', gap: 1, flexDirection: 'column' }}>
          {filesArray.map((file) => (
            <Box
              key={file.name}
              sx={{ display: 'flex', gap: 1, flexDirection: 'row' }}
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
