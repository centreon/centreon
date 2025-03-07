import { useAtomValue } from 'jotai';
import {
  T,
  cond,
  equals,
  flatten,
  identity,
  includes,
  isNil,
  split
} from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import PostAddIcon from '@mui/icons-material/PostAdd';
import { Box, FormHelperText, Typography, alpha } from '@mui/material';

import { userAtom } from '@centreon/ui-context';

import { useMemoComponent } from '../utils';

import { labelDropOr, labelSelectAFile } from './translatedLabels';
import useDropzone, { UseDropzoneState } from './useDropzone';

interface StylesProps {
  hasCustomDropZoneContent: boolean;
  isDraggingOver: boolean;
}

const useStyles = makeStyles<StylesProps>()(
  (theme, { hasCustomDropZoneContent, isDraggingOver }) => ({
    dropzone: {
      '&:hover': {
        backgroundColor: alpha(theme.palette.primary.main, 0.1),
        boxShadow: theme.shadows[3],
        cursor: 'pointer'
      },
      border: `${theme.spacing(0.3)} dashed ${theme.palette.primary.main}`,
      boxShadow: isDraggingOver ? theme.shadows[3] : theme.shadows[0],
      borderRadius: `${theme.shape.borderRadius}px`,
      padding: theme.spacing(0.5, 1),
      width: hasCustomDropZoneContent ? '100%' : theme.spacing(50)
    },
    dropzoneInfo: {
      display: 'grid',
      gridTemplateRows: hasCustomDropZoneContent
        ? undefined
        : 'repeat(2, min-content)',
      justifyItems: 'center',
      rowGap: theme.spacing(1)
    },
    input: {
      display: 'none'
    }
  })
);

export type CustomDropZoneContentProps = Pick<
  UseDropzoneState,
  'openFileExplorer'
> & { files: FileList | null; label?: string };

interface Props {
  CustomDropZoneContent?: ({
    openFileExplorer
  }: CustomDropZoneContentProps) => JSX.Element;
  accept: string;
  changeFiles: (files: FileList | null) => void;
  className?: string;
  files: FileList | null;
  maxFileSize?: number;
  multiple?: boolean;
  resetFilesStatusAndUploadData: () => void;
  label?: string;
}

const getExtensions = cond([
  [
    includes('image/'),
    (accept: string): Array<string> => {
      const allowedFilesExtensions = split('/', accept)[1];
      if (equals(allowedFilesExtensions, '*')) {
        return ['.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp'];
      }

      return split(',', allowedFilesExtensions).map(
        (extension) => `.${extension}`
      );
    }
  ],
  [T, identity]
]) as (accept: string) => Array<string>;

export const transformFileListToArray = (
  files: FileList | null
): Array<File> =>
  isNil(files)
    ? []
    : (Array(files.length)
        .fill(0)
        .map((_, idx) => files.item(idx)) as Array<File>);

const Dropzone = ({
  files,
  changeFiles,
  resetFilesStatusAndUploadData,
  multiple = false,
  accept,
  CustomDropZoneContent,
  maxFileSize,
  className,
  label
}: Props): JSX.Element => {
  const hasCustomDropZoneContent = !isNil(CustomDropZoneContent);
  const {
    dragOver,
    dropFiles,
    handleChangeFiles,
    error,
    fileInputRef,
    isDraggingOver,
    openFileExplorer
  } = useDropzone({
    allowedFilesExtensions: flatten(split(',', accept).map(getExtensions)),
    changeFiles,
    maxFileSize,
    resetFilesStatusAndUploadData
  });

  const { classes, cx } = useStyles({
    hasCustomDropZoneContent,
    isDraggingOver
  });
  const { t } = useTranslation();
  const { themeMode } = useAtomValue(userAtom);

  return useMemoComponent({
    Component: (
      <div>
        <Box
          className={cx(classes.dropzone, className)}
          onClick={openFileExplorer}
          onDragLeave={dragOver(false)}
          onDragOver={dragOver(true)}
          onDrop={dropFiles}
        >
          <div className={classes.dropzoneInfo}>
            {hasCustomDropZoneContent ? (
              <CustomDropZoneContent
                openFileExplorer={openFileExplorer}
                files={files}
                label={label}
              />
            ) : (
              <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
                <PostAddIcon color="primary" fontSize="large" />
                <Typography>
                  {t(labelDropOr)} {t(labelSelectAFile)}
                </Typography>
              </Box>
            )}
            <input
              accept={accept}
              aria-label={t(labelSelectAFile)}
              className={classes.input}
              multiple={multiple}
              ref={fileInputRef}
              type="file"
              onChange={handleChangeFiles}
            />
          </div>
        </Box>
        {error && <FormHelperText error>{t(error)}</FormHelperText>}
      </div>
    ),
    memoProps: [files, isDraggingOver, error, themeMode]
  });
};

export default Dropzone;
